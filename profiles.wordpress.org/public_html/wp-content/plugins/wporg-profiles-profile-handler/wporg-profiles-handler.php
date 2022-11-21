<?php
namespace WordPressdotorg\Profiles;
use Exception;
/*
 * Plugin Name: WordPress.org Profiles Profile Update Handler
 * Plugin URI: http://wordpress.org
 * License: GPL2
 * Version: 1.0
 * Description: Allows other WordPress.org systems to update BuddyPress xProfile data.
*/

defined( 'ABSPATH' ) or die();

class Profile_Update_Handler {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_wporg_update_profile', [ $this, 'handle' ] );
	}

	/**
	 * Primary AJAX handler.
	 */
	public function handle() {
		try {
			/*
			 * This is useful for testing on your sandbox.
			 *
			 * e.g., Edit `$_POST['user_id']` so that activity goes to a test account rather than a real one.
			 */
			do_action( 'wporg_profiles_before_handle_update_profile' );

			// Return error if not a valid activity request.
			if ( true !== apply_filters( 'wporg_is_valid_update_profile_request', false ) ) {
				throw new Exception( '-1 Not a valid request' );
			}

			// Disable requirement that user have a display_name set
			remove_filter( 'bp_activity_before_save', 'bporg_activity_requires_display_name' );

			$_user = wp_unslash( $_POST['user'] ?? '' );
			$user  = get_user_by( 'id', $_user );
			if ( ! $user || ! $_user || $user->ID != $_user ) {
				throw new Exception( 'Invalid user specified.' );
			}

			// Pre-validate all fields are valid.
			$_fields = wp_unslash( $_POST['fields'] ?? [] );
			foreach ( $_fields as $field => $value ) {
				if ( ! xprofile_get_field_id_from_name( $field ) ) {
					throw new Exception( "'{$field}' xProfile field could not be found." );
				}
			}

			// Perform the profile updates, ignoring whether it succeeds.
			foreach ( $_fields as $field => $value ) {
				xprofile_set_field_data( $field, $user->ID, $value );
			}

			$response = '1';
		} catch ( Exception $exception ) {
			trigger_error( $exception->getMessage(), E_USER_WARNING );

			$response = $exception->getMessage();
		}

		die( $response );
	}

}
new Profile_Update_Handler();
