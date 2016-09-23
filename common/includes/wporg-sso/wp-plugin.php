<?php
/**
 * WordPress-specific WPORG SSO: redirects all WP login and registration screens to our SSO ones.
 *
 * @uses WPOrg_SSO (class-wporg-sso.php)
 * @author stephdau
 */
if ( ! class_exists( 'WPOrg_SSO' ) ) {
	require_once __DIR__ . '/class-wporg-sso.php';
}

if ( class_exists( 'WPOrg_SSO' ) && ! class_exists( 'WP_WPOrg_SSO' ) ) {
	class WP_WPOrg_SSO extends WPOrg_SSO {
		/**
		 * List of valid paths on login.wordpress.org
		 * @var array
		 */
		public $valid_sso_paths = array(
			'root'         => '/',
			'checkemail'   => '/checkemail',
			'loggedout'    => '/loggedout',
			'lostpassword' => '/lostpassword',
			'oauth'        => '/oauth',
		);

		/**
		 * Constructor: add our action(s)/filter(s)
		 */
		public function __construct() {
			parent::__construct();

			if ( $this->has_host() ) {
				add_action( 'init', array( &$this, 'redirect_all_login_or_signup_to_sso' ) );
				// De-hooking the password change notification, too high volume on wp.org, for no admin value.
				remove_action( 'after_password_reset', 'wp_password_change_notification' );
			}
		}

		/**
		 * Redirect all attempts to get to a WP login or signup to the SSO ones, or to a safe redirect location.

		 * @example add_action( 'init', array( &$wporg_sso, 'redirect_all_wp_login_or_signup_to_sso' ) );
		 *
		 * @note Also handles accesses to lost password forms, since wp-login too.
		 */
		public function redirect_all_login_or_signup_to_sso() {
			if ( ! $this->_is_valid_targeted_domain( $this->host ) ) {
				// Not in list of targeted domains, not interested, bail out
				return;
			}

			// Extend paths which are only available for logged in users.
			if ( is_user_logged_in() ) {
				$this->valid_sso_paths['logout'] = '/logout';
			}

			$redirect_req = $this->_get_safer_redirect_to();

			// Add our host to the list of allowed ones.
			add_filter( 'allowed_redirect_hosts', array( &$this, 'add_allowed_redirect_host' ) );

			// Replace the lost password URL by our own
			add_filter( 'lostpassword_url', array( &$this, 'lostpassword_url' ), 10, 2 );

			if ( preg_match( '!/wp-signup\.php$!', $this->script ) ) {
				// If we're on any WP signup screen, redirect to the SSO host one,respecting the user's redirect_to request
				$this->_safe_redirect( add_query_arg( 'redirect_to', urlencode( $redirect_req ), $this->sso_signup_url ) );

			} elseif ( self::SSO_HOST !== $this->host ) {
				// If we're not on the SSO host
				if ( preg_match( '!/wp-login\.php$!', $this->script ) ) {
					// If on a WP login screen...
					$redirect_to_sso_login = $this->sso_login_url;

					// Pass thru the requested action, loggedout, if any
					if ( ! empty( $_GET ) ) {
						$redirect_to_sso_login = add_query_arg( $_GET, $redirect_to_sso_login );
					}

					// Pay extra attention to the post-process redirect_to
					$redirect_to_sso_login = add_query_arg( 'redirect_to', urlencode( $redirect_req ), $redirect_to_sso_login );

					// And actually redirect to the SSO login
					$this->_safe_redirect( $redirect_to_sso_login );

				} else {
					// Otherwise, filter the login_url to point to the SSO
					add_action( 'login_url', array( &$this, 'login_url' ), 10, 2 );
				}

			} else if ( self::SSO_HOST === $this->host ) {
				// If on the SSO host
				if ( ! preg_match( '!/wp-login\.php$!', $this->script ) ) {
					// ... but not on its login screen.
					if ( preg_match( '!^(' . implode( '|', $this->valid_sso_paths ) . ')([/?]{1,2}.*)?$!', $_SERVER['REQUEST_URI'] ) ) {
						// If we're on the path of interest

						// Add a custom filter others can apply (theme, etc).
						add_filter( 'is_valid_wporg_sso_path' , '__return_true' );

						if ( preg_match( '!^/(\?.*)?$!', $_SERVER['REQUEST_URI'] ) ) {
							// If at host root (/)
							if ( ! empty( $_GET['action'] ) ) {
								// If there's an action, it's really meant for wp-login.php, redirect
								$get = $_GET;
								if ( in_array( $get['action'], array( 'logout', 'loggedout' ) ) ) {
									// But make sure to show our custom screen when needed
									$get['redirect_to'] = $this->_get_safer_redirect_to();
								}
								$this->_safe_redirect( add_query_arg( $get, $this->sso_login_url . '/wp-login.php' ) );
								return;
							} else {
								// Else let the theme render, or redirect if logged in
								if ( is_user_logged_in() ) {
									$this->_redirect_to_profile();
								} else {
									if ( empty( $_GET['screen'] ) ) {
										add_filter( 'login_form_defaults', array( &$this, 'login_form_defaults' ) );
									}
								}
								return;
							}
						} else if ( is_user_logged_in() ) {
							if ( preg_match( '!^' . $this->valid_sso_paths['logout'] . '/?$!', $_SERVER['REQUEST_URI'] ) ) {
								// No redirect, ask the user if they really want to log out.
								return;
							} else {
								// Otherwise, redirect to the login screen.
								$this->_redirect_to_profile();
							}
						}
					} elseif ( is_user_logged_in() ) {
						// Logged in catch all, before last fallback
						$this->_redirect_to_profile();
					} else {
						// Otherwise, redirect to the login screen.
						$this->_safe_redirect( $this->sso_login_url );
					}
				} else {
					// if on login screen, filter network_site_url to make sure our forms go to the SSO host, not wordpress.org
					add_action( 'network_site_url', array( &$this, 'login_network_site_url' ), 10, 3 );
				}
			}
		}

		/**
		 * Modifies the network_site_url on login.wordpress.org's login screen to make sure all forms and links
		 * go to the SSO host, not wordpress.org
		 *
		 * @param string $url
		 * @param string $path
		 * @param string $scheme
		 * @return string
		 *
		 * @example add_action( 'network_site_url', array( &$this, 'login_network_site_url' ), 10, 3 );
		 */
		public function login_network_site_url( $url, $path, $scheme ) {
			if ( self::SSO_HOST === $this->host && preg_match( '!/wp-login\.php$!', $this->script ) ) {
				$url = preg_replace( '!^(https?://)[^/]+(/.+)$!' , '\1'.self::SSO_HOST.'\2', $url );
			}

			return $url;
		}


		/**
		 * Filters the defaults captions and options for the login form
		 *
		 * @param array $defaults
		 * @return array
		 */
		public function login_form_defaults( $defaults ) {
			$defaults['label_remember'] = __( 'Remember me', 'wporg-sso' );
			$defaults['label_log_in']   = __( 'Log in', 'wporg-sso' );
			if ( ! empty( $_GET['redirect_to'] ) ) {
				$defaults['redirect'] = $_GET['redirect_to']; // always ultimately checked for safety at redir time
			}
			return $defaults;
		}

		/**
		 * Filters the default lost password URL and returns our custom one instead.
		 *
		 * @param string $lostpassword_url
		 * @param string $redirect
		 */
		public function lostpassword_url( $lostpassword_url, $redirect ) {
			return home_url( $this->valid_sso_paths['lostpassword'] . '/?redirect_to=' . $redirect );
		}

		/**
		 * Redirects the user to her/his (support) profile.
		 */
		protected function _redirect_to_profile() {
			if ( ! is_user_logged_in() ) {
				return;
			}

			if ( ! empty( $_GET['redirect_to'] ) ) {
				$this->_safe_redirect( $this->_get_safer_redirect_to() );
			} else {
				$this->_safe_redirect( 'https://profiles.wordpress.org/' . wp_get_current_user()->user_nicename );
			}
		}
	}

	new WP_WPOrg_SSO();
}
