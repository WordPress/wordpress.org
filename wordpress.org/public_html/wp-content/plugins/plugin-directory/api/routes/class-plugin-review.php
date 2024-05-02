<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
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
	function __construct() {
		// An API Endpoint to expose more detailed plugin data for a pending plugin.
		register_rest_route( 'plugins/v1', '/plugin-review/(?P<plugin_id>\d+)-(?P<token>[a-f0-9]{32})/?', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'plugin_review_info' ),
			'permission_callback' => array( $this, 'plugin_info_permission_check' ),
		) );
	}

	/**
	 * Permission check that validates the hash for a pending plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	public function plugin_info_permission_check( $request ) {
		$post          = get_post( $request['plugin_id'] );
		$expected_hash = wp_hash( $post->ID, 'plugin-review' );

		return (
			$post &&
			$expected_hash &&
			! empty( $request['token'] ) &&
			hash_equals( $expected_hash, $request['token'] )
		);
	}

	/**
	 * Append a link to the plugin review info endpoint to a URL.
	 *
	 * @param string   $url  The URL.
	 * @param \WP_Post $post The WP post.
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
	 */
	public static function get_plugin_review_info_url( $post ) {
		return rest_url( sprintf(
			'plugins/v1/plugin-review/%d-%s/',
			$post->ID,
			wp_hash( $post->ID, 'plugin-review' )
		) );
	}

	/**
	 * Endpoint to retrieve a full plugin representation for a pending plugin.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	public function plugin_review_info( $request ) {
		$post      = get_post( $request['plugin_id'] );
		$submitter = get_user_by( 'id', $post->post_author );

		if ( ! $post ) {
			return new WP_Error( 'plugin_not_found', 'Plugin not found', [ 'status' => 404 ] );
		}

		// Review-specific fields.
		$details = [
			'ID'            => $post->ID,
			'post_status'   => $post->post_status,
			'edit_url'      => add_query_arg( [ 'action' => 'edit', 'post' => $post->ID ], admin_url( 'post.php' ) ),
			'helpscout'     => null, // Most recent email details.
			'submitter'     => [
				'user_login' => $submitter->user_login,
				'user_email' => $submitter->user_email,
			],
			'zips'          => [],
		];

		// Append the public api fields.
		$details = $details + (new Plugin)->plugin_info_data( $request, $post );

		// When the plugin is pre-publish, we'll overwrite some fields.
		if ( in_array( $post->post_status, [ 'new', 'pending', 'approved' ] ) ) {
			$details['download_link'] = null;
			$details['preview_link']  = null;
			$details['helpscout']     = Tools::get_helpscout_emails( $post, [ 'subject' => 'Review in Progress:', 'limit' => 1 ] );
		} else {
			$details['helpscout']     = Tools::get_helpscout_emails( $post, [ 'limit' => 1 ] );
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
}
