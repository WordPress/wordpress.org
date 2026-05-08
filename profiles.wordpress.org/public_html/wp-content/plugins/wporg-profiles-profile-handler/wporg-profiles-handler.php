<?php
namespace WordPressdotorg\Profiles;
use Exception, WP_Error;
/*
 * Plugin Name: WordPress.org Profiles Profile Update Handler
 * Plugin URI: http://wordpress.org
 * License: GPL2
 * Version: 1.0
 * Description: Allows other WordPress.org systems to update BuddyPress xProfile data.
*/

defined( 'ABSPATH' ) or die();

class Profile_Update_Handler {

	/**
	 * @var Profile_Update_Handler|null
	 */
	protected static $instance = null;

	/**
	 * Returns the shared instance, constructing it on first call.
	 *
	 * Used by both the bootstrap at the bottom of this file and by the
	 * CLI queue processor to avoid duplicate hook registration.
	 */
	public static function get_instance() {
		return self::$instance ??= new self();
	}

	public function __construct() {
		add_action( 'wp_ajax_nopriv_wporg_update_profile', [ $this, 'ajax_handle' ] );
	}

	/**
	 * AJAX wrapper for handle().
	 *
	 * Validates the request, delegates to handle(), and dies with the result.
	 */
	public function ajax_handle() {
		try {
			do_action( 'wporg_profiles_before_handle_update_profile' );

			if ( true !== apply_filters( 'wporg_is_valid_update_profile_request', false ) ) {
				status_header( 400 );
				die( '-1 Not a valid request' );
			}

			$result = $this->handle( wp_unslash( $_POST ) );

			if ( is_wp_error( $result ) ) {
				$status = $result->get_error_data()['status'] ?? 500;
				status_header( $status );
				trigger_error( $result->get_error_message(), E_USER_WARNING );
				die( '-1 ' . $result->get_error_message() );
			}

			die( '1' );
		} catch ( Exception $exception ) {
			status_header( 500 );
			trigger_error( $exception->getMessage(), E_USER_WARNING );
			die( '-1 ' . $exception->getMessage() );
		}
	}

	/**
	 * Primary handler for profile update requests.
	 *
	 * @param array $data {
	 *     @type int   $user   The user ID to update.
	 *     @type array $fields Associative array of field name => value pairs.
	 * }
	 * @return true|WP_Error
	 */
	public function handle( array $data ) {
		// Disable requirement that user have a display_name set
		remove_filter( 'bp_activity_before_save', 'bporg_activity_requires_display_name' );

		$_user = $data['user'] ?? '';
		$user  = get_user_by( 'id', $_user );
		if ( ! $user || ! $_user || $user->ID != $_user ) {
			return new WP_Error( 'invalid_user', 'Invalid user specified.', [ 'status' => 400 ] );
		}

		// Pre-validate all fields are valid.
		$fields = $data['fields'] ?? [];
		foreach ( $fields as $field => $value ) {
			if ( ! xprofile_get_field_id_from_name( $field ) ) {
				return new WP_Error( 'invalid_field', "'{$field}' xProfile field could not be found.", [ 'status' => 400 ] );
			}
		}

		// Perform the profile updates, ignoring whether it succeeds.
		foreach ( $fields as $field => $value ) {
			xprofile_set_field_data( $field, $user->ID, $value );
		}

		return true;
	}

}
Profile_Update_Handler::get_instance();
