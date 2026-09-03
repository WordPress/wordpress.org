<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\Helpscout;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Reviewer;
use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;
use WP_Error;
use WP_REST_Server;

/**
 * Plugin-review related endpoints.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Review extends Base {

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
		$plugin_route = '/plugin-review/(?P<plugin_id>\d+)-(?P<token>[a-f0-9]{32})';

		// An API Endpoint to expose more detailed plugin data for a pending plugin.
		register_rest_route(
			'plugins/v1',
			$plugin_route . '/?',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'plugin_review_info' ),
				'permission_callback' => array( $this, 'plugin_info_permission_check' ),
			)
		);

		/*
		 * The endpoints below change a plugin. They're not authenticated as a WordPress.org
		 * user, so each of them is told which user is performing the action.
		 */
		$user_id_arg = array(
			'user_id' => array(
				'description' => 'The WordPress.org user performing the action.',
				'type'        => 'integer',
				'minimum'     => 1,
				'required'    => true,
			),
		);

		// An API Endpoint to change the status of a plugin from new to pending and assign a reviewer to it.
		register_rest_route(
			'plugins/v1',
			$plugin_route . '/assign',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'assign_reviewer' ),
				'permission_callback' => array( $this, 'plugin_change_permission_check' ),
				'args'                => $user_id_arg,
			)
		);

		// An API Endpoint to change the status of a plugin to approved.
		register_rest_route(
			'plugins/v1',
			$plugin_route . '/approve',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'approve_plugin' ),
				'permission_callback' => array( $this, 'plugin_change_permission_check' ),
				'args'                => $user_id_arg,
			)
		);

		// An API Endpoint to change the slug of a plugin which hasn't been approved yet.
		register_rest_route(
			'plugins/v1',
			$plugin_route . '/slug',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'change_slug' ),
				'permission_callback' => array( $this, 'plugin_change_permission_check' ),
				'args'                => $user_id_arg + array(
					'slug' => array(
						'description' => 'The new slug for the plugin.',
						'type'        => 'string',
						'minLength'   => 1,
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * Permission check that validates the hash for a pending plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool Whether the token matches the plugin.
	 */
	public function plugin_info_permission_check( $request ) {
		if ( empty( $request['plugin_id'] ) || empty( $request['token'] ) ) {
			return false;
		}

		$post          = get_post( $request['plugin_id'] );
		$expected_hash = $post ? wp_hash( $post->ID, 'plugin-review' ) : '';

		return (
			$post &&
			$expected_hash &&
			hash_equals( $expected_hash, $request['token'] )
		);
	}

	/**
	 * Append a link to the plugin review info endpoint to a URL.
	 *
	 * @param string  $url  The URL.
	 * @param WP_Post $post The WP post.
	 * @return string
	 */
	public static function append_plugin_review_info_url( $url, $post ) {
		if ( ! $url || ! $post || str_contains( $url, '#wporgapi:' ) ) {
			return $url;
		}

		// Append with a anchor, such that CLI environments don't require special handling.
		$url .= '#wporgapi:' . self::get_plugin_review_info_url( $post );

		return $url;
	}
	/**
	 * Fetch the URL to the plugin review info endpoint.
	 *
	 * @param WP_Post $post The plugin post object.
	 * @return string The generated plugin review information URL.
	 */
	public static function get_plugin_review_info_url( $post ) {
		return rest_url(
			sprintf(
				'plugins/v1/plugin-review/%d-%s/',
				$post->ID,
				wp_hash( $post->ID, 'plugin-review' )
			)
		);
	}

	/**
	 * Endpoint to retrieve a full plugin representation for a pending plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array|WP_Error A formatted array of all the data for the plugin.
	 */
	public function plugin_review_info( $request ) {
		$post = get_post( $request['plugin_id'] );
		if ( ! $post ) {
			return new WP_Error( 'plugin_not_found', 'Plugin not found', [ 'status' => 404 ] );
		}

		$submitter = get_user_by( 'id', $post->post_author );

		// Review-specific fields.
		$details = [
			'ID'          => $post->ID,
			'post_status' => $post->post_status,
			'edit_url'    => add_query_arg(
				[
					'action' => 'edit',
					'post'   => $post->ID,
				],
				admin_url( 'post.php' )
			),
			'helpscout'   => null, // Most recent email details.
			'submitter'   => [
				'user_login' => $submitter->user_login,
				'user_email' => $submitter->user_email,
			],
			'zips'        => [],
		];

		// Append the public api fields.
		$details = $details + ( new Plugin() )->plugin_info_data( $request, $post );

		// When the plugin is pre-publish, we'll overwrite some fields.
		if ( in_array( $post->post_status, [ 'new', 'pending', 'approved' ], true ) ) {
			$details['download_link'] = null;
			$details['preview_link']  = null;
			$details['helpscout']     = Helpscout::get_emails(
				$post,
				[
					'subject' => 'Review in Progress:',
					'limit'   => 1,
				]
			);
		} else {
			$details['helpscout'] = Helpscout::get_emails( $post, [ 'limit' => 1 ] );
		}

		$attachments = get_attached_media( 'application/zip', $post );
		if ( $attachments ) {
			foreach ( $attachments as $zip_file ) {
				$url     = self::append_plugin_review_info_url( wp_get_attachment_url( $zip_file->ID ), $post );
				$preview = Template::preview_link_zip( $post->post_name, $zip_file->ID, 'pcp' );

				$details['zips'][] = [
					'url'     => $url,
					'name'    => $zip_file->submitted_name ?: preg_split( '/[?#]/', basename( $url ) )[0],
					'date'    => $zip_file->post_date,
					'version' => $zip_file->version,
					'note'    => $zip_file->post_content,
					'preview' => $preview,
				];
			}

			// Use the last one, which should be the latest.
			$details['download_link'] ??= $url;
			$details['preview_link']  ??= $preview;
		}

		// For a published plugin, append the API url.
		$details['download_link'] = self::append_plugin_review_info_url( $details['download_link'], $post );

		return $details;
	}

	/**
	 * Permission check for the endpoints which change a plugin.
	 *
	 * The requests are not authenticated as a WordPress.org user. They're authorised by the
	 * per-plugin internal token in the URL, which limits a request to a single plugin, plus a
	 * shared secret which is only known to the clients allowed to make changes.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|WP_Error True if the request is authorised, WP_Error upon failure.
	 */
	public function plugin_change_permission_check( $request ) {
		$secret_check = $this->permission_check_api_bearer( $request, 'PLUGIN_REVIEW_ENDPOINT_SECRET' );
		if ( is_wp_error( $secret_check ) ) {
			return $secret_check;
		}

		return $this->plugin_info_permission_check( $request );
	}

	/**
	 * Fetch the plugin a request is for.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return \WP_Post|WP_Error The plugin, WP_Error if it doesn't exist.
	 */
	protected function get_plugin( $request ) {
		$post = get_post( $request['plugin_id'] );

		if ( ! $post || 'plugin' !== $post->post_type ) {
			return new WP_Error( 'plugin_not_found', 'Plugin not found', [ 'status' => 404 ] );
		}

		return $post;
	}

	/**
	 * Fetch the user performing a change, and act as them for the rest of the request.
	 *
	 * The request isn't made by a logged in user, so the user is taken from `user_id`. Setting
	 * them as the current user attributes the change and the audit log entries to them.
	 *
	 * @param \WP_REST_Request $request    The Rest API Request.
	 * @param string           $capability The capability the user needs to make the change.
	 * @return \WP_User|WP_Error The user, WP_Error if they cannot make the change.
	 */
	protected function get_acting_user( $request, $capability ) {
		$user = get_user_by( 'id', $request['user_id'] );

		if ( ! $user ) {
			return new WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
		}

		if ( ! user_can( $user, $capability ) ) {
			return new WP_Error( 'user_cannot_do_that', 'The given user cannot perform this action', [ 'status' => 403 ] );
		}

		wp_set_current_user( $user->ID );

		return $user;
	}

	/**
	 * Change the status of a plugin.
	 *
	 * The side-effects of the change are handled by `Status_Transitions`.
	 *
	 * @param \WP_Post $post   The plugin.
	 * @param string   $status The new post status.
	 * @return bool|WP_Error True if the status was changed, WP_Error upon failure.
	 */
	protected function set_plugin_status( $post, $status ) {
		/*
		 * The transition actions are only hooked in the admin, so opt this request in to get
		 * the same side-effects a status change from the Edit Plugin screen has.
		 */
		Status_Transitions::init();

		$result = wp_update_post(
			[
				'ID'          => $post->ID,
				'post_status' => $status,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			$result->add_data( [ 'status' => 500 ] );
			return $result;
		}

		if ( 0 === $result ) {
			return new WP_Error( 'plugin_status_not_updated', 'Failed to update plugin status', [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Endpoint to change the status of a plugin from new to pending and assign a reviewer to it.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|WP_Error
	 */
	public function assign_reviewer( $request ) {
		$post = $this->get_plugin( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( 'new' !== $post->post_status ) {
			return new WP_Error( 'invalid_status', 'Plugin is not in "new" status', [ 'status' => 400 ] );
		}

		$reviewer = $this->get_acting_user( $request, 'plugin_review' );
		if ( is_wp_error( $reviewer ) ) {
			return $reviewer;
		}

		// Assign the reviewer first, so that a failure here leaves the plugin untouched.
		$assigned_reviewer = (int) get_post_meta( $post->ID, 'assigned_reviewer', true );
		$reviewer_changed  = $assigned_reviewer !== $reviewer->ID;

		if ( $reviewer_changed && ! Reviewer::set_reviewer( $post->ID, $reviewer->ID ) ) {
			return new WP_Error( 'reviewer_not_assigned', 'Failed to assign reviewer', [ 'status' => 500 ] );
		}

		$result = $this->set_plugin_status( $post, 'pending' );
		if ( is_wp_error( $result ) && $reviewer_changed ) {
			Reviewer::set_reviewer( $post->ID, $assigned_reviewer );
		}

		return $result;
	}

	/**
	 * Endpoint to change the status of a plugin to approved.
	 *
	 * Approving a plugin creates its SVN repository, grants the author commit access and
	 * emails them, all of which is handled by `Status_Transitions`.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|WP_Error
	 */
	public function approve_plugin( $request ) {
		$post = $this->get_plugin( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$allowed_transitions = Status_Transitions::get_allowed_transitions( $post->post_status, $post );
		if ( ! in_array( 'approved', $allowed_transitions, true ) ) {
			return new WP_Error(
				'invalid_status',
				sprintf( 'A plugin with the "%s" status cannot be approved', $post->post_status ),
				[ 'status' => 400 ]
			);
		}

		$user = $this->get_acting_user( $request, 'plugin_approve' );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return $this->set_plugin_status( $post, 'approved' );
	}

	/**
	 * Endpoint to change the slug of a plugin.
	 *
	 * Limited to plugins which haven't been approved yet. Approved plugins have a SVN
	 * repository, and renaming that is only handled by the Edit Plugin screen.
	 *
	 * The new slug goes through the same availability checks as an author requesting a slug
	 * change, minus the trademark check, as reviewers rename plugins to resolve trademarks.
	 *
	 * The slug the plugin ends up with isn't always the one requested, so the response
	 * carries both and the caller can tell the reviewer which slug they got.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array|WP_Error {
	 *     The renamed plugin, WP_Error upon failure.
	 *
	 *     @type string $slug           The slug the plugin now has.
	 *     @type string $requested_slug The slug which was requested.
	 * }
	 */
	public function change_slug( $request ) {
		$post = $this->get_plugin( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! in_array( $post->post_status, [ 'new', 'pending' ], true ) ) {
			return new WP_Error( 'invalid_status', 'Plugin is not awaiting review', [ 'status' => 400 ] );
		}

		/*
		 * `plugin_approve` is the plugin post type's `publish_posts` capability, which
		 * `wp_insert_post()` requires to set the slug of a pending post.
		 */
		$user = $this->get_acting_user( $request, 'plugin_approve' );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$old_slug = $post->post_name;
		$new_slug = trim( $request['slug'] );

		if ( sanitize_title_with_dashes( $new_slug ) !== $new_slug ) {
			return new WP_Error( 'invalid_slug', 'Slugs may only contain the lowercase characters a-z, 0-9, and -', [ 'status' => 400 ] );
		}

		if ( $new_slug === $old_slug ) {
			return new WP_Error( 'slug_unchanged', 'That is already the slug of this plugin', [ 'status' => 400 ] );
		}

		if ( Plugin_Directory::get_plugin_post( $new_slug ) ) {
			return new WP_Error( 'slug_in_use', 'That slug is already in use', [ 'status' => 400 ] );
		}

		// Short slugs are too generic to hand out.
		if ( strlen( $new_slug ) < 5 ) {
			return new WP_Error( 'too_short', 'That slug is too short', [ 'status' => 400 ] );
		}

		// Some slugs would clash with the directory itself, or with well known plugins.
		$upload_handler              = new Upload_Handler();
		$upload_handler->plugin_slug = $new_slug;

		if ( $upload_handler->has_reserved_slug() ) {
			return new WP_Error( 'reserved_slug', 'That slug is reserved', [ 'status' => 400 ] );
		}

		/*
		 * A slug with a significant install base outside of the directory can't be handed out
		 * either, as updates from here would overwrite those installs. It's a heuristic on the
		 * plugin name, so it's the last check to run.
		 */
		if ( function_exists( 'wporg_stats_get_plugin_name_install_count' ) ) {
			$name     = ucwords( str_replace( '-', ' ', $new_slug ) );
			$installs = wporg_stats_get_plugin_name_install_count( $name );

			if ( $installs && $installs->count >= 100 ) {
				return new WP_Error( 'slug_in_use_in_the_wild', 'That slug is already in use by a plugin hosted elsewhere', [ 'status' => 400 ] );
			}
		}

		$result = wp_update_post(
			[
				'ID'        => $post->ID,
				'post_name' => $new_slug,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			$result->add_data( [ 'status' => 500 ] );
			return $result;
		}

		/*
		 * `wp_insert_post()` alters the slug in a few cases, most commonly by suffixing it to
		 * keep it unique when the slug was taken between the check above and this update, so
		 * the slug the plugin ended up with is read back rather than assumed.
		 */
		$saved_slug = get_post_field( 'post_name', $post->ID );

		if ( ! $saved_slug || $saved_slug === $old_slug ) {
			return new WP_Error( 'slug_not_updated', 'Failed to update the plugin slug', [ 'status' => 500 ] );
		}

		$audit_entry = sprintf( "Slug changed from '%s' to '%s'.", $old_slug, $saved_slug );

		if ( $saved_slug !== $new_slug ) {
			$audit_entry .= sprintf( " The requested slug, '%s', was not available.", $new_slug );
		}

		Tools::audit_log( $audit_entry, $post->ID );

		return [
			'slug'           => $saved_slug,
			'requested_slug' => $new_slug,
		];
	}
}
