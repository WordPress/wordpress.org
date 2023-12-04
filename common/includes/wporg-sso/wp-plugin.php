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
			'root'            => '/',
			'robots'          => '/robots\.txt',
			'checkemail'      => '/checkemail',
			'loggedout'       => '/loggedout',
			'lostpassword'    => '/lostpassword(/(?P<user>[^/]+))?',
			'linkexpired'     => '/linkexpired(/(?P<reason>[^/]+)(/(?P<user>[^/]+))?)?',
			'oauth'           => '/oauth',

			// Primarily for logged in users.
			'updated-tos'     => '/updated-policies',
			'logout'          => '/logout',

			// Primarily for logged out users.
			'pending-profile' => '/register/create-profile(/(?P<profile_user>[^/]+)/(?P<profile_key>[^/]+))?',
			'pending-create'  => '/register/create(/(?P<confirm_user>[^/]+)/(?P<confirm_key>[^/]+))?',
			'register'        => '/register(/(?P<user>[^/]+))?',
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

				// Disable the 'admin' user with a nicer message. Must be before authenticate_block_check.
				add_filter( 'authenticate', array( $this, 'authenticate_admin_check' ), 4, 2 );

				add_filter( 'allow_password_reset', array( $this, 'disable_password_reset_for_blocked_users' ), 10, 2 );
				add_filter( 'authenticate', array( $this, 'authenticate_block_check' ), 5, 2 );
				add_filter( 'authenticate', array( $this, 'authenticate_block_nologin_accounts' ), 5, 2 );

				add_filter( 'password_change_email', array( $this, 'replace_admin_email_in_change_emails' ) );
				add_filter( 'email_change_email', array( $this, 'replace_admin_email_in_change_emails' ) );

				add_filter( 'pre_site_option_registration', array( $this, 'inherit_registration_option' ) );

				add_action( 'wp_login', array( $this, 'record_last_logged_in' ), 10, 2 );
				add_action( 'profile_update', array( $this, 'record_last_password_change' ), 10, 3 );

				add_action( 'login_form_logout', array( $this, 'login_form_logout' ) );

				add_filter( 'salt', array( $this, 'salt' ), 10, 2 );

				if ( ! $this->is_sso_host() ) {
					add_filter( 'login_url', [ $this, 'add_locale' ], 21 );
					add_filter( 'register_url', [ $this, 'add_locale' ], 21 );
					add_filter( 'lostpassword_url', [ $this, 'add_locale' ], 21 );

					add_filter( 'logout_redirect', [ $this, 'logout_redirect' ], 100 );
				} else {
					add_filter( 'login_redirect', [ $this, 'maybe_add_remote_login_bounce_to_post_login_url' ], 10, 3 );

					// Updated TOS interceptor.
					add_filter( 'send_auth_cookies', [ $this, 'maybe_block_auth_cookies' ], 100, 5 );
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
		 * Checks if the authenticated is "admin" and returns a nicer error message.
		 *
		 * @param WP_User|WP_Error|null $user WP_User or WP_Error object if a previous
		 *                                    callback failed authentication.
		 * @param string $user_login The user login attmpting to login.
		 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
		 */
		public function authenticate_admin_check( $user, $user_login ) {
			// Allow 'admin' to login in local environments.
			if ( 'local' === wp_get_environment_type() ) {
				return $user;
			}

			// If this isn't the admin user logging in, allow it.
			if ( 'admin' !== strtolower( $user_login ) ) {
				return $user;
			}

			// Someone is attempting to login as 'admin', throw an error.

			// Returning a WP_Error from an authenticate filter doesn't block auth, as a later hooked item can return truthful.
			remove_all_actions( 'authenticate' );

			return new WP_Error(
				'admin_wrong_place',
				sprintf(
					'<strong>%s</strong><br><br>%s',
					__( 'Are you in the right place?', 'wporg' ),
					__( 'This login form is for the WordPress.org website, rather than your personal WordPress site.', 'wporg' )
				)
			);
		}

		/**
		 * Checks if the authenticated user has been marked as blocked.
		 *
		 * @param WP_User|WP_Error|null $user WP_User or WP_Error object if a previous
		 *                                    callback failed authentication.
		 * @param string $user_login The user login attmpting to login.
		 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
		 */
		public function authenticate_block_check( $user, $user_login ) {

			$support_user = get_user_by( 'login', $user_login );
			if ( ! $support_user ) {
				$support_user = get_user_by( 'email', $user_login );
			}

			if ( $support_user && defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				$support_user->for_site( WPORG_SUPPORT_FORUMS_BLOGID );

				if (
					'BLOCKED' === substr( $support_user->user_pass, 0, 7 ) ||
					! empty( $support_user->allcaps['bbp_blocked'] )
				) {
					// Returning a WP_Error from an authenticate filter doesn't block auth, as a later hooked item can return truthful.
					// By removing all actions, we can catch both the bbp_blocked role for old users, and those whose passwords were broken via https://meta.trac.wordpress.org/changeset/10578
					remove_all_actions( 'authenticate' );

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
		 * Checks if the authenticated user cannot login via the web.
		 *
		 * @param WP_User|WP_Error|null $user WP_User or WP_Error object if a previous
		 *                                    callback failed authentication.
		 * @param string $user_login The user login attmpting to login.
		 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
		 */
		public function authenticate_block_nologin_accounts( $user, $user_login ) {
			global $nologin_accounts; // [ 'user1', 'user2' ]

			if ( ! empty( $nologin_accounts ) && in_array( $user_login, $nologin_accounts, true ) ) {
				// Returning a WP_Error from an authenticate filter doesn't block auth, as a later hooked item can return truthful.
				remove_all_actions( 'authenticate' );

				return new WP_Error( 'blocked_account', __( '<strong>ERROR</strong>: Your account has been disabled.', 'wporg' ) );
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

			$redirect_req = $this->_get_safer_redirect_to();

			// Add our host to the list of allowed ones.
			add_filter( 'allowed_redirect_hosts', array( $this, 'add_allowed_redirect_host' ) );

			// Replace some URLs by our own.
			add_filter( 'lostpassword_url', array( $this, 'lostpassword_url' ), 20, 2 );
			add_filter( 'site_url', array( $this, 'login_post_url' ), 20, 3 );
			add_filter( 'register_url', array( $this, 'register_url' ), 20 );

			// Maybe do a Remote SSO login/logout
			$this->_maybe_perform_remote_login();
			$this->_maybe_perform_remote_logout();

			if ( preg_match( '!/wp-signup\.php$!', $_SERVER['REQUEST_URI'] ) ) {
				// Note: wp-signup.php is not a physical file, and so it's matched on it's request uri.
				// If we're on any WP signup screen, redirect to the SSO host one,respecting the user's redirect_to request
				$this->_safe_redirect( add_query_arg( 'redirect_to', urlencode( $redirect_req ), $this->sso_signup_url ), 301 );

			} elseif ( ! $this->is_sso_host() ) {
				// If we're not on the SSO host
				if ( preg_match( '!/wp-login\.php$!', $this->script ) ) {
					// Don't redirect the 'confirmaction' wp-login handlers to login.wordpress.org.
					if ( isset( $_GET['action'] ) && empty( $_POST ) && 'confirmaction' == $_GET['action'] ) {
						return;
					}

					// Don't redirect the 'postpass' wp-login handlers to login.wordpress.org.
					if ( isset( $_GET['action'] ) && 'postpass' == $_GET['action'] ) {
						return;
					}

					// Allow logout to process. See self::login_form_logout()
					if ( isset( $_GET['action'] ) && empty( $_POST ) && 'logout' == $_GET['action'] ) {
						return;
					}

					// Don't redirect the 2fa 'revalidate_2fa' handler to login.wordpress.org when presented on WordPress.org
					if ( isset( $_REQUEST['action'] ) && 'revalidate_2fa' == $_REQUEST['action'] ) {
						return;
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

			} else if ( $this->is_sso_host() ) {
				// If on the SSO host

				if ( ! preg_match( '!/wp-login\.php$!', $this->script ) ) {

					// ... but not on its login screen.
					self::$matched_route        = false;
					self::$matched_route_regex  = false;
					self::$matched_route_params = array();
					foreach ( $this->valid_sso_paths as $route => $regex ) {
						// Process the URI with trailing `/.`, `/..`, `/. ` and `/.%20` normalised to `/`.
						$request_uri = preg_replace( '!/[ .]+$!', '/', urldecode( $_SERVER['REQUEST_URI'] ) );
						if ( preg_match( '!^' . $regex . '(?:[/?]{1,2}.*)?$!', $request_uri, $matches ) ) {
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
								$this->_safe_redirect( add_query_arg( $get, $this->sso_host_url . '/wp-login.php' ), 301 );
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
						}
					} elseif (
						(
							( is_admin() || wp_installing() ) &&
							( is_super_admin() || is_user_member_of_blog() )
						) ||
						0 === strpos( $_SERVER['REQUEST_URI'], '/wp-json' ) ||
						0 === strpos( $_SERVER['REQUEST_URI'], '/xmlrpc.php' )
					) {
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
			if ( $this->is_sso_host() && preg_match( '!/wp-login\.php$!', $this->script ) ) {
				$url = preg_replace( '!^(https?://)[^/]+(/.+)$!' , '\1' . $this->sso_host . '\2', $url );
			}

			return $url;
		}

		/**
		 * Filters the default login lost URL and returns our custom one instead.
		 *
		 * Does not alter the URL if `wp-login.php?action=postpass` is present.
		 * Attached to `site_url`.
		 *
		 * @param string      $url     The complete site URL including scheme and path.
		 * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
		 * @param string|null $scheme  Site URL context.
		 * @return string
		 */
		public function login_post_url( $url, $path, $scheme ) {
			// Only affect links that are relative to the login form.
			if ( 'login_post' != $scheme ) {
				return $url;
			}

			// Don't alter the post-password form.
			if ( str_contains( $url, 'wp-login.php?action=postpass' ) ) {
				return $url;
			}

			// Don't alter the revalidate 2fa form.
			if ( str_contains( $url, 'wp-login.php?action=revalidate_2fa' ) ) {
				return $url;
			}

			return $this->sso_host_url . '/wp-login.php';
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
		 * Change the logout destination to not land on wp-login on hosts other
		 * than login.wordpress.org.
		 *
		 * @param string $redirect The redirection location post-logout.
		 * @return string
		 */
		public function logout_redirect( $redirect ) {
			if (
				str_starts_with( $redirect, wp_login_url() ) &&
				! str_starts_with( $redirect, $this->sso_host_url )
			) {
				// Prefer the source page, as long as it wasn't a login/admin page.
				$redirect = wp_get_referer();
				if (
					str_starts_with( $redirect, wp_login_url() ) ||
					str_contains( $redirect, '/wp-admin/' )
				) {
					$redirect = home_url('/');
				}
			}

			return $redirect;
		}

		/**
		 * Override the logout process.
		 */
		public function login_form_logout() {
			check_admin_referer( 'log-out' );

			// The entire remote-logout process isn't needed on local environments.
			if ( 'local' === wp_get_environment_type() ) {
				$sso = $this;
				add_filter( 'logout_redirect', function() use( $sso ) {
					return $sso->sso_host_url . '/loggedout';
				} );
				return;
			}

			$user = wp_get_current_user();

			// Redirect back to the requested location.. the referer.. or failing that, the current sites front page after it's all done.
			$logout_redirect = ( wp_unslash( $_REQUEST['redirect_to'] ?? '' ) ?: wp_get_referer() ) ?: home_url( '/' );

			// Never to wp-admin.
			if ( str_contains( $logout_redirect, '/wp-admin/' ) ) {
				$logout_redirect = home_url( '/' );
			}

			$logout_redirect = apply_filters( 'logout_redirect', $logout_redirect, $logout_redirect, $user );

			$remote_logout_url = add_query_arg(
				array(
					'action'         => 'remote-logout',
					'redirect_to'    => urlencode( $logout_redirect ),
					'sso_logout'     => urlencode( $this->_generate_remote_token( $user ) )
				),
				$this->sso_host_url . '/wp-login.php'
			);

			$this->_safe_redirect( $remote_logout_url );
			exit;
		}

		/**
		 * Redirects the user back to where they came from (or w.org profile)
		 */
		public function redirect_to_source_or_profile() {
			$redirect = $this->_get_safer_redirect_to( false );

			// On local environments, just throw a logged in message instead.
			if ( 'local' === wp_get_environment_type() ) {
				wp_die(
					sprintf(
						"<h1>Logged in!</h1><p>You are currently logged in as <code>%s</code>.</p><p><a href='%s'>Would you like to logout?</a>",
						wp_get_current_user()->user_login,
						wp_logout_url()
					)
				);
				exit;
			}

			if ( $redirect ) {
				$this->_safe_redirect( $this->_maybe_add_remote_login_bounce( $redirect ) );
			} elseif ( is_user_logged_in() ) {
				$this->_safe_redirect( 'https://profiles.wordpress.org/' . wp_get_current_user()->user_nicename . '/' );
			} else {
				$this->_safe_redirect( 'https://wordpress.org/' );
			}
			exit;
		}

		protected function _redirect_to_source_or_profile() {
			return $this->redirect_to_source_or_profile();
		}

		/**
		 * Logs in a user on the current domain on a remote-login action.
		 */
		protected function _maybe_perform_remote_login() {
			if ( empty( $_GET['sso_token'] ) ) {
				return;
			}

			$remote_token = wp_unslash( $_GET['sso_token'] );
			$remote_token = $this->_validate_remote_token( $remote_token );

			// Log the user in if successful.
			if ( $remote_token && $remote_token['valid'] && $remote_token['user'] ) {
				wp_set_current_user( $remote_token['user']->ID );
				wp_set_auth_cookie( $remote_token['user']->ID, (bool) $remote_token['remember_me'], true, $remote_token['session_token'] );
			}

			if ( isset( $_GET['redirect_to'] ) ) {
				$redirect_to = wp_unslash( $_GET['redirect_to'] );
			} else {
				// Generate the current url based on the current hostname and request uri.
				$redirect_to = set_url_scheme( 'http://' . $this->host . ( $_SERVER['REQUEST_URI'] ?? '/' ) );

				// Remove the sso_token parameter, as we've now used it.
				$redirect_to = remove_query_arg( 'sso_token', $redirect_to );
			}

			// If we're going to land on a login page, go back home to avoid a potential endless loop.
			if ( str_contains( $redirect_to, '/wp-login.php' ) ) {
				$redirect_to = home_url( '/' );
			}

			$this->_safe_redirect( $redirect_to );
			exit;
		}

		public function maybe_add_remote_login_bounce_to_post_login_url( $redirect, $requested, $user ) {
			return $this->_maybe_add_remote_login_bounce( $redirect, $user );
		}

		protected function _maybe_add_remote_login_bounce( $redirect, $user = false ) {
			// Authentication failed, don't need to add the login nonces yet.
			if ( is_wp_error( $user ) ) {
				return $redirect;
			}

			if ( ! $user ) {
				$user = wp_get_current_user();
			}

			// If it's on a different _supported_ host, bounce through the remote-login.
			$redirect_host = parse_url( $redirect, PHP_URL_HOST );

			if ( $user && $this->_is_valid_targeted_domain( $redirect_host ) && ! preg_match( '!wordpress.org$!i', $redirect_host ) ) {
				$sso_token = $this->_generate_remote_token( $user );
				$redirect  = add_query_arg( 'sso_token', urlencode( $sso_token ), $redirect );
			}

			return $redirect;
		}

		/**
		 * Log out a user and destroy the session.
		 */
		protected function _maybe_perform_remote_logout() {
			if ( empty( $_GET['sso_logout'] ) || ! $this->is_sso_host() ) {
				return;
			}

			// Validate the logout token.
			$remote_token = wp_unslash( $_GET['sso_logout'] );
			$remote_token = $this->_validate_remote_token( $remote_token );
			if ( ! $remote_token || ! $remote_token['valid'] ) {
				return;
			}

			// Perform the logout. This will destroy the session, logging the user out of all sites.
			wp_logout();

			// Default to the logout confirmation screen, or back to the source site if possible.
			$redirect_to = $this->sso_host_url . '/loggedout';
			if ( ! empty( $_REQUEST['redirect_to'] ) ) {
				$requested_redirect_to = urldecode( wp_unslash( $_REQUEST['redirect_to'] ) );
				$redirect_to           = add_query_arg( 'redirect_to', urlencode( $requested_redirect_to ), $redirect_to );

				// If the requested redirect_to is valid, use it.
				if ( wp_validate_redirect( $requested_redirect_to ) ) {
					$redirect_to = $requested_redirect_to;
				}
			}

			$this->_safe_redirect( $redirect_to );
			exit;
		}

		/**
		 * Generates a remote token for login/logout.
		 *
		 * @param WP_User $user The User for the token.
		 * @return string The SSO token.
		 */
		protected function _generate_remote_token( $user ) {
			// Use a super-short timeout for the token. It's only going to be used once.
			$valid_until = time() + self::REMOTE_TOKEN_TIMEOUT;

			/*
			 * Fetch auth cookie parts to find out if the user has selected 'remember me'.
			 * This is only useful for login tokens, but causes no harm for loggout tokens.
			 */
			$auth_cookie_parts = wp_parse_auth_cookie( '', 'logged_in' );
			$remember_me       = ! empty( $_POST['rememberme'] ) || ( $auth_cookie_parts && $auth_cookie_parts['expiration'] >= ( time() + ( 2 * DAY_IN_SECONDS ) ) );
			$session_token     = wp_get_session_token();

			$hash        = $this->_generate_remote_token_hash( $user, $valid_until, $remember_me, $session_token );
			$sso_token   = $user->ID . '|' . $hash . '|' . $valid_until . '|' . $remember_me . '|' . $session_token;

			return $sso_token;
		}

		/**
		 * Generate a hash for remote-login for non-wordpress.org domains
		 */
		protected function _generate_remote_token_hash( $user, $valid_until, $remember_me = false, $session_token = '' ) {
			// re-use the same frag that Auth cookies use to invalidate sessions.
			$pass_frag = substr( $user->user_pass, 8, 4 );
			$key       = wp_hash( $user->user_login . '|' . $pass_frag . '|' . $valid_until . '|' . $session_token, 'wporg_sso' );
			$hash      = hash_hmac( 'sha256', $user->user_login . '|' . $valid_until . '|' . (int) $remember_me . '|' . $session_token, $key );

			return $hash;
		}

		/**
		 * Validates a SSO token is valid.
		 *
		 * @param string $token The raw token from the URL, wp_unslash() it please.
		 * @return array If the token was valid.
		 */
		protected function _validate_remote_token( $sso_token ) {
			if ( ! is_string( $sso_token ) || 4 !== substr_count( $sso_token, '|' ) ) {
				wp_die( 'Invalid token.' );
			}

			list( $user_id, $sso_hash, $valid_until, $remember_me, $session_token ) = explode( '|', $sso_token, 5 );

			$expiration_valid = (
				// +/- 5s on a 5s timeout.
				$valid_until >= ( time() - self::REMOTE_TOKEN_TIMEOUT ) &&
				$valid_until <= ( time() + ( self::REMOTE_TOKEN_TIMEOUT * 2 ) )
			);

			$valid_hash = false;
			$user       = get_user_by( 'id', $user_id );
			if ( $user ) {
				$valid_hash = hash_equals(
					$this->_generate_remote_token_hash( $user, $valid_until, $remember_me, $session_token ),
					$sso_hash
				);
			}

			// Validate that the remote login token is valid.
			$valid = ( $expiration_valid && $valid_hash );

			return compact(
				'valid',
				'user',
				'remember_me',
				'session_token'
			);
		}

		/**
		 * Add a custom salt, defined in the config.
		 */
		public function salt( $salt, $scheme ) {
			if ( 'wporg_sso' === $scheme && defined( 'WPORG_SSO_SALT' ) ) {
				$salt = WPORG_SSO_SALT;
			}

			return $salt;
		}

		/**
		 * Hooked to 'send_auth_cookies' to prevent sending of the Authentication cookies and redirect
		 * to the updated policy interstitial if required.
		 */
		public function maybe_block_auth_cookies( $send_cookies, $expire, $expiration, $user_id, $token = '' ) {
			if (
				$user_id &&
				! $this->has_agreed_to_tos( $user_id )
			) {
				$send_cookies = false;

				// Set a cookie so that we can keep the user in a auth'd (but not) state.
				$token_cookie = wp_generate_auth_cookie( $user_id, time() + HOUR_IN_SECONDS, 'tos_token', $token );
				$remember_me  = ( 0 !== $expire );

				setcookie( self::LOGIN_TOS_COOKIE, $token_cookie, time() + HOUR_IN_SECONDS, '/', $this->get_cookie_host(), true, true );
				setcookie( self::LOGIN_TOS_COOKIE . '_remember', $remember_me, time() + HOUR_IN_SECONDS, '/', $this->get_cookie_host(), true, true );

				// Redirect them to the interstitial.
				add_filter( 'login_redirect', [ $this, 'redirect_to_policy_update' ], 1000 );
			}

			return $send_cookies;
		}

		/**
		 * Redirects the user to the policy update interstitial.
		 */
		public function redirect_to_policy_update( $redirect ) {
			if ( false === strpos( $redirect, home_url( '/updated-policies' ) ) ) {
				$redirect = add_query_arg(
					'redirect_to',
					urlencode( $redirect ),
					home_url( '/updated-policies' )
				);
			}

			return $redirect;
		}

		/**
		 * Whether the given user_id has agreed to the current version of the TOS.
		 */
		protected function has_agreed_to_tos( $user_id ) {
			// Always return true as also broken for supes.
			return true;

			// TEMPORARY: Limit to supes.
			if ( ! is_super_admin( $user_id ) ) {
				return true;
			}

			$tos_agreed_to = get_user_meta( $user_id, self::TOS_USER_META_KEY, true ) ?: 0;

			return $tos_agreed_to >= TOS_REVISION;
		}

		/**
		 * Record the last date a user logged in.
		 * 
		 * Note: This might be before they agree to the new TOS, which is recorded separately.
		 */
		public function record_last_logged_in( $login, $user ) {
			update_user_meta( $user->ID, 'last_logged_in', gmdate( 'Y-m-d H:i:s' ) );
		}

		/**
		 * Record the last date a user changed their password.
		 */
		public function record_last_password_change( $user_id, $old_data, $new_data ) {
			if ( $old_data->user_pass !== $new_data['user_pass'] ) {
				update_user_meta( $user_id, 'last_password_change', gmdate( 'Y-m-d H:i:s' ) );
			}
		}

	}

	WP_WPOrg_SSO::get_instance();
}
