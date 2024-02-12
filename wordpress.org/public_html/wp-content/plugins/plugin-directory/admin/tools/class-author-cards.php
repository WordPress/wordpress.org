<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Tools;

use WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Author_Card;
use const WordPressdotorg\Plugin_Directory\PLUGIN_FILE;

/**
 * All functionality related to Author_Cards Tool.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Tools
 */
class Author_Cards {

	/**
	 * Fetch the instance of the Author_Cards class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new self();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
		add_action( 'admin_page_access_denied', array( $this, 'admin_page_access_denied' ) );
	}

	/**
	 * Add the Author Cards tool to the Tools menu.
	 */
	public function add_to_menu() {
		$hook = add_submenu_page(
			'plugin-tools',
			__( 'Author Cards', 'wporg-plugins' ),
			__( 'Author Cards', 'wporg-plugins' ),
			'plugin_review',
			'authorcards',
			array( $this, 'show_form' )
		);

		add_action( "admin_print_styles-{$hook}", array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Redirect the old location.
	 */
	public function admin_page_access_denied() {
		global $pagenow, $plugin_page;
		if (
			isset( $pagenow, $plugin_page ) &&
			'tools.php' === $pagenow &&
			'authorcards' === $plugin_page
		) {
			wp_safe_redirect( admin_url( "admin.php?page={$plugin_page}" ) );
			exit;
		}
	}

	/**
	 * Enqueue JS and CSS assets needed for any wp-admin screens.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'plugin-admin-post-css', plugins_url( 'css/edit-form.css', PLUGIN_FILE ), array( 'edit' ), 5 );
	}

	/**
	 * Display the Author Cards tool.
	 */
	public function show_form() {
		if ( ! current_user_can( 'plugin_review' ) ) {
			return;
		}

		$usernames = ! empty( $_REQUEST['users'] ) ? $_REQUEST['users'] : '';

		echo '<div class="wrap author-cards">';
		echo '<h1>' . __( 'Author Cards', 'wporg-plugins' ) . '</h1>';

		echo '<p>' . __( 'This is a tool to display an author card for one or more specified users.', 'wporg-plugins' ) . '</p>';

		echo '<form method="GET">';
		echo '<table class="form-table"><tbody><tr>';
		echo '<th scope="row"><label for="users">' . __( 'Users', 'wporg-plugins' ) . '</label></th><td>';
		echo '<input name="page" type="hidden" value="' . esc_attr( $_REQUEST['page'] ) . '">';
		echo '<input name="users" type="text" id="users" value="' . esc_attr( $usernames ) . '" class="regular-text">';
		echo '<p>' . __( 'Comma-separated list of user slugs, logins, and/or email addresses.', 'wporg-plugins' ) . '</p>';
		echo '</td></tr></tbody></table>';
		echo '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="' . esc_attr__( 'Submit', 'wporg-plugins' ) . '"></p>';
		echo '</form>';

		if ( $usernames ) {
			echo '<h2>' . __( 'Results', 'wporg-plugins' ) . '</h2>';

			echo '<div class="main">';

			// Array to store usernames that have been processed to ensure no
			// duplicates are displayed
			$processed_usernames = array();

			$usernames = explode( ',', $usernames );

			// Iterate through usernames
			foreach ( $usernames as $username ) {
				$username = trim( $username );

				if ( false !== strpos( $username, '@' ) ) {
					$user = get_user_by( 'email', $username );
				} else {
					$user = get_user_by( 'slug', $username );

					if ( ! $user ) {
						$user = get_user_by( 'login', $username );
					}
				}

				// Output author card
				if ( $user ) {
					if ( ! in_array( $user->user_nicename, $processed_usernames ) ) {
						$processed_usernames[] = $user->user_nicename;
						Author_Card::display( $user->ID );
					}
				} else {
					if ( ! in_array( $username, $processed_usernames ) ) {
						$processed_usernames[] = $username;
						echo '<div class="profile"><p class="profile-personal">';
						echo '<img class="avatar" src="https://gravatar.com/avatar/?d=mystery"><span class="profile-details"><strong>';
						echo esc_html( $username );
						echo '</strong></span></p>';
						echo '<p><em>' . __( 'No user found with this slug, login, or email address.', 'wporg-plugins' ) . '</em></p>';
						echo '</div>';
					}
				}
			}
		}

		echo '</div>';
	}

}
