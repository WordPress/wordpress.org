<?php
if ( ! class_exists( 'WPOrg_SSO' ) ) {
	/**
	 * Single Sign-On (SSO) handling for WordPress/bbPress instances on wordpress.org.
	 *
	 * @author stephdau
	 */
	class WPOrg_SSO {

		const SUPPORT_EMAIL = 'forum-password-resets@wordpress.org';

		const LOGIN_TOS_COOKIE  = 'wporg_tos_login';
		const TOS_USER_META_KEY = 'tos_revision';

		/**
		 * The time SSO tokens are valid. These are used for remote login/logout on
		 * non-wordpress.org domains.
		 *
		 * @var int
		 */
		const REMOTE_TOKEN_TIMEOUT = 5;

		const VALID_HOSTS = [
			'wordpress.org',
			'bbpress.org',
			'buddypress.org',
			'wordcamp.org'
		];

		public $sso_host       = 'login.wordpress.org';
		public $sso_host_url   = '';
		public $sso_login_url  = '';
		public $sso_signup_url = '';

		public $host   = '';
		public $script = '';

		private static $instance = null;

		/**
		 * Constructor, instantiate common properties
		 */
		public function __construct() {
			// On local installations, the SSO host is always the current sites domain and scheme.
			if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
				$this->sso_host     = parse_url( home_url(), PHP_URL_HOST );
				$this->sso_host_url = untrailingslashit( home_url() ); // Respect scheme, domain, and port.

				// If a port is specified, include that in the hostname.
				if ( parse_url( home_url(), PHP_URL_PORT ) ) {
					$this->sso_host .= ':' . parse_url( home_url(), PHP_URL_PORT );
				}
			} else {
				$this->sso_host_url = 'https://' . $this->sso_host;
			}

			$this->sso_login_url  = $this->sso_host_url . '/';
			$this->sso_signup_url = $this->sso_host_url . '/register';

			if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
				$this->host   = $_SERVER['HTTP_HOST'];
				$this->script = $_SERVER['SCRIPT_NAME'];
			}
		}

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				$class = get_called_class();
				self::$instance = new $class;
			}

			return self::$instance;
		}

		/**
		 * Return the hostname to use in cookie headers.
		 *
		 * Cookies do not respect the port in the hostname, and are hostname-specific unless the `Port` parameter is set.
		 */
		public function get_cookie_host() {
			list( $cookie_host ) = explode( ':', $this->sso_host );

			return $cookie_host;
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
			if ( $this->is_sso_host() ) {
				// If on the SSO host, add the requesting source (eg: make.wordpress.org), if within our bounds
				$url  = parse_url( $this->_get_safer_redirect_to() );
				$host = ( ! $url || ! isset( $url['host'] ) ) ? null : $url['host'];
			} else {
				// If not on the SSO host, add login.wordpress.org, to be safe
				$host = $this->sso_host;
			}

			// If we got a host by now, it's a safe wordpress.org-based one, add it to the list of allowed redirects
			if ( ! empty( $host ) && ! in_array( $host, $hosts ) ) {
				$hosts[] = $host;
			}

			// Return list of allowed hosts
			return $hosts;
		}

		/**
		 * Returns the SSO login URL, with redirect_to as requested, if deemed valid.
		 *
		 * @param string $login_url
		 * @param string $redirect_to When used with the WP login_url filter, the redirect_to is passed as a 2nd arg instead.
		 * @return string
		 *
		 * @example Use through add_action( 'login_url', array( $wporg_sso, 'login_url' ), 10, 2 );
		 */
		public function login_url( $login_url = '', $redirect_to = '' ) {
			$login_url = $this->sso_login_url;

			if ( ! preg_match( '!wordpress\.org$!', $this->host ) ) {
				$login_url = add_query_arg( 'from', $this->host, $login_url );
			}

			// Always include the redirect_to if not set, to avoid cross-origin redirect issues.
			if ( empty( $redirect_to ) ) {
				$redirect_to = 'https://' . $this->host . $_SERVER['REQUEST_URI'];
			}

			if ( ! empty( $redirect_to ) && $this->_is_valid_targeted_domain( $redirect_to ) ) {
				$redirect_to = preg_replace( '/\/wp-(login|signup)\.php\??.*$/', '/', $redirect_to );
				$redirect_to = preg_replace( '/\/login(\.php|\/)?$/', '/', $redirect_to ); // Thanks to wp_redirect_admin_locations()
				$login_url   = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
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
		 * Whether the current host is the SSO host.
		 *
		 * @return bool True if current host is the SSO host, false if not.
		 */
		public function is_sso_host() {
			return $this->sso_host === $this->host;
		}

		/**
		 * Get a safe redirect URL (ie: a wordpress.org-based one) from $_REQUEST['redirect_to'] or a safe alternative.
		 *
		 * @return string Safe redirect URL from $_REQUEST['redirect_to']
		 */
		protected function _get_safer_redirect_to( $default = 'https://wordpress.org/' ) {
			// Setup a default redirect to URL, with a safe version to only change if validation succeeds below.
			$redirect_to = ! empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'logout', 'loggedout' ) ) ? '/loggedout/' : $default;

			if ( ! empty( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) {
				// User is requesting a further redirect afterward, let's make sure it's a legit target.
				$redirect_to_requested = str_replace( ' ', '%20', $_REQUEST['redirect_to'] ); // Encode spaces.
				$redirect_to_requested = function_exists( 'wp_sanitize_redirect' ) ? wp_sanitize_redirect( $redirect_to_requested ) : $redirect_to_requested;
				if ( $this->_is_valid_targeted_domain( $redirect_to_requested ) ) {
					$redirect_to = $redirect_to_requested;
				}
			} else if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				// We didn't get a redirect_to, but we got a referrer, use that if a valid target.
				$redirect_to_referrer = $_SERVER['HTTP_REFERER'];
				if ( $this->_is_valid_targeted_domain( $redirect_to_referrer ) && $this->sso_host != parse_url( $redirect_to_referrer, PHP_URL_HOST ) ) {
					$redirect_to = $redirect_to_referrer;
				}
			} elseif ( ! $this->is_sso_host() ) {
				// Otherwise, attempt to guess the parent dir of where they came from and validate that.
				$redirect_to_source_parent = preg_replace( '/\/[^\/]+\.php\??.*$/', '/', "https://{$this->host}{$_SERVER['REQUEST_URI']}" );
				if ( $this->_is_valid_targeted_domain( $redirect_to_source_parent ) ) {
					$redirect_to = $redirect_to_source_parent;
				}
			}

			return $redirect_to;
		}

		/**
		 * Tests if the passed host/domain, or URL, is part of the WordPress.org network.
		 *
		 * @param string $host A domain, hostname, or URL
		 * @return boolean True is ok, false if not
		 */
		protected function _is_valid_targeted_domain( $host ) {
			if ( empty( $host ) || ! is_string( $host ) ) {
				return false;
			}

			if ( strstr( $host, '/' ) ) {
				$host = parse_url( $host, PHP_URL_HOST );
			}

			if ( $host === $this->sso_host ) {
				return true;
			}

			$host = $this->_get_targetted_host( $host );

			return in_array( $host, self::VALID_HOSTS, true );
		}

		/**
		 * Determine the targetted hostname for a given hostname.
		 *
		 * This returns 'wordpress.org' in the case of 'login.wordpress.org'.
		 * This does NOT validate the hostname is valid for a redirect.
		 *
		 * @param string $host The hostname to process.
		 * @return string The hostname, maybe top-level, maybe not.
		 */
		protected function _get_targetted_host( $host ) {
			if ( in_array( $host, self::VALID_HOSTS, true ) ) {
				return $host;
			}

			// If not a top-level domain, shrink it down and try again.
			$top_level_host = implode( '.', array_slice( explode( '.', $host ), -2 ) );
			if ( in_array( $top_level_host, self::VALID_HOSTS, true ) ) {
				$host = $top_level_host;
			}

			return $host;
		}

		/**
		 * Validates if target URL is within our bounds, then redirects to it if so, or to WP.org homepage (returns if headers already sent).
		 *
		 * @note: using our own over wp_safe_redirect(), etc, because not all targeted platforms (WP/BB/GP/etc) implement an equivalent, we run early, etc.
		 *
		 * @param string $to     Destination URL
		 * @param int    $status HTTP redirect status, defaults to 302
		 */
		protected function _safe_redirect( $to, $status = 302 ) {

			// When available, sanitize the redirect prior to redirecting.
			// This isn't strictly needed, but prevents harmless invalid inputs being passed through to the Location header.
			if ( function_exists( 'wp_sanitize_redirect' ) ) {
				$to = wp_sanitize_redirect( $to );
			}

			// This function MUST be passed a full URI, a relative or root-relative URI is not valid.
			if ( ! $this->_is_valid_targeted_domain( $to ) ) {
				$to = $this->_get_safer_redirect_to();
			}

			if ( function_exists( 'apply_filters' ) ) {
				$to = apply_filters( 'wp_redirect', $to, $status );
			}

			/*
			 * Collapse leading multiple slashes at the start of the path in the URL.
			 * This can cause problems with setting cookies when the redirect is to
			 * a SSO login destination such as `http://example.org//////wp-admin`.
			 */
			$to = preg_replace( '!^(https?://[^/]+)/{2,}!', '$1/', $to );

			// In the event headers have been sent already, output a HTML redirect.
			if ( headers_sent() ) {
				if ( function_exists( 'esc_url' ) ) {
					$to = esc_url( $to );
				} else {
					// This is not a replacement for esc_url().
					$to = htmlspecialchars( $to, ENT_QUOTES | ENT_SUBSTITUTE );
				}

				printf(
					'<meta http-equiv="refresh" content="1;url=%1$s" />' . 
					'<a href="%1$s">%1$s</a>',
					$to
				);
				exit;
			}

			header(
				'Location: ' . $to,
				true,
				preg_match( '/^30(1|2)$/', $status ) ? $status : 302
			);
			exit;
		}
	}
}
