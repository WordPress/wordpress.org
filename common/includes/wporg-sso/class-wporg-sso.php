<?php
if ( ! class_exists( 'WPOrg_SSO' ) ) {
	/**
	 * Single Sign-On (SSO) handling for WordPress/bbPress/GlotPress instances under *.wordpress.org.
	 *
	 * @author stephdau
	 */
	class WPOrg_SSO {
		const SSO_HOST = 'login.wordpress.org';

		const SUPPORT_EMAIL = 'forum-password-resets@wordpress.org';

		public $sso_host_url;
		public $sso_login_url;
		public $sso_signup_url;

		public $host;
		public $script;

		/**
		 * Constructor, instantiate common properties
		 */
		public function __construct() {
			$this->sso_host_url   = 'https://' . self::SSO_HOST;
			$this->sso_login_url  = $this->sso_host_url . '/';
			$this->sso_signup_url = 'https://wordpress.org/support/register.php'; // For now

			if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
				$this->host   = $_SERVER['HTTP_HOST'];
				$this->script = $_SERVER['SCRIPT_NAME'];
			}
		}

		/**
		 * Checks if the requested redirect_to URL is part of the wordpress.org empire, adds it as an redirect host if so.
		 *
		 * @param array $hosts Currently allowed hosts
		 * @return array $hosts Edited lists of allowed hosts
		 *
		 * @example add_filter( 'allowed_redirect_hosts', array( &$this, 'add_allowed_redirect_host' ) );
		*/
		public function add_allowed_redirect_host( $hosts ) {
			if ( self::SSO_HOST === $this->host ) {
				// If on the SSO host, add the requesting source (eg: make.wordpress.org), if within our bounds
				$url  = parse_url( $this->_get_safer_redirect_to() );
				$host = ( empty( $url ) ) ? null : $url['host'];
			} else {
				// If not on the SSO host, add login.wordpress.org, to be safe
				$host = self::SSO_HOST;
			}

			// If we got a host by now, it's a safe wordpress.org-based one, add it to the list of allowed redirects
			if ( ! empty( $host ) && ! in_array( $host, $hosts ) ){
				$hosts[] = $host;
			}

			// Return list of allowed hosts
			return $hosts;
		}

		/**
		 * Returns the SSO login URL, with redirect_to as requested, if deemed valid.
		 *
		 * @param string $redirect_to
		 * @param string $filter_redirect_to When used with the WP login_url filter, the redirect_to is passed as a 2nd arg instead.
		 * @return string
		 *
		 * @example Use directly, or through add_action( 'login_url', array( &$wporg_sso, 'login_url' ), 10, 2 );
		 */
		public function login_url( $redirect_to = '', $filter_redirect_to = '' ) {
			$login_url = $this->sso_login_url;
			// If 2nd arg is passed, then redirect_to is it.
			if ( ! empty( $filter_redirect_to ) ) {
				$redirect_to = $filter_redirect_to;
			}
			if ( ! empty( $redirect_to ) && $this->_is_valid_targeted_domain( $redirect_to ) ) {
				$redirect_to = preg_replace( '/\/wp-(login|signup)\.php\??.*$/', '/', $redirect_to );
				$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
			}
			return $login_url;

		}


		/**
		 * Tests if the current process has $_SERVER['HTTP_HOST'] or not (EG: cron'd processes do not).
		 *
		 * @return boolean
		 */
		public function has_host() {
			return ( ! empty( $this->host ) );
		}

		/**
		 * Get a safe redirect URL (ie: a wordpress.org-based one) from $_REQUEST['redirect_to'] or a safe alternative.
		 *
		 * @return string Safe redirect URL from $_REQUEST['redirect_to']
		 */
		protected function _get_safer_redirect_to() {
			// Setup a default redirect to URL, with a safe version to only change if validation succeeds below.
			$redirect_to = ! empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'logout', 'loggedout' ) ) ? '/loggedout/' : 'https://wordpress.org/';

			if ( ! empty( $_REQUEST['redirect_to'] ) ) {
				// User is requesting a further redirect afterward, let's make sure it's a legit target.
				$redirect_to_requested = str_replace( ' ', '%20', $_REQUEST['redirect_to'] ); // Encode spaces.
				$redirect_to_requested = function_exists( 'wp_sanitize_redirect' ) ? wp_sanitize_redirect( $redirect_to_requested ) : $redirect_to_requested;
				if ( $this->_is_valid_targeted_domain( $redirect_to_requested ) ) {
					$redirect_to = $redirect_to_requested;
				}
			} else if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				// We didn't get a redirect_to, but we got a referrer, use that if a valid target.
				$redirect_to_referrer = $_SERVER['HTTP_REFERER'];
				if ( $this->_is_valid_targeted_domain( $redirect_to_referrer ) ) {
					$redirect_to = $redirect_to_referrer;
				}
			} else{
				// Otherwise, attempt to guess the parent dir of where they came from and validate that.
				$redirect_to_source_parent = preg_replace( '/\/[^\/]+\.php\??.*$/', '/', "https://{$this->host}{$_SERVER['REQUEST_URI']}" );
				if ( $this->_is_valid_targeted_domain( $redirect_to_source_parent ) ) {
					$redirect_to = $redirect_to_source_parent;
				}
			}

			return $redirect_to;
		}

		/**
		 * Tests if the passed host/domain, or URL, is part of the WordPress.org domain.
		 *
		 * @param unknown $string A domain, hostname, or URL
		 * @return boolean True is ok, false if not
		 */
		protected function _is_valid_targeted_domain( $string ) {
			if ( empty( $string ) || ! is_string( $string ) ) {
				$string = '';
			}

			if ( strstr( $string , '/' ) ) {
				$url = parse_url( $string );
				$host = ( ! empty( $url['host'] ) ) ? $url['host'] : '';
			} else {
				$host = $string;
			}

			if ( ! empty( $host ) && strstr( $host , '.' ) ) {
				return ( preg_match( '/^(.+\.)?wordpress\.org$/', $host ) ) ? true : false;
			}

			return false;
		}

		/**
		 * Validates if target URL is within our bounds, then redirects to it if so, or to WP.org homepage (returns if headers already sent).
		 *
		 * @param string $to Destination URL
		 * @param number $status HTTP redirect status, defaults to 302
		 *
		 * @note: using our own over wp_safe_redirect(), etc, because not all targeted platforms (WP/BB/GP/etc) implement an equivalent, we run early, etc.
		 */
		protected function _safe_redirect( $to, $status = 302 ) {
			if ( headers_sent() ) {
				return;
			}

			if ( ! $this->_is_valid_targeted_domain( $to ) ) {
				$to = $this->_get_safer_redirect_to();
			}

			header(
				'Location: ' . $to,
				true,
				preg_match( '/^30(1|2)$/', $status ) ? $status : 302
			);

			die();
		}
	}
}
