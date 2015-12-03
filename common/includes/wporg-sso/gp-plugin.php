<?php
/**
 * WPORG SSO: filters gp_url_login() to return the equivalent SSO URL instead, removes the login route.
 * 
 * @uses WPOrg_SSO (class-wporg-sso.php) 
 * @author stephdau
 */
if ( class_exists( 'GP_Plugin' ) && ! class_exists( 'GP_WPOrg_SSO' ) ) {
	class GP_WPOrg_SSO extends GP_Plugin {
		var $sso_obj;
		
		function __construct() {
			parent::__construct();
			
			// Load SSO lib
			$this->instantiate_sso();
			
			if ( $this->sso_obj->has_host() ) {
				// Actions
				$this->add_action( 'init' );
				// Filters
				$this->add_filter( 'gp_url', array( 'args' => 3 ) );
			}
		}
		
		/**
		 * Instantiates a WPOrg_SSO (SSO lib) obj as self::sso_obj
		 */
		function instantiate_sso() {
			if ( empty( $this->sso_obj ) ) {
				if ( ! class_exists( 'WPOrg_SSO' ) ) {
					require_once __DIR__ . '/class-wporg-sso.php';
				}
				$this->sso_obj = new WPOrg_SSO();
			}
		}
		
		/**
		 * Init action: remove the /login route, login URL filtered to SSO's in self::gp_url()
		 */
		function init() {
			GP::$router->remove( '/login' );
		}
		
		
		/**
		 * Filter gp_url to return an equivalent login URL on the SSO instead of GP.
		 * 
		 * @param string $url
		 * @param string $path
		 * @param string $args
		 * @return string Login URL to SSO, with redirect_to back.
		 */
		function gp_url( $url, $path, $args ) {
			if ( preg_match( '/^\/login(\?.+)?$/', $url ) ) {
				// If the URL is to the login route.
				if ( ! empty( $args['redirect_to'] ) ) {
					// If there's already a redirect to request, pass it on to the SSO login.
					$url = $this->sso_obj->login_url( null, $args['redirect_to'] );
				} else {
					// Oherwise, make the current page the post-login target.
					$url = $this->sso_obj->login_url( gp_url_current() );
				}
			}
			
			return $url;
		}
	}
	
	GP::$plugins->wporg_sso = new GP_WPOrg_SSO();
}
