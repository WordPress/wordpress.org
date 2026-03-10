<?php
/**
 * Plugin Name: Login Application Passwords
 * Description: Provides the application password authorization flow on wp-login.php.
 * Version:     1.0.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package login-application-passwords
 */

declare( strict_types = 1 );

require_once __DIR__ . '/clients/mcp/class-mcp-authorization.php';
add_action( 'login_init', array( MCP_Authorization::class, 'init' ) );

/**
 * Returns the registered applications, their names, and allowed callback domains.
 *
 * Each app_id maps to an array with 'name' and 'hosts'. The name is used
 * instead of any user-supplied app_name to prevent phishing. The hosts
 * array restricts which domains success_url and reject_url may point to.
 *
 * @return array<string, array{name: string, hosts: string[]}> Map of app_id UUID => app config.
 */
function wporg_get_allowed_apps(): array {
	/**
	 * Filters the registered applications allowed to use the application password authorization flow.
	 *
	 * Each entry must be keyed by a valid UUID (the app_id) and contain an array
	 * with 'name' (non-empty string) and optionally 'hosts' (array of allowed
	 * callback domains). If 'hosts' is omitted, it defaults to an empty array,
	 * meaning the app does not support callback URLs.
	 *
	 * Example:
	 *
	 *     add_filter( 'wporg_login_application_passwords_allowed_apps', function ( $apps ) {
	 *         $apps['c4c73a54-96d7-47b9-9bdc-1a66b9b04505'] = array(
	 *             'name'  => 'My Application',
	 *             'hosts' => array( 'example.com' ),
	 *         );
	 *         return $apps;
	 *     } );
	 *
	 * @param array<string, array{name: string, hosts?: string[]}> $apps Map of app_id UUID => app config.
	 */
	$apps = apply_filters( 'wporg_login_application_passwords_allowed_apps', array() );

	$validated = array();

	foreach ( $apps as $app_id => $config ) {
		$config = wp_parse_args( $config, array( 'hosts' => array() ) );

		if ( ! wp_is_uuid( $app_id ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf( 'App ID must be a valid UUID. Got: %s', esc_html( $app_id ) ),
				'1.0.0'
			);
			continue;
		}

		if ( ! is_array( $config ) || empty( $config['name'] ) || ! is_string( $config['name'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf( 'App "%s" must have a non-empty "name" string.', esc_html( $app_id ) ),
				'1.0.0'
			);
			continue;
		}

		if ( ! is_array( $config['hosts'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf( 'App "%s" "hosts" must be an array.', esc_html( $app_id ) ),
				'1.0.0'
			);
			continue;
		}

		$validated[ $app_id ] = $config;
	}

	return $validated;
}

/**
 * Handles the authorize_application action on wp-login.php.
 */
function wporg_handle_authorize_application_login(): void {
	if ( ! is_user_logged_in() ) {
		$authorize_url = add_query_arg(
			array(
				'action'      => 'authorize_application',
				'app_id'      => sanitize_text_field( wp_unslash( $_REQUEST['app_id'] ?? '' ) ),
				'success_url' => sanitize_url( wp_unslash( $_REQUEST['success_url'] ?? '' ) ),
				'reject_url'  => sanitize_url( wp_unslash( $_REQUEST['reject_url'] ?? '' ) ),
			),
			site_url( 'wp-login.php', 'login' )
		);

		wp_safe_redirect( wp_login_url( $authorize_url ) );
		exit;
	}

	$error        = null;
	$new_password = '';

	// Handle form submission.
	if ( isset( $_SERVER['REQUEST_METHOD'] )
		&& 'POST' === $_SERVER['REQUEST_METHOD']
		&& isset( $_POST['wp_action'] ) && 'authorize_application_password' === $_POST['wp_action']
	) {
		check_admin_referer( 'authorize_application_password' );

		$app_id      = sanitize_text_field( wp_unslash( $_POST['app_id'] ?? '' ) );
		$success_url = sanitize_url( wp_unslash( $_POST['success_url'] ?? '' ) );
		$reject_url  = sanitize_url( wp_unslash( $_POST['reject_url'] ?? '' ) );
		$redirect    = '';

		// Re-validate POST values (don't trust hidden form fields).
		$user         = wp_get_current_user();
		$allowed_apps = wporg_get_allowed_apps();
		$app_name     = $allowed_apps[ $app_id ]['name'] ?? '';
		$request      = compact( 'app_name', 'app_id', 'success_url', 'reject_url' );

		$is_valid = wporg_validate_authorize_app_request( $request, $user );
		if ( is_wp_error( $is_valid ) ) {
			wp_die(
				wp_kses_post( implode( ' ', $is_valid->get_error_messages() ) ),
				esc_html__( 'Cannot Authorize Application' )
			);
		}

		if ( isset( $_POST['reject'] ) ) {
			$redirect = $reject_url ?: admin_url(); // phpcs:ignore Universal.Operators.DisallowShortTernary
		} elseif ( isset( $_POST['approve'] ) ) {
			// Revoke any existing password for this app_id.
			if ( $app_id ) {
				$existing = WP_Application_Passwords::get_user_application_passwords( get_current_user_id() );
				foreach ( $existing as $item ) {
					if ( isset( $item['app_id'] ) && $item['app_id'] === $app_id ) {
						$deleted = WP_Application_Passwords::delete_application_password( get_current_user_id(), $item['uuid'] );
						if ( is_wp_error( $deleted ) ) {
							$error = $deleted;
							break;
						}
					}
				}
			}

			if ( ! is_wp_error( $error ) ) {
				$created = WP_Application_Passwords::create_new_application_password(
					get_current_user_id(),
					array(
						'name'   => $app_name,
						'app_id' => $app_id,
					)
				);

				if ( is_wp_error( $created ) ) {
					$error = $created;
				} else {
					list( $new_password ) = $created;

					if ( $success_url ) {
						$redirect = add_query_arg(
							array(
								'site_url'   => rawurlencode( 'https://wordpress.org' ),
								'user_login' => rawurlencode( wp_get_current_user()->user_login ),
								'password'   => rawurlencode( $new_password ),
							),
							$success_url
						);
					}
				}
			}
		}

		if ( $redirect ) {
			$allowed_hosts = $allowed_apps[ $app_id ]['hosts'] ?? array();
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $allowed_hosts ) {
					return array_merge( $hosts, $allowed_hosts );
				}
			);

			wp_safe_redirect( $redirect );
			exit;
		}
	}

	// Extract and validate request parameters.
	$app_id      = sanitize_text_field( wp_unslash( $_REQUEST['app_id'] ?? '' ) );
	$success_url = sanitize_url( wp_unslash( $_REQUEST['success_url'] ?? '' ) ) ?: null; // phpcs:ignore Universal.Operators.DisallowShortTernary

	if ( ! empty( $_REQUEST['reject_url'] ) ) {
		$reject_url = sanitize_url( wp_unslash( $_REQUEST['reject_url'] ) );
	} elseif ( $success_url ) {
		$reject_url = add_query_arg( 'success', 'false', $success_url );
	} else {
		$reject_url = null;
	}

	$user         = wp_get_current_user();
	$allowed_apps = wporg_get_allowed_apps();
	$app_name     = $allowed_apps[ $app_id ]['name'] ?? '';
	$request      = compact( 'app_name', 'app_id', 'success_url', 'reject_url' );

	$is_valid = wporg_validate_authorize_app_request( $request, $user );
	if ( is_wp_error( $is_valid ) ) {
		wp_die(
			wp_kses_post( implode( ' ', $is_valid->get_error_messages() ) ),
			esc_html__( 'Cannot Authorize Application' )
		);
	}

	if ( ! wp_is_application_passwords_available_for_user( $user ) ) {
		if ( wp_is_application_passwords_available() ) {
			$message = __( 'Application passwords are not available for your account. Please contact the site administrator for assistance.' );
		} else {
			$message = __( 'Application passwords are not available.' );
		}

		wp_die(
			esc_html( $message ),
			esc_html__( 'Cannot Authorize Application' ),
			array(
				'response'  => 501,
				'link_text' => esc_html__( 'Go Back' ),
				'link_url'  => esc_url( $reject_url ? add_query_arg( 'error', 'disabled', $reject_url ) : admin_url() ),
			)
		);
	}

	// Render the authorization form.
	$title    = __( 'Authorize Application' );
	$wp_error = new WP_Error();

	if ( is_wp_error( $error ) ) {
		$wp_error->add( 'authorize_error', $error->get_error_message() );
	}

	add_filter( 'login_site_html_link', '__return_empty_string' );

	login_header( $title, '', $wp_error );

	?>
	<form action="<?php echo esc_url( site_url( 'wp-login.php?action=authorize_application', 'login' ) ); ?>" method="post" class="authorize-application-form">
		<?php wp_nonce_field( 'authorize_application_password' ); ?>
		<input type="hidden" name="wp_action" value="authorize_application_password" />
		<input type="hidden" name="app_id" value="<?php echo esc_attr( $app_id ); ?>" />
		<input type="hidden" name="success_url" value="<?php echo $success_url ? esc_url( $success_url ) : ''; ?>" />
		<input type="hidden" name="reject_url" value="<?php echo $reject_url ? esc_url( $reject_url ) : ''; ?>" />

		<?php if ( $new_password ) : ?>
			<div class="authorize-application-password-display">
				<p>
					<label for="new-application-password-value">
						<?php
						printf(
							/* translators: %s: Application name. */
							esc_html__( 'Your new password for %s is:' ),
							'<strong>' . esc_html( $app_name ) . '</strong>'
						);
						?>
					</label>
				</p>
				<input id="new-application-password-value" type="text" class="input" readonly="readonly" value="<?php echo esc_attr( WP_Application_Passwords::chunk_password( $new_password ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Be sure to save this in a safe location. You will not be able to retrieve it.' ); ?></p>
			</div>
			<?php
			/** This action is documented in wp-admin/authorize-application.php */
			do_action( 'wp_authorize_application_password_form_approved_no_js', $new_password, $request, $user );
			?>
		<?php else : ?>
			<h2 class="authorize-application-heading"><?php esc_html_e( 'Authorize Application' ); ?></h2>

			<?php if ( $app_name ) : ?>
				<p class="authorize-application-details">
					<?php
					printf(
						/* translators: %s: Application name. */
						esc_html__( '%s would like to connect to your account. Only approve this if you trust this application.' ),
						'<strong>' . esc_html( $app_name ) . '</strong>'
					);
					?>
				</p>
			<?php else : ?>
				<p class="authorize-application-details"><?php esc_html_e( 'An application would like to connect to your account. Only approve this if you trust this application.' ); ?></p>
			<?php endif; ?>

			<?php
			if ( is_multisite() ) :
				$blogs       = get_blogs_of_user( $user->ID, true );
				$blogs_count = count( $blogs );

				if ( $blogs_count > 1 ) :
					?>
					<p class="authorize-application-details">
						<?php
						/* translators: 1: URL to My Sites, 2: Number of sites. */
						$message = _n(
							'This will grant access to <a href="%1$s">the %2$s site in this installation that you have permissions on</a>.',
							'This will grant access to <a href="%1$s">all %2$s sites in this installation that you have permissions on</a>.',
							$blogs_count
						);

						if ( is_super_admin() ) {
							/* translators: 1: URL to My Sites, 2: Number of sites. */
							$message = _n(
								'This will grant access to <a href="%1$s">the %2$s site on the network as you have Super Admin rights</a>.',
								'This will grant access to <a href="%1$s">all %2$s sites on the network as you have Super Admin rights</a>.',
								$blogs_count
							);
						}

						printf( wp_kses_post( $message ), esc_url( get_admin_url( array_key_first( $blogs ), 'my-sites.php' ) ), esc_html( number_format_i18n( $blogs_count ) ) );
						?>
					</p>
					<?php
				endif;
			endif;

			/** This action is documented in wp-admin/authorize-application.php */
			do_action( 'wp_authorize_application_password_form', $request, $user );
			?>

			<p class="submit authorize-application-submit">
				<input type="submit" name="approve" class="button button-primary button-large" value="<?php esc_attr_e( 'Approve' ); ?>" aria-describedby="auth-app-description" />
				<input type="submit" name="reject" class="button button-large" value="<?php esc_attr_e( 'Reject' ); ?>" aria-describedby="auth-app-description" />
			</p>

			<p class="description" id="auth-app-description">
				<?php
				if ( $success_url ) {
					$host = wp_parse_url( $success_url, PHP_URL_HOST );
					printf(
						/* translators: %s: The host the user is being redirected to. */
						esc_html__( 'You will be redirected to %s.' ),
						'<strong>' . esc_html( $host ) . '</strong>'
					);
				} else {
					esc_html_e( 'You will be given a password to manually enter into the application in question.' );
				}
				?>
			</p>
		<?php endif; ?>
	</form>
	<?php

	login_footer();
	exit;
}
add_action( 'login_form_authorize_application', 'wporg_handle_authorize_application_login' );

/**
 * Adds a contextual message on the login form when redirecting to authorize an application.
 *
 * @param WP_Error $errors      WP_Error object.
 * @param string   $redirect_to Redirect destination URL.
 *
 * @return WP_Error
 */
function wporg_authorize_application_login_message( WP_Error $errors, string $redirect_to ): WP_Error {
	if ( ! str_contains( $redirect_to, 'action=authorize_application' ) ) {
		return $errors;
	}

	$query_component = wp_parse_url( $redirect_to, PHP_URL_QUERY );
	$query           = array();

	if ( $query_component ) {
		parse_str( $query_component, $query );
	}

	$allowed_apps = wporg_get_allowed_apps();
	$app_id       = $query['app_id'] ?? '';
	$app_name     = $allowed_apps[ $app_id ]['name'] ?? '';

	if ( $app_name ) {
		$message = sprintf(
			/* translators: 1: Website name, 2: Application name. */
			esc_html__( 'Please log in to %1$s to authorize %2$s to connect to your account.' ),
			get_bloginfo( 'name', 'display' ),
			'<strong>' . esc_html( $app_name ) . '</strong>'
		);
	} else {
		$message = sprintf(
			/* translators: %s: Website name. */
			esc_html__( 'Please log in to %s to proceed with authorization.' ),
			get_bloginfo( 'name', 'display' )
		);
	}

	$errors->add( 'authorize_application', $message, 'message' );

	return $errors;
}
add_filter( 'wp_login_errors', 'wporg_authorize_application_login_message', 10, 2 );

/**
 * Enqueues the authorize_application stylesheet on the login page.
 */
function wporg_authorize_application_login_styles(): void {
	global $action;

	if ( 'authorize_application' !== $action ) {
		return;
	}

	wp_enqueue_style(
		'login-authorize-application',
		plugins_url( 'css/authorize-application.css', __FILE__ ),
		array(),
		filemtime( __DIR__ . '/css/authorize-application.css' )
	);
}
add_action( 'login_enqueue_scripts', 'wporg_authorize_application_login_styles' );

/**
 * Validates the authorize application request.
 *
 * Inlined from wp_is_authorize_application_password_request_valid() in
 * wp-admin/includes/user.php, which is not loaded in the login context.
 *
 * @param array   $request The request data.
 * @param WP_User $user    The user authorizing the application.
 *
 * @return true|WP_Error True if valid, WP_Error otherwise.
 */
function wporg_validate_authorize_app_request( array $request, WP_User $user ): bool|WP_Error {
	$error = new WP_Error();

	// Validate redirect URLs.
	$is_local = 'local' === wp_get_environment_type();

	foreach ( array( 'success_url', 'reject_url' ) as $key ) {
		if ( empty( $request[ $key ] ) ) {
			continue;
		}

		if ( ! wp_http_validate_url( $request[ $key ] ) ) {
			$error->add( 'invalid_redirect_url', __( 'The provided URL is not valid.' ) );
		} elseif ( 'https' !== wp_parse_url( $request[ $key ], PHP_URL_SCHEME ) && ! $is_local ) {
			$error->add( 'invalid_redirect_scheme', __( 'The URL must be served over a secure connection.' ) );
		}
	}

	// Validate app_id format.
	if ( empty( $request['app_id'] ) ) {
		$error->add( 'missing_app_id', __( 'An application ID is required.' ) );
	} elseif ( ! wp_is_uuid( $request['app_id'] ) ) {
		$error->add( 'invalid_app_id', __( 'The application ID must be a UUID.' ) );
	}

	// Validate app_id against the allowlist and check callback domains.
	$allowed_apps = wporg_get_allowed_apps();

	if ( ! empty( $request['app_id'] ) && wp_is_uuid( $request['app_id'] ) ) {
		if ( ! isset( $allowed_apps[ $request['app_id'] ] ) ) {
			$error->add( 'unauthorized_app', __( 'This application is not authorized.' ) );
		} else {
			$allowed_hosts = $allowed_apps[ $request['app_id'] ]['hosts'];

			if ( empty( $allowed_hosts ) ) {
				if ( ! empty( $request['success_url'] ) || ! empty( $request['reject_url'] ) ) {
					$error->add( 'redirect_not_allowed', __( 'This application does not support callback URLs.' ) );
				}
			} else {
				foreach ( array( 'success_url', 'reject_url' ) as $key ) {
					if ( empty( $request[ $key ] ) ) {
						continue;
					}

					$host = strtolower( wp_parse_url( $request[ $key ], PHP_URL_HOST ) );
					if ( ! $host || ! in_array( $host, array_map( 'strtolower', $allowed_hosts ), true ) ) {
						$error->add( 'unauthorized_redirect', __( 'The callback URL does not point to an allowed domain.' ) );
					}
				}
			}
		}
	}

	/** This action is documented in wp-admin/includes/user.php */
	do_action( 'wp_authorize_application_password_request_errors', $error, $request, $user );

	if ( $error->has_errors() ) {
		return $error;
	}

	return true;
}

