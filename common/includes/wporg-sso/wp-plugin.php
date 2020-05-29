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
			'robots'       => '/robots\.txt',
			'checkemail'   => '/checkemail',
			'loggedout'    => '/loggedout',
			'lostpassword' => '/lostpassword(/(?P<user>[^/]+))?',
			'linkexpired'  => '/linkexpired(/(?P<reason>register|lostpassword)/(?P<user>[^/]+))?',
			'oauth'        => '/oauth',
		);

		/**
		 * Holds the route hit in `valid_sso_paths`
		 * @var bool|string
		 */
		static $matched_route = false;

		/**
		 * Holds the route regex hit in `valid_sso_paths`
		 * @var bool|string
		 */
		static $matched_route_regex = false;

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

				add_filter( 'pre_site_option_registration', array( $this, 'inherit_registration_option' ) );

				if ( ! $this->is_sso_host() ) {
					add_filter( 'login_url', [ $this, 'add_locale' ], 21 );
					add_filter( 'register_url', [ $this, 'add_locale' ], 21 );
					add_filter( 'lostpassword_url', [ $this, 'add_locale' ], 21 );
				} else {
					add_filter( 'login_redirect', [ $this, 'maybe_add_remote_login_bounce_to_post_login_url' ], 10, 3 );
				}
			}
		}

		/**
		 * Inherits the 'registration' option from the main network.
		 *
		 * @return string Current registration status.
		 */
		public function inherit_registration_option() {
			remove_filter( 'pre_site_option_registration', array( $this, 'inherit_registration_option' ) );
			$value = get_network_option( 1, 'registration', 'none' );
			add_filter( 'pre_site_option_registration', array( $this, 'inherit_registration_option' ) );
			return $value;
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
					return new WP_Error(
						'blocked_account',
						__( '<strong>ERROR</strong>: Your account has been disabled.', 'wporg' )  . '<br>' .
						sprintf(
							__( 'Please contact %s for more details.', 'wporg' ),
							'<a href="mailto:forum-password-resets@wordpress.org">forum-password-resets@wordpress.org</a>'
						)
					);
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

			// Extend registration paths only when registration is open.
			if ( 'user' === get_site_option( 'registration', 'none' ) ) {
				// New "pending" registration flow.
				$this->valid_sso_paths['pending-profile']  = '/register/create-profile(/(?P<profile_user>[^/]+)/(?P<profile_key>[^/]+))?';
				$this->valid_sso_paths['pending-create']   = '/register/create(/(?P<confirm_user>[^/]+)/(?P<confirm_key>[^/]+))?';

				// Primary registration route.
				$this->valid_sso_paths['register']         = '/register(/(?P<user>[^/]+))?';
			}

			$redirect_req = $this->_get_safer_redirect_to();

			// Add our host to the list of allowed ones.
			add_filter( 'allowed_redirect_hosts', array( $this, 'add_allowed_redirect_host' ) );

			// Replace some URLs by our own.
			add_filter( 'lostpassword_url', array( $this, 'lostpassword_url' ), 20, 2 );
			add_filter( 'site_url', array( $this, 'login_post_url' ), 20, 3 );
			add_filter( 'register_url', array( $this, 'register_url' ), 20 );

			if ( preg_match( '!/wp-signup\.php$!', $this->script ) ) {
				// If we're on any WP signup screen, redirect to the SSO host one,respecting the user's redirect_to request
				$this->_safe_redirect( add_query_arg( 'redirect_to', urlencode( $redirect_req ), $this->sso_signup_url ), 301 );

			} elseif ( ! $this->is_sso_host() ) {
				// If we're not on the SSO host
				if ( preg_match( '!/wp-login\.php$!', $this->script ) ) {
					// Don't redirect the 'confirmaction' wp-login handlers to login.wordpress.org.
					if ( isset( $_GET['action'] ) && empty( $_POST ) && 'confirmaction' == $_GET['action'] ) {
						return;
					}

					// Allow logout on non-dotorg hosts.
					if ( isset( $_GET['action'] ) && empty( $_POST ) && 'logout' == $_GET['action'] ) {
						if ( ! preg_match( '!wordpress\.org$!', $_SERVER['HTTP_HOST'] ) ) {
							return;
						}
					}

					// Remote SSO login?
					if ( isset( $_GET['action'] ) && 'remote-login' === $_GET['action'] && ! empty( $_GET['sso_token'] ) ) {
						$this->_maybe_perform_remote_login();
					}

					// If on a WP login screen...
					$redirect_to_sso_login = $this->sso_login_url;

					// Pass thru the requested action, loggedout, if any
					if ( ! empty( $_GET ) ) {
						$redirect_to_sso_login = add_query_arg( $_GET, $redirect_to_sso_login );
					}

					// Pay extra attention to the post-process redirect_to
					$redirect_to_sso_login = add_query_arg( 'redirect_to', urlencode( $redirect_req ), $redirect_to_sso_login );
					if ( ! preg_match( '!wordpress\.org$!', $this->host ) ) {
						$redirect_to_sso_login = add_query_arg( 'from', $this->host, $redirect_to_sso_login );
					}

					// And actually redirect to the SSO login
					$this->_safe_redirect( $redirect_to_sso_login, 301 );

				} else {
					// Otherwise, filter the login_url to point to the SSO
					add_filter( 'login_url', array( $this, 'login_url' ), 20, 2 );
				}

			} else if ( self::SSO_HOST === $this->host ) {
				// If on the SSO host
				if ( ! preg_match( '!/wp-login\.php$!', $this->script ) ) {
					// ... but not on its login screen.
					self::$matched_route        = false;
					self::$matched_route_regex  = false;
					self::$matched_route_params = array();
					foreach ( $this->valid_sso_paths as $route => $regex ) {
						if ( preg_match( '!^' . $regex . '(?:[/?]{1,2}.*)?$!', $_SERVER['REQUEST_URI'], $matches ) ) {
							self::$matched_route        = $route;
							self::$matched_route_regex  = $regex;
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
								$this->_safe_redirect( add_query_arg( $get, $this->sso_login_url . '/wp-login.php' ), 301 );
								return;
							} else {
								// Else let the theme render, or redirect if logged in
								if ( is_user_logged_in() ) {
									$this->_redirect_to_source_or_profile();
								} else {
									if ( empty( $_GET['screen'] ) ) {
										add_filter( 'login_form_defaults', array( $this, 'login_form_defaults' ) );
									}
								}
								return;
							}
						} else if ( is_user_logged_in() && 'logout' == self::$matched_route ) {
							// No redirect, ask the user if they really want to log out.
							return;
						} else if ( 'robots' === self::$matched_route ) {
							// No redirect, just display robots.
						} else if ( is_user_logged_in() ) {
							// Otherwise, redirect to the their profile.
							$this->_redirect_to_source_or_profile();
						}
					} elseif ( ( is_admin() && is_super_admin() ) || 0 === strpos( $_SERVER['REQUEST_URI'], '/wp-json' ) || 0 === strpos( $_SERVER['REQUEST_URI'], '/xmlrpc.php' ) ) {
						// Do nothing, allow access to wp-admin, wp-json and xmlrpc.php on login.wordpress.org
					} elseif ( is_user_logged_in() ) {
						// Logged in catch all, before last fallback
						$this->_redirect_to_source_or_profile();
					} else {
						// Otherwise, redirect to the login screen.
						$this->_safe_redirect( $this->sso_login_url, 301 );
					}
				} else {
					// if on login screen, filter network_site_url to make sure our forms go to the SSO host, not wordpress.org
					add_action( 'network_site_url', array( $this, 'login_network_site_url' ), 10, 3 );
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
		 * @example add_action( 'network_site_url', array( $this, 'login_network_site_url' ), 10, 3 );
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
			if ( ! empty( $_GET['redirect_to'] ) ) {
				$defaults['redirect'] = $_GET['redirect_to']; // always ultimately checked for safety at redir time
			} elseif ( $referer = wp_get_referer() ) {
				$_GET['redirect_to'] = $referer;
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
			$lostpassword_url = $this->sso_host_url . '/lostpassword';

			if ( ! empty( $redirect ) ) {
				$lostpassword_url = add_query_arg( 'redirect_to', $redirect, $lostpassword_url );
			}

			return $lostpassword_url;
		}

		/**
		 * Adds a locale parameter to the passed URL.
		 *
		 * @param string $url The URL.
		 * @return string
		 */
		public function add_locale( $url ) {
			return add_query_arg( 'locale', get_locale(), $url );
		}

		/**
		 * Redirects the user back to where they came from (or w.org profile)
		 */
		protected function _redirect_to_source_or_profile() {
			$redirect = $this->_get_safer_redirect_to();

			if ( $redirect ) {
				$this->_safe_redirect( $this->_maybe_add_remote_login_bounce( $redirect ) );
			} elseif ( is_user_logged_in() ) {
				$this->_safe_redirect( 'https://profiles.wordpress.org/' . wp_get_current_user()->user_nicename . '/' );
			} else {
				$this->_safe_redirect( 'https://wordpress.org/' );
			}
		}

		/**
		 * Logs in a user on the current domain on a remote-login action.
		 */
		protected function _maybe_perform_remote_login() {
			$remote_token = wp_unslash( $_GET['sso_token'] );
			if ( ! is_string( $remote_token ) || 3 !== substr_count( $remote_token, '|' ) ) {
				wp_die( 'Invalid token.' );
			}

			list( $user_id, $sso_hash, $valid_until, $remember_me ) = explode( '|', $remote_token, 4 );

			$remote_expiration_valid = (
				// +/- 5s on a 5s timeout.
				$valid_until >= ( time() - 5 ) &&
				$valid_until <= ( time() + 10 )
			);

			$valid_remote_hash = false;
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$valid_remote_hash = hash_equals( 
					$this->_generate_remote_login_hash( $user, $valid_until, $remember_me ),
					$sso_hash
				);
			}

			if ( $remote_expiration_valid && $valid_remote_hash ) {
				wp_set_current_user( (int) $user_id );
				wp_set_auth_cookie( (int) $user_id, (bool) $remember_me );

				if ( isset( $_GET['redirect_to'] ) ) {
					$this->_safe_redirect( wp_unslash( $_GET['redirect_to'] ) );
				} else {
					$this->_safe_redirect( home_url( '/' ) );
				}
				exit;
			}

			return false;
		}

		public function maybe_add_remote_login_bounce_to_post_login_url( $redirect, $requested, $user ) {
			return $this->_maybe_add_remote_login_bounce( $redirect, $user );
		}

		protected function _maybe_add_remote_login_bounce( $redirect, $user = false ) {
			if ( ! $user ) {
				$user = wp_get_current_user();
			}

			// If it's on a different _supported_ host, bounce through the remote-login.
			$redirect_host = parse_url( $redirect, PHP_URL_HOST );

			if ( $user && $this->_is_valid_targeted_domain( $redirect_host ) && ! preg_match( '!wordpress.org$!i', $redirect_host ) ) {

				// Fetch auth cookie parts to find out if the user has selected 'remember me'.
				$auth_cookie_parts = wp_parse_auth_cookie( '', 'secure_auth' );

				$valid_until = time() + 5; // Super short timeout.
				$remember_me = ! empty( $_POST['rememberme'] ) || ( $auth_cookie_parts && $auth_cookie_parts['expiration'] >= ( time() + ( 2 * DAY_IN_SECONDS ) ) );

				$hash        = $this->_generate_remote_login_hash( $user, $valid_until, $remember_me );
				$sso_token   = $user->ID . '|' . $hash . '|' . $valid_until . '|' . $remember_me;

				$redirect = add_query_arg(
					array(
						'action'      => 'remote-login',
						'sso_token'   => urlencode( $sso_token ),
						'redirect_to' => urlencode( $redirect ),
					),
					'https://' . $redirect_host . '/wp-login.php' // Assume that wp-login exists and is accessible
				);
			}

			return $redirect;
		}

		/**
		 * Generate a hash for remote-login for non-wordpress.org domains
		 */
		protected function _generate_remote_login_hash( $user, $valid_until, $remember_me = false ) {
			// re-use the same frag that Auth cookies use to invalidate sessions.
			$pass_frag = substr( $user->user_pass, 8, 4 );
			$key       = wp_hash( $user->user_login . '|' . $pass_frag . '|' . $valid_until );
			$hash      = hash_hmac( 'sha256', $user->user_login . '|' . $valid_until . '|' . (int) $remember_me, $key );

			return $hash;
		}
	}

	new WP_WPOrg_SSO();
}
