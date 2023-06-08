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
	// Allow cross-domain calls from *.wordpress.org
	if ( isset( $_SERVER['HTTP_ORIGIN'] ) && preg_match( '!^https?://([^.]+\.)?wordpress\.org/?$!i', $_SERVER['HTTP_ORIGIN'] ) ) {
		header( 'Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'] );
		header( 'Access-Control-Allow-Credentials: true' ); // Allow cookies to be used.
	}

	if ( isset( $_GET['callback'] ) ) {
		$callback = preg_replace( '/[^a-z0-9_]/i', '', $_GET['callback'] );
	} else {
		$callback = false;
	}

	$json = wp_json_encode( $data );

	if ( $callback ) {
		header( 'Content-Type:application/javascript; charset=' . get_option( 'blog_charset' ) );
		echo "$callback( $json );";
	} else {
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo $json;
	}
	die();
}

if ( ! is_user_logged_in() ) {
	api_send_json( array(
		'error' => 'not_logged_in'
	) );
}

switch ( $_REQUEST['action'] ) {
	case 'add-favorite':
	case 'remove-favorite':
		if ( ! isset( $_REQUEST['theme'] ) || ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'modify-theme-favorite' ) ) {
			api_send_json( array(
				'error' => 'bad_request'
			) );
		}

		$theme_slug = wp_unslash( $_REQUEST['theme'] );

		if ( 'add-favorite' == $_REQUEST['action'] ) {
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