<?php
/**
 * API to perform actions as the current user.
 * Current supported features include
 * - Add Theme favorite
 * - Remove Theme favorite
 *
 * NOTE: Cache clearing - We can't clear the cached data for the browse=favorited&user=??? cache, it's cached for 10min.
 *
 */

// Load WordPress, pretend we're the Theme Directory in order to avoid having to switch sites after loading.
$_SERVER['HTTP_HOST'] = 'wordpress.org';
$_SERVER['REQUEST_URI'] = '/themes/';

require dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init.php';

function api_send_json( $data ) {
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

	// Allow cross-domain calls from *.wordpress.org
	if ( $origin && preg_match( '!^https?://([^.]+\.)?wordpress\.org/?$!i', $origin ) ) {
		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Access-Control-Allow-Credentials: true' ); // Allow cookies to be used.
	}

	/*
	 * The JSONP callback name doesn't change any state; the actions below
	 * verify a nonce.
	 * phpcs:ignore WordPress.Security.NonceVerification.Recommended
	 */
	$callback = isset( $_GET['callback'] )
		? preg_replace( '/[^a-z0-9_]/i', '', sanitize_text_field( wp_unslash( $_GET['callback'] ) ) )
		: false;

	$json = wp_json_encode( $data );

	if ( $callback ) {
		header( 'Content-Type:application/javascript; charset=' . get_option( 'blog_charset' ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated callback, JSON payload.
		echo "$callback( $json );";
	} else {
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload served as application/json.
		echo $json;
	}
	die();
}

if ( ! is_user_logged_in() ) {
	api_send_json( array(
		'error' => 'not_logged_in'
	) );
}

$requested_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

switch ( $requested_action ) {
	case 'add-favorite':
	case 'remove-favorite':
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( ! isset( $_REQUEST['theme'] ) || ! wp_verify_nonce( $nonce, 'modify-theme-favorite' ) ) {
			api_send_json( array(
				'error' => 'bad_request'
			) );
		}

		$theme_slug = sanitize_key( wp_unslash( $_REQUEST['theme'] ) );

		if ( 'add-favorite' == $requested_action ) {
			$result = wporg_themes_add_favorite( $theme_slug );
		} else {
			$result = wporg_themes_remove_favorite( $theme_slug );
		}

		if ( is_wp_error( $result ) ) {
			api_send_json( array(
				'error' => $result->get_error_code(),
			) );
		}
		api_send_json( array(
			'success' => true
		) );
		break;

	default:
		api_send_json( array(
			'error' => 'action_not_implemented'
		) );
		break;
}