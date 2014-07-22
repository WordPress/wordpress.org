<?php
/**
 * Plugin Name: WordCamp.org Global Login Endpoint
 * Plugin Description: Allows users to only log in on WordCamp.org, and not any of the other sites in the network.
 */

class WordCamp_Global_Login_Endpoint_Plugin {

	function __construct() {
		add_action( 'login_form_login', array( $this, 'login_form_login' ) );
		add_action( 'login_url', array( $this, 'login_url' ), 99, 2 );
		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
	}

	/**
	 * Don't render the login form, unless the current host is wordcamp.org
	 */
	function login_form_login() {

		$loggedout = !empty( $_REQUEST['loggedout'] ) ? $_REQUEST['loggedout'] : '';

		$current_network = get_current_site();
		$url = parse_url( admin_url() );
		if ( $url['host'] != $current_network->domain ) {
			$login_url = wp_login_url();
			if ( $loggedout )
				$login_url = add_query_arg( 'loggedout', $loggedout, $login_url );

			wp_safe_redirect( $login_url );
			die();
		}
	}

	/**
	 * Filter the wp_login_url function to always return the wordcamp.org login url.
	 */
	function login_url( $login_url, $redirect ) {

		$current_network = get_current_site();
		$url = parse_url( $login_url );
		if ( $url['host'] != $current_network->domain ) {
			$login_url = sprintf( 'https://%s/wp-login.php', $current_network->domain );

			if ( $redirect )
				$login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url );

			$login_url = esc_url_raw( $login_url );
		}

		return $login_url;
	}

	/**
	 * When redirecting with ?redirect_to= from wordcamp.org, allow the redirects to
	 * be other sites in the network. See wp_safe_redirect().
	 */
	function allowed_redirect_hosts( $hosts ) {

		$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
		$url = parse_url( $redirect_to );
		$current_network = get_current_site();
		$pattern = '/\.'. str_replace( '.', '\.', $current_network->domain ) .'$/i';

		if ( !empty( $url['host'] ) && preg_match( $pattern, $url['host'] ) )
			$hosts[] = $url['host'];

		return $hosts;
	}
}

$GLOBALS['wordcamp_gle'] = new WordCamp_Global_Login_Endpoint_Plugin;