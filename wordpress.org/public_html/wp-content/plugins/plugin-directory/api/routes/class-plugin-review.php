<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools\Helpscout;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Reviewer;
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
		// An API Endpoint to expose more detailed plugin data for a pending plugin.
		register_rest_route(
			'plugins/v1',
			'/plugin-review/(?P<plugin_id>\d+)-(?P<token>[a-f0-9]{32})/?',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'plugin_review_info' ),
				'permission_callback' => array( $this, 'plugin_info_permission_check' ),
			)
		);

		// An API Endpoint to change the status of a plugin from new to pending and assign a reviewer to it.
		register_rest_route(
			'plugins/v1',
			'/plugin-review/(?P<plugin_id>\d+)-(?P<token>[a-f0-9]{32})/assign',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'assign_reviewer' ),
				'permission_callback' => array( $this, 'assign_reviewer_permission_check' ),
				'args'                => array(
					'user_id' => array(
						'description' => 'The WordPress.org user performing the action, who will be assigned as the reviewer.',
						'type'        => 'integer',
						'minimum'     => 1,
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
	 * Permission check for assigning a reviewer.
	 *
	 * The request is not authenticated as a WordPress.org user. It's authorised by the
	 * per-plugin internal token in the URL, which limits the request to a single plugin, plus a
	 * shared secret which is only known to the clients allowed to make changes.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|WP_Error True if the request is authorised, WP_Error upon failure.
	 */
	public function assign_reviewer_permission_check( $request ) {
		$secret_check = $this->permission_check_api_bearer( $request, 'PLUGIN_REVIEW_ENDPOINT_SECRET' );
		if ( is_wp_error( $secret_check ) ) {
			return $secret_check;
		}

		return $this->plugin_info_permission_check( $request );
	}

	/**
	 * Endpoint to change the status of a plugin from new to pending and assign a reviewer to it.
	 *
	 * The reviewer is passed as `user_id`, as the request isn't made by a logged in user.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|WP_Error
	 */
	public function assign_reviewer( $request ) {
		$post     = get_post( $request['plugin_id'] );
		$reviewer = get_user_by( 'id', $request['user_id'] );

		if ( ! $post || 'plugin' !== $post->post_type ) {
			return new WP_Error( 'plugin_not_found', 'Plugin not found', [ 'status' => 404 ] );
		}

		if ( 'new' !== $post->post_status ) {
			return new WP_Error( 'invalid_status', 'Plugin is not in "new" status', [ 'status' => 400 ] );
		}

		if ( ! $reviewer || ! user_can( $reviewer, 'plugin_review' ) ) {
			return new WP_Error( 'invalid_reviewer', 'The given user cannot review plugins', [ 'status' => 400 ] );
		}

		/*
		 * Act as the reviewer for the remainder of the request, so that the reviewer
		 * assignment and the audit log entries are attributed to them.
		 */
		wp_set_current_user( $reviewer->ID );

		// Assign the reviewer first, so that a failure here leaves the plugin untouched.
		$assigned_reviewer = (int) get_post_meta( $post->ID, 'assigned_reviewer', true );

		if ( $assigned_reviewer !== $reviewer->ID && ! Reviewer::set_reviewer( $post->ID, $reviewer->ID ) ) {
			return new WP_Error( 'reviewer_not_assigned', 'Failed to assign reviewer', [ 'status' => 500 ] );
		}

		// Change status to pending.
		$update_result = wp_update_post(
			[
				'ID'          => $post->ID,
				'post_status' => 'pending',
			],
			true
		);

		if ( is_wp_error( $update_result ) ) {
			$update_result->add_data( [ 'status' => 500 ] );
			return $update_result;
		}

		if ( 0 === $update_result ) {
			return new WP_Error( 'plugin_status_not_updated', 'Failed to update plugin status', [ 'status' => 500 ] );
		}

		return true;
	}
}
