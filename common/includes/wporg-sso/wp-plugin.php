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
			'register-profile' => '/register/profile/(?P<profile_user>[^/]+)/(?P<profile_nonce>[^/]+)',
			'register-confirm' => '/register/confirm/(?P<confirm_user>[^/]+)/(?P<confirm_key>[^/]+)',
			'register'         => '/register',
		);

		/**
		 * Holds the route hit in `valid_sso_paths`
		 * @var bool|string
		 */
		static $matched_route = false;

		/**
		 * Holds any matched route params.
		 * @var array
		 */
		static $matched_route_params = array();

		/**
		 * Constructor: add our action(s)/filter(s)
		 */
		public function __construct() {
			parent::__construct();

			if ( $this->has_host() ) {
				add_action( 'init', array( $this, 'redirect_all_login_or_signup_to_sso' ) );
				// De-hooking the password change notification, too high volume on wp.org, for no admin value.
				remove_action( 'after_password_reset', 'wp_password_change_notification' );

				add_filter( 'allow_password_reset', array( $this, 'disable_password_reset_for_blocked_users' ), 10, 2 );
				add_filter( 'authenticate', array( $this, 'authenticate_block_check' ), 30 );

				add_filter( 'password_change_email', array( $this, 'replace_admin_email_in_change_emails' ) );
				add_filter( 'email_change_email', array( $this, 'replace_admin_email_in_change_emails' ) );
			}
		}

		/**
		 * Checks if the authenticated user has been marked as blocked.
		 *
		 * @param WP_User|WP_Error|null $user WP_User or WP_Error object if a previous
		 *                                    callback failed authentication.
		 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
		 */
		public function authenticate_block_check( $user ) {
			if ( $user instanceof WP_User && defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				$support_user = new WP_User( $user->ID, '', WPORG_SUPPORT_FORUMS_BLOGID );

				if ( ! empty( $support_user->allcaps['bbp_blocked'] ) ) {
					return new WP_Error( 'blocked_account', __( '<strong>ERROR</strong>: Your account has been disabled.', 'wporg-sso' ) );
				}
			}

			return $user;
		}

		/**
		 * Disables password reset for blocked users.
		 *
		 * @param bool $allow   Whether to allow the password to be reset.
		 * @param int  $user_id The ID of the user attempting to reset a password.
		 * @return bool True if user is blocked, false if not.
		 */
		public function disable_password_reset_for_blocked_users( $allow, $user_id ) {
			if ( ! $allow || ! defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				return $allow;
			}

			$user = new WP_User( $user_id, '', WPORG_SUPPORT_FORUMS_BLOGID );
			$is_blocked = ! empty( $user->allcaps['bbp_blocked'] );

			return ! $is_blocked;
		}

		/**
		 * Replaces the admin email placeholder with a support email
		 * to avoid using the site's admin email.
		 *
		 * @param array $email The email/password change email.
		 * @return array The email/password change email.
		 */
		public function replace_admin_email_in_change_emails( $email ) {
			$email['headers'] = "From: WordPress.org <donotreply@wordpress.org>\n";
			$email['message'] = str_replace( '###ADMIN_EMAIL###', self::SUPPORT_EMAIL, $email['message'] );
			return $email;
		}

		/**
		 * Redirect all attempts to get to a WP login or signup to the SSO ones, or to a safe redirect location.
		 *
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

			// Replace some URLs by our own.
			add_filter( 'lostpassword_url', array( &$this, 'lostpassword_url' ), 20, 2 );
			add_filter( 'site_url', array( $this, 'login_post_url' ), 20, 3 );
			add_filter( 'register_url', array( $this, 'register_url' ), 20 );

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
					add_filter( 'login_url', array( $this, 'login_url' ), 20, 2 );
				}

			} else if ( self::SSO_HOST === $this->host ) {
				// If on the SSO host
				if ( ! preg_match( '!/wp-login\.php$!', $this->script ) ) {
					// ... but not on its login screen.
					self::$matched_route = false;
					self::$matched_route_params = array();
					foreach ( $this->valid_sso_paths as $route => $regex ) {
						if ( preg_match( '!^' . $regex . '(?:[/?]{1,2}.*)?$!', $_SERVER['REQUEST_URI'], $matches ) ) {
							self::$matched_route = $route;
							self::$matched_route_params = $matches;
							break;
						}
					}

					// If we're on the path of interest
					if ( self::$matched_route ) {
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
					} elseif ( ( is_admin() && is_super_admin() ) || preg_match( '!^/wp-json(/?$|/.+)!i', $_SERVER['REQUEST_URI'] ) ) {
						// Do nothing, allow access to wp-admin and wp-json on login.wordpress.org
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
		 * Filters the default login lost URL and returns our custom one instead.
		 *
		 * @param string      $url     The complete site URL including scheme and path.
		 * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
		 * @param string|null $scheme  Site URL context.
		 * @return string
		 */
		public function login_post_url( $url, $path, $scheme ) {
			if ( 'login_post' === $scheme ) {
				return $this->sso_host_url . '/wp-login.php';
			}

			return $url;
		}

		/**
		 * Filters the default registration URL and returns our custom one instead.
		 *
		 * @return string
		 */
		public function register_url() {
			return $this->sso_signup_url;
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
			} elseif ( $referer = wp_get_referer() ) {
				$defaults['redirect'] = $referer;
			}
			return $defaults;
		}

		/**
		 * Filters the default lost password URL and returns our custom one instead.
		 *
		 * @param string $lostpassword_url The lost password page URL.
		 * @param string $redirect         The path to redirect to on login.
		 * @return string New lost password URL.
		 */
		public function lostpassword_url( $lostpassword_url, $redirect ) {
			$lostpassword_url = $this->sso_host_url . $this->valid_sso_paths['lostpassword'];

			if ( ! empty( $redirect ) ) {
				$lostpassword_url = add_query_arg( 'redirect_to', $redirect, $lostpassword_url );
			}

			return $lostpassword_url;
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
