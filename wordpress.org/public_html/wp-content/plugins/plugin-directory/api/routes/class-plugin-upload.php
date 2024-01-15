<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;
use WP_REST_Server;
use WP_Error;

/**
 * An API Endpoint to expose a single Plugin data via api.wordpress.org/plugins/info/1.x
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Upload extends Base {

	/**
	 * Plugin constructor.
	 */
	function __construct() {
		register_rest_route( 'plugins/v1', '/upload/(?P<ID>[0-9]+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'upload' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args' => [
				'post_name' => [
					'type'     => 'string',
					'required' => false,
				],
			]
		) );

		register_rest_route( 'plugins/v1', '/upload/(?P<ID>[0-9]+)/slug', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'slug' ),
			'permission_callback' => array( $this, 'permission_check' ),
			'args' => [
				'post_name' => [
					'type'     => 'string',
					'required' => true,
				],
			]
		) );
	}

	public function permission_check( $request ) {
		if (
			! current_user_can( 'plugin_approve' ) &&
			get_current_user_id() != get_post_field( 'post_author', $request['ID'] )
		) {
			return false;
		}

		$post = get_post( $request['ID'] );
		if ( $post->ID != $request['ID'] || 'plugin' !== $post->post_type ) {
			return false;
		}

		return true;
	}

	public function upload( $request ) {
		$plugin = get_post( $request['ID'] );

		if ( ! empty( $request['post_name'] ) ) {
			return $this->slug( $request );

		} elseif ( ! empty( $_FILES['zip_file'] ) && current_user_can( 'plugin_approve' ) ) {
			return ( new Upload_Handler() )->process_upload( $plugin->ID );
		}
	}

	/**
	 * Change the slug of a plugin.
	 */
	public function slug( $request ) {
		$plugin = get_post( $request['ID'] );
		$slug   = trim( $request['post_name'] ?? '' );
		$result = $this->perform_slug_change( $plugin, $slug );
		if ( is_wp_error( $result ) ) {
			// Warn the reviewer when a plugin author has attempted to use an unavailable slug.
			Tools::audit_log(
				sprintf(
					"Attempt to change slug to '%s' blocked: %s",
					esc_html( $slug ),
					$result->get_error_code()
				),
				$plugin
			);

			$result = new WP_Error( 'error', $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Perform the slug change request.
	 *
	 * TODO: This is duplicated from Upload_Handler. The slug checks should be moved to plugin-check and called from both.
	 */
	protected function perform_slug_change( $plugin, $slug ) {
		// Only newly submitted plugins.
		if ( 'new' !== $plugin->post_status ) {
			return new WP_Error( 'cant_change_slug', __( "You can't change the slug of this plugin.", 'wporg-plugins' ) );
		}

		// Has it already been changed once?
		if ( $plugin->{'_wporg_plugin_original_slug'} && ! current_user_can( 'plugin_approve' ) ) {
			return new WP_Error( 'cant_change_slug', __( "You can't change the slug of this plugin.", 'wporg-plugins' ) );
		}

		// Check the slug is in a valid format.
		if ( $slug != sanitize_title_with_dashes( $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Invalid slug. Slugs may only contain the lowercase characters a-z, 0-9, and -.', 'wporg-plugins' ) );
		}

		// Check the plugin can have it's slug changed.
		if ( $slug == $plugin->post_name ) {
			return new WP_Error( 'thats_your_slug', __( "That's already your slug.", 'wporg-plugins' ) );
		}

		// Check if the slug isn't already in use.
		$existing = Plugin_Directory::get_plugin_post( $slug );
		if ( $existing ) {
			return new WP_Error( 'slug_in_use', __( 'That slug is already in use.', 'wporg-plugins' ) );
		}

		// Short slugs are not great.
		if ( strlen( $slug ) < 5 ) {
			return new WP_Error( 'too_short', __( 'Error: The plugin slug is too short.', 'wporg-plugins' ) );
		}

		$upload_handler = new Upload_Handler();
		$upload_handler->plugin_slug = $slug;

		// Check if the slug isn't going to cause us problems.
		if ( $upload_handler->has_reserved_slug() ) {
			return new WP_Error( 'reserved_slug', __( 'That slug is already in use.', 'wporg-plugins' ) );
		}

		// Duplicated from Upload handler.
		// Make sure it doesn't use a TRADEMARK protected slug.
		if ( false !== $upload_handler->has_trademarked_slug()  ) {
			$error = __( 'That plugin slug includes a restricted term.', 'wporg-plugins' );

			if ( $upload_handler->has_trademarked_slug() === trim( $upload_handler->has_trademarked_slug(), '-' ) ) {
				// Trademarks that do NOT end in "-" indicate slug cannot contain term at all.
				$message = sprintf(
					/* translators: 1: plugin slug, 2: trademarked term, 3: 'Plugin Name:', 4: plugin email address */
					__( 'Your chosen plugin slug - %1$s - contains the restricted term "%2$s", which cannot be used at all in your plugin permalink nor the display name.', 'wporg-plugins' ),
					'<code>' . $slug . '</code>',
					trim( $upload_handler->has_trademarked_slug(), '-' )
				);
			} else {
				// Trademarks ending in "-" indicate slug cannot BEGIN with that term.
				$message = sprintf(
					/* translators: 1: plugin slug, 2: trademarked term, 3: 'Plugin Name:', 4: plugin email address */
					__( 'Your chosen plugin slug - %1$s - contains the restricted term "%2$s" and cannot be used to begin your permalink or display name. We disallow the use of certain terms in ways that are abused, or potentially infringe on and/or are misleading with regards to trademarks.', 'wporg-plugins' ),
					'<code>' . $slug . '</code>',
					trim( $upload_handler->has_trademarked_slug(), '-' )
				);
			}

			return new WP_Error( 'trademarked_slug', $error . ' ' . $message );
		}

		// Not ideal, but it's better than nothing.
		if ( function_exists( 'wporg_stats_get_plugin_name_install_count' ) ) {
			$name = str_replace( '-', ' ', $slug );
			$name = ucwords( $name );

			$installs = wporg_stats_get_plugin_name_install_count( $name );
			if ( $installs && $installs->count >= 100 ) {
				return new WP_Error( 'slug_in_use_in_the_wild', __( 'That slug is already in use.', 'wporg-plugins' ) );
			}
		}

		// Proceed with the slug change.
		Tools::audit_log(
			sprintf(
				'Changed slug from %s to %s',
				$plugin->post_name,
				$slug
			),
			$plugin
		);
		update_post_meta( $plugin->ID, '_wporg_plugin_original_slug', $plugin->post_name );
		$success = wp_update_post( [
			'ID'        => $plugin->ID,
			'post_name' => $slug,
		] );
		if ( ! $success ) {
			return new WP_Error( 'unknown_error', __( 'An unknown error occurred.', 'wporg-plugins' ) );
		}

		// Refresh.
		$plugin = get_post( $plugin->ID );

		$this->send_slug_change_email( $plugin );

		return true;
	}

	public function send_slug_change_email( $plugin ) {
		/* translators: %s: plugin name */
		$email_subject = sprintf(
			'Re: ' . __( '[WordPress Plugin Directory] Successful Plugin Submission - %s', 'wporg-plugins' ),
			$plugin->post_title
		);

		$email_content = sprintf(
			// translators: 1: plugin slug.
			__(
'Your request to change your plugin slug to %1$s has been received and will be reviewed by our team.

We will contact you when your plugin has been approved or if we have any questions.

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins'
			),
			$plugin->post_name
		);

		$user_email = get_user_by( 'id', $plugin->post_author )->user_email;

		wp_mail( $user_email, $email_subject, $email_content, 'From: plugins@wordpress.org' );
	}

}
