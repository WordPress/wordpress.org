<?php
/**
 * bbPress-specific WPORG SSO: redirects all BB login and registration screens to our SSO ones.
 * 
 * @uses WPOrg_SSO (class-wporg-sso.php)
 * @author stephdau
 */
if ( ! class_exists( 'WPOrg_SSO' ) ) {
	require_once __DIR__ . '/class-wporg-sso.php';
}

if ( class_exists( 'WPOrg_SSO' ) && ! class_exists( 'BB_WPOrg_SSO' ) ) {
	class BB_WPOrg_SSO extends WPOrg_SSO {
		/**
		 * Constructor: add our action(s)/filter(s)
		 */
		public function __construct() {
			parent::__construct();

			add_action( 'bb_init', array( &$this, 'redirect_all_login_or_signup_to_sso' ) );
		}
		
		/**
		 * Redirect all attempts to get to a BB login or signup to the SSO ones, or to a safe redirect location.
		 * 
		 * @example add_action( 'bb_init', array( &$wporg_sso, 'redirect_all_bb_login_or_signup_to_sso' ) );
		 */
		function redirect_all_login_or_signup_to_sso() {
			if ( ! $this->_is_valid_targeted_domain( $this->host ) ) {
				// Not in list of targeted domains, not interested, bail out.
				return;
			} else if ( preg_match( '/\/register\.php$/', $this->script ) ) {
				// Redirect registration request to the one we want to standardize on.
				if ( "https://{$this->host}{$this->script}" !== $this->sso_signup_url ) {
					$this->_safe_redirect( $this->sso_signup_url );
				}
			} else if ( preg_match( '/\/bb-login\.php$/', $this->script ) ) {
				$redirect_to_sso_login = $this->sso_login_url;
					
				// Pass thru the requested action, loggedout, if any
				if ( ! empty( $_GET ) ) {
					$redirect_to_sso_login = add_query_arg( $_GET, $redirect_to_sso_login );
				}
					
				// Pay extra attention to the post-process redirect_to
				$redirect_to_sso_login = add_query_arg( 'redirect_to', $this->_get_safer_redirect_to(), $redirect_to_sso_login );
				
				// Redirect to SSO login, trying to pass on a decent redirect_to request.
				$this->_safe_redirect( $redirect_to_sso_login );
			}
		}
	}
	
	new BB_WPOrg_SSO();
}