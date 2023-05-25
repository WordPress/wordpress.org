<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Tools;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * All functionality related to the Upload Token Tool.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Tools
 */
class Upload_Token {

	const UPLOAD_PAGE_URL = '/developers/add/';
	const META_KEY        = '_plugin_upload_token';

	/**
	 * Fetch the instance of the Stats_Report class.
	 */
	public static function instance() {
		static $instance = null;

		return $instance ?: $instance = new self();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
	}

	/**
	 * Plugin Submission handler, delete the upload token for that user.
	 *
	 * Note: This is only hooked in the event that an upload token was validated as being available.
	 */
	public function plugin_upload( $plugin, $plugin_post ) {
		$this->delete_token( $plugin_post->post_author );

		Tools::audit_log( 'Plugin submitted using an Upload Token - Bypassed Trademark and Active Install checks.', $plugin_post->ID );
	}

	/**
	 * Adds the "Stats Report" link to the admin menu under "Tools".
	 */
	public function add_to_menu() {
		add_submenu_page(
			'plugin-tools',
			__( 'Upload Token', 'wporg-plugins' ),
			__( 'Upload Token', 'wporg-plugins' ),
			'plugin_review',
			'upload-token',
			array( $this, 'render' )
		);
	}

	/**
	 * Create a one-time-use upload token.
	 *
	 * @param int $user_id    User ID.
	 * @param int $expiration Optional. Expiration time in seconds. Default 1 week.
	 * @return string Token.
	 */
	public function create_token( $user_id, $expiration = 0 ) {
		$token      = wp_generate_password( 64, false, false );
		$hash       = wp_hash_password( $token );
		$expiration = $expiration ?: time() + WEEK_IN_SECONDS;

		update_user_meta( $user_id, self::META_KEY, compact( 'hash', 'expiration' ) );

		return $token;
	}

	/**
	 * Validates a token is valid.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_for_user( $user_id, $token ) {
		$user_token = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! $user_id || ! $token || ! $user_token || $user_token['expiration'] < time() ) {
			return false;
		}

		if ( ! wp_check_password( $token, $user_token['hash'] ) ) {
			return false;
		}

		add_action( 'plugin_upload', array( $this, 'plugin_upload' ), 10, 2 );
	
		return true;
	}

	/**
	 * Deletes a token.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function delete_token( $user_id ) {
		return delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Renders the create-new-upload-token tool.
	 */
	public function render() {
		if ( ! current_user_can( 'plugin_review' ) ) {
			return;
		}

		$username   = wp_unslash( $_REQUEST['user'] ?? '' );
		$expiration = wp_unslash( $_REQUEST['expiration'] ?? '' );
		if ( ! $expiration ) {
			$expiration = gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS );
		}

		echo '<div class="wrap author-cards">';
		echo '<h1>' . __( 'Upload Token', 'wporg-plugins' ) . '</h1>';
		echo '<p>' . __( 'This tool allows the generation of a one-time-use token to allow a plugin author to upload a plugin bypassing certain checks.', 'wporg-plugins' ) . '</p>';
		echo '<ol>';
		echo '<li>' . __( 'Trademarked terms', 'wporg-plugins' ) . '</li>';
		echo '<li>' . __( 'Active Installs', 'wporg-plugins' ) . '</li>';
		echo '<li>' . __( 'Plugin Check', 'wporg-plugins' ) . '</li>';
		echo '</ol>';

		echo '<form method="post">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="users">' . __( 'User', 'wporg-plugins' ) . '</label></th><td>';
		echo '<input name="user" type="text" id="user" value="' . esc_attr( $username ) . '" class="regular-text">';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="expiration">' . __( 'Expiration', 'wporg-plugins' ) . '</label></th><td>';
		echo '<input name="expiration" type="datetime-local" id="expiration" value="' . esc_attr( $expiration ) . '" class="regular-text">';
		echo '</td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr__( 'Submit', 'wporg-plugins' ) . '"></p>';
		echo '</form>';

		if ( ! $username ) {
			return;
		}

		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $username );
		}
		if ( ! $user ) {
			$user = get_user_by( 'slug', $username );
		}

		if ( ! $user ) {
			printf(
				'<div class="notice inline notice-error"><p>%s</p></div>',
				__( 'User not found.', 'wporg-plugins' )
			);
			return;
		}

		$user_token = get_user_meta( $user->ID, '_plugin_upload_token', true );
		if ( $user_token && $user_token['expiration'] > time() ) {
			printf(
				'<div class="notice inline notice-error"><p>%s</p></div>',
				__( 'User already had a valid token, replacing it.', 'wporg-plugins' )
			);
		}

		$token = $this->create_token( $user->ID, strtotime( $expiration ) );

		printf(
			'<div class="notice inline notice-success"><p>%s</p></div>',
			sprintf(
				__( 'Token created. Please provide the author with the following URL: <a href="%1$s">%1$s</a>', 'wporg-plugins' ),
				esc_url(
					add_query_arg(
						'upload_token',
						urlencode( $token ),
						home_url( self::UPLOAD_PAGE_URL )
					)
				)
			)
		);
	}
}
