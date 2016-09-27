<?php

namespace WordPressdotorg\Forums;

class Users {

	public function __construct() {
		// If the user has a custom title, use that instead of the forum role.
		add_filter( 'bbp_get_user_display_role', array( $this, 'display_role' ), 10, 2 );

		// Add a Custom Title input to user's profile.
		add_action( 'bbp_user_edit_after_name', array( $this, 'add_custom_title_input' ) );

		// Save Custom Title input value.
		add_action( 'personal_options_update', array( $this, 'save_custom_title' ), 10, 2 );
		add_action( 'edit_user_profile_update', array( $this, 'save_custom_title' ), 10, 2 );

		// Custom user contact methods.
		add_filter( 'user_contactmethods', array( $this, 'custom_contact_methods' ) );
	}

	/**
	 * If the user has a custom title, use that instead of the forum role.
	 *
	 * @param string  $role    The user's forum role.
	 * @param int     $user_id The user ID.
	 * @return string The user's custom forum title, or their forum role.
	 */
	public function display_role( $role, $user_id ) {
		$title = get_user_option( 'title', $user_id );
		if ( ! empty( $title ) ) {
			return esc_html( $title );
		}

		return $role;
	}

	/**
	 * Custom contact methods
	 *
	 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/user_contactmethods
	 *
	 * @param array $user_contact_method Array of contact methods.
	 * @return array An array of contact methods.
	 */
	public function custom_contact_methods( $user_contact_method ) {
		/* Remove legacy user contact methods */
		unset( $user_contact_method['aim'] );
		unset( $user_contact_method['yim'] );
		unset( $user_contact_method['jabber'] );

		return $user_contact_method;
	}

	/**
	 * Add a Custom Title input (only available to moderators) to user's profile.
	 */
	public function add_custom_title_input() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}
		
		?>
		<div>
			<label for="title"><?php esc_html_e( 'Custom Title', 'wporg-forums' ); ?></label>
			<input type="text" name="title" id="title" value="<?php echo esc_attr( get_user_option( 'title', bbpress()->displayed_user->ID ) ); ?>" class="regular-text" />
		</div>
	<?php
	}

	/**
	 * Save Custom Title input value.
	 *
	 * @param int $user_id The user ID.
	 */
	public function save_custom_title( $user_id ) {
		if ( ! current_user_can( 'moderate' ) || ! isset( $_POST['title'] ) ) {
			return;
		}

		update_user_option( $user_id, 'title', sanitize_text_field( $_POST['title'] ) );
	}

}
