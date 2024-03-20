<?php
/**
 * WP.org login functions and definitions.
 *
 * @package wporg-login
 */

require __DIR__ . '/functions-restapi.php';
require __DIR__ . '/functions-registration.php';

if ( is_admin() ) {
	require __DIR__ . '/admin/ui.php';
}

/**
 * No-cache headers.
 */
add_action( 'template_redirect', 'nocache_headers', 10, 0 );

/**
 * Registers support for various WordPress features.
 */
function wporg_login_setup() {
	load_theme_textdomain( 'wporg' );

	// We don't need wp4.css to load here.
	add_theme_support( 'wp4-styles' );
}
add_action( 'after_setup_theme', 'wporg_login_setup' );

/**
 * Extend the default WordPress body classes.
 *
 * @param array $classes A list of existing body class values.
 * @return array The filtered body class list.
 */
function wporg_login_body_class( $classes ) {
	if ( WP_WPOrg_SSO::$matched_route ) {
		$classes[] = 'route-' . WP_WPOrg_SSO::$matched_route;
	}

	// Remove the 404 class..
	if ( false !== ( $pos = array_search( 'error404', $classes ) ) ) {
		unset( $classes[ $pos ] );
	}
	return $classes;
}
add_filter( 'body_class', 'wporg_login_body_class' );

/**
 * Remove the toolbar.
 */
add_filter( 'show_admin_bar', '__return_false', 101 );

/**
 * Disable XML-RPC endpoints.
 */
add_filter( 'xmlrpc_methods', '__return_empty_array' );

/**
 * Replace cores login CSS with our own.
 */
function wporg_login_replace_css() {
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons' ), '20230504' );
}
add_action( 'login_init', 'wporg_login_replace_css' );

/**
 * Enqueue scripts and styles.
 */
function wporg_login_scripts() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

	// Concatenates core scripts when possible.
	if ( ! $script_debug ) {
		$GLOBALS['concatenate_scripts'] = true;
	}

	wp_enqueue_style( 'wporg-normalize', get_template_directory_uri() . '/stylesheets/normalize.css', 3 );
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons' ), filemtime( __DIR__ . '/stylesheets/login.css' ) );
}
add_action( 'wp_enqueue_scripts', 'wporg_login_scripts' );

function wporg_login_register_scripts() {
	if ( is_admin() ) {
		return;
	}

	wp_register_script( 'wporg-registration', get_template_directory_uri() . '/js/registration.js', array( 'recaptcha-api', 'jquery' ), filemtime( __DIR__ . '/js/registration.js' ) );
	wp_localize_script( 'wporg-registration', 'wporg_registration', array(
		'rest_url' => esc_url_raw( rest_url( 'wporg/v1' ) )
	) );

	// Local environments do not need reCaptcha.
	if ( 'local' === wp_get_environment_type() && ! defined( 'RECAPTCHA_V3_PUBKEY' ) ) {
		wp_register_script( 'recaptcha-api', false ); // Empty script to satisfy wporg-registration requirements.
		return;
	}


	wp_register_script( 'recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), '2' );
	wp_add_inline_script(
		'recaptcha-api',
		'function onSubmit(token) {
			var form = document.querySelector( "#registerform, form:not(#language-switcher)" );

			if ( ! form ) {
				return;
			}

			// This is used for the interaction with the invisible v3 recaptcha. 
			if ( form.dataset.submitReady ) {
				form.submit();
			} else {
				// Still waiting on reCaptcha V3, disable the submit button.
				form.dataset.submitReady = true;
				document.getElementById("wp-submit").disabled = true;
			}
		}'
	);

	// reCaptcha v3 is loaded on all login pages, not just the registration flow.
	wp_enqueue_script( 'recaptcha-api-v3', 'https://www.google.com/recaptcha/api.js?onload=reCaptcha_v3_init&render=' . RECAPTCHA_V3_PUBKEY, array(), '3' );
	$login_route = WP_WPOrg_SSO::$matched_route;
	if ( ! $login_route || 'root' == $login_route ) {
		$login_route = 'login';
	}
	// reCaptcha only supports [a-Z _/] as the action.
	$login_route = preg_replace( '#[^a-z/_ ]#i', '_', $login_route );

	wp_add_inline_script(
		'recaptcha-api-v3',
		'function reCaptcha_v3_init() {
			grecaptcha.execute(' .
				json_encode( RECAPTCHA_V3_PUBKEY ) .
				', {action: ' . json_encode( $login_route ) . ' }
			).then( function( token ) {
				// Add the token to the "primary" form
				var input = document.createElement( "input" ),
					form = document.querySelector( "#registerform, form:not(#language-switcher)" );

				if ( ! form ) {
					return;
				}

				input.setAttribute( "type", "hidden" );
				input.setAttribute( "name", "_reCaptcha_v3_token" );
				input.setAttribute( "value", token );

				form.appendChild( input );

				// If the visual reCaptcha v2 is not loaded, this data point will never be used.
				if ( form.dataset.submitReady ) {
					form.submit();
				} else {
					form.dataset.submitReady = true;
				}
			});
		}'
	);
}
add_action( 'init', 'wporg_login_register_scripts' );

/**
 * wp_die() handler for login.wordpress.org, adds GTM to error pages.
 */
function wporg_login_die_handler( $message, $title = '', $args = array() ) {
	if ( is_string( $message ) && is_callable( '\WordPressdotorg\Plugin\GoogleTagManager\wp_head' ) ) {
		ob_start();

		\WordPressdotorg\Plugin\GoogleTagManager\wp_head();
		\WordPressdotorg\Plugin\GoogleTagManager\wp_body_open();

		$gtm = ob_get_clean();

		$message = $gtm . $message;
	}

	return _default_wp_die_handler( $message, $title, $args );
}

/**
 * Switch the default WP_Die handler for login.wordpress.org to one that includes GTM.
 */
function wp_die_handler_switcher( $handler ) {
	if ( $handler == '_default_wp_die_handler' ) {
		$handler = 'wporg_login_die_handler';
	}

	return $handler;
}
add_filter( 'wp_die_handler', 'wp_die_handler_switcher' );

/**
 * Avoid sending a 404 header but send a 200 with nocache headers.
 */
function wporg_login_pre_handle_404( $false, $wp_query ) {
	$wp_query->set_404(); // Set the query as 404 to avoid things running thinking it's a real page
	status_header( 200 ); // but return a 200
	return true;
}
add_filter( 'pre_handle_404', 'wporg_login_pre_handle_404', 10, 2 );

/**
 * Filters the page template to load wporg-login/$route.php.
 *
 * @param array $templates The templates WordPress intends to load.
 * @return array The templates the theme intends to use.
 */
function wporg_login_filter_templates( $templates ) {
	$route = WP_WPOrg_SSO::$matched_route;

	if ( ! $route || 'root' === $route ) {
		$route = 'login';
	}

	return array( "{$route}.php", 'index.php' );
}
add_filter( 'index_template_hierarchy', 'wporg_login_filter_templates' );

// Don't index login/register sub-pages.
add_filter( 'wporg_noindex_request', function( $noindex ) {

	// Don't no-index the front page, see https://meta.trac.wordpress.org/ticket/5530
	if ( '/' === $_SERVER['REQUEST_URI'] ) {
		return $noindex;
	}

	// noindex it.
	return true;
} );

// No emoji support needed.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// No Jetpack styles needed.
add_filter( 'jetpack_implode_frontend_css', '__return_false' );

// No embeds needed.
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
remove_action( 'rest_api_init', 'wp_oembed_register_route' );

// Don't perform any WP_Query queries on this site..
if ( ! is_admin() ) {
	add_filter( 'posts_pre_query', '__return_empty_array' );
}

// Don't attempt to do canonical lookups..
remove_filter( 'template_redirect', 'redirect_canonical' );

// There's no need to edit the site..
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );

// We don't need all the rest routes either..
remove_action( 'rest_api_init', 'create_initial_rest_routes', 99 );

/**
 * Don't need all the wp-admin specific user metas on user create/update.
 *
 * @param array $meta Default meta values and keys for the user.
 * @return array Filtered meta values and keys for the user.
 */
function wporg_login_limit_user_meta( $meta ) {
	$keep = [ 'nickname' ];
	return array_intersect_key( $meta, array_flip( $keep ) );
}
add_filter( 'insert_user_meta', 'wporg_login_limit_user_meta', 1 );

/**
 * Remove the default contact methods.
 * This prevents the user meta being created unless they edit their profiles.
 */
add_filter( 'user_contactmethods', '__return_empty_array' );

/**
 * Retreives all avaiable locales with their native names.
 *
 * @return array Locales with their native names.
 */
function wporg_login_get_locales() {
	wp_cache_add_global_groups( [ 'locale-associations' ] );

	$wp_locales = wp_cache_get( 'locale-list', 'locale-associations' );
	if ( false === $wp_locales ) {
		$wp_locales = (array) $GLOBALS['wpdb']->get_col( 'SELECT locale FROM wporg_locales' );
		wp_cache_set( 'locale-list', $wp_locales, 'locale-associations' );
	}

	$wp_locales[] = 'en_US';

	require_once GLOTPRESS_LOCALES_PATH;

	$locales = [];

	foreach ( $wp_locales as $locale ) {
		$gp_locale = GP_Locales::by_field( 'wp_locale', $locale );
		if ( ! $gp_locale ) {
			continue;
		}

		$locales[ $locale ] = $gp_locale->native_name;
	}

	natsort( $locales );

	return $locales;
}

/**
 * Prints markup for a simple language switcher.
 * 
 * Note: See the 'Locale Detection' plugin for the switching of the locale.
 */
function wporg_login_language_switcher( $display = true ) {
	$current_locale = get_locale();

	/*
	 * If something has explicitely disabled the switcher, don't show our version either.
	 * This is used for when we're called from the 'login_display_language_dropdown' filter.
	 */
	if ( ! $display ) {
		return $display;
	}

	?>
	<div class="language-switcher">
		<form id="language-switcher" action="" method="GET">
			<?php if ( !empty( $_GET['redirect_to'] ) ): ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $_GET['redirect_to'] ); ?>" />
			<?php endif; ?>
			<label for="language-switcher-locales">
				<span aria-hidden="true" class="dashicons dashicons-translation"></span>
				<span class="screen-reader-text"><?php _e( 'Select the language:', 'wporg' ); ?></span>
			</label>
			<select id="language-switcher-locales" name="locale">
				<?php
				foreach ( wporg_login_get_locales() as $locale => $locale_name ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $locale ),
						selected( $locale, $current_locale, false ),
						esc_html( $locale_name )
					);
				}
				?>
			</select>
		</form>
	</div>
	<script>
		var switcherForm  = document.getElementById( 'language-switcher' );
		var localesSelect = document.getElementById( 'language-switcher-locales' );
		localesSelect.addEventListener( 'change', function() {
			switcherForm.submit()
		} );
	</script>
	<?php

	return false; // For the login_display_language_dropdown filter.
}
add_action( 'wp_footer', 'wporg_login_language_switcher', 1, 0 );
add_action( 'login_display_language_dropdown', 'wporg_login_language_switcher', 20 );

/**
 * Simple API for accessing the reCaptcha verify api.
 */
function wporg_login_recaptcha_api( $token, $key ) {
	// Just a basic cache for multiple calls on the same token on the same pageload.
	static $cache = array();

	$verify = array(
		'secret'   => $key,
		'remoteip' => $_SERVER['REMOTE_ADDR'],
		'response' => $token,
	);
	$cache_key = implode( ':', $verify );

	if ( ! isset( $cache[ $cache_key ] ) ) {
		$resp = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => $verify
			)
		);
		if ( is_wp_error( $resp ) || 200 != wp_remote_retrieve_response_code( $resp ) ) {
			$cache[ $cache_key ] = false;
			return false;
		}

		$cache[ $cache_key ] = json_decode( wp_remote_retrieve_body( $resp ), true );
	}

	return $cache[ $cache_key ];
}

/**
 * Schedule a cron-task to clear pending registrations regularly.
 */
function wporg_login_cron_tasks() {
	if ( ! wp_next_scheduled( 'login_purge_pending_registrations' ) ) {
		wp_schedule_event( time(), 'daily', 'login_purge_pending_registrations' );
	}
}
add_action( 'admin_init', 'wporg_login_cron_tasks' );

/**
 * Clears the Pending Registrations table reguarly.
 */
function wporg_login_purge_pending_registrations() {
	global $wpdb;
	$timeout_s = time() - 8 * WEEK_IN_SECONDS;
	$timeout   = gmdate( 'Y-m-d H:i:s', $timeout_s );

	$wpdb->query( $wpdb->prepare(
		"DELETE FROM `{$wpdb->base_prefix}user_pending_registrations`
		WHERE `user_registered` <= %s AND LEFT( `user_activation_key`, 10 ) <= %d",
		$timeout,
		$timeout_s
	) );
}
add_action( 'login_purge_pending_registrations', 'wporg_login_purge_pending_registrations' );

/**
 * The canonical url for login.wordpress.org is a bit different.
 */
function wporg_login_canonical_url( $canonical ) {
	$canonical = false;

	// If the regular expression for this route is not matching, it's the canonical.
	$matching_route = stripos( WP_WPOrg_SSO::$matched_route_regex, '(' );
	if ( false === $matching_route ) {
		$canonical = home_url( WP_WPOrg_SSO::$matched_route_regex ?: '/' );

	// Else, if there's a long enough slug followed by a `/`, that's a parent page.
	} elseif ( $matching_route >= 3 && '/' === substr( WP_WPOrg_SSO::$matched_route_regex, $matching_route + 1, 1 ) ) {
		$canonical = home_url( substr( WP_WPOrg_SSO::$matched_route_regex, 0, $matching_route ) );

	}

	return $canonical;
}
add_filter( 'wporg_canonical_url', 'wporg_login_canonical_url' );

/**
 * Set the title for the wp-login.php page.
 */
function wporg_login_title() {

	// Note: This does a poor job of duplicating the title from the rosetta header.
	//  On rosetta networks, the title is defined in the Blog title field, not from GP_Locale.

	return
		( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ? WPORG_SANDBOXED . ': ' : '' ) .
		__( 'WordPress.org Login', 'wporg' ) . 
		' &#124; WordPress.org ' . 
		( wporg_login_get_locales()[ get_locale() ] ?? '' );

}
add_filter( 'login_title', 'wporg_login_title' );

function wporg_login_wporg_is_starpress( $redirect_to = '' ) {

	$message = '';

	$from = 'wordpress.org';
	if ( $redirect_to ) {
		$from = $redirect_to;
	} elseif ( !empty( $_REQUEST['from'] ) ) {
		$from = $_REQUEST['from'];
	} elseif ( !empty( $_REQUEST['redirect_to'] ) ) {
		$from = $_REQUEST['redirect_to'];
	}

	if ( false !== stripos( $from, 'buddypress.org' ) ) {
		$message .= '<strong>' . __( 'BuddyPress is part of WordPress.org', 'wporg' ) . '</strong><br>';
		$message .= __( 'Log in to your WordPress.org account to contribute to BuddyPress, or get help in the support forums.', 'wporg' );
	
	} elseif ( false !== stripos( $from, 'bbpress.org' ) ) {
		$message .= '<strong>' . __( 'bbPress is part of WordPress.org', 'wporg' ) . '</strong><br>';
		$message .= __( 'Log in to your WordPress.org account to contribute to bbPress, or get help in the support forums.', 'wporg' );
	
	} elseif ( false !== stripos( $from, 'wordcamp.org' ) ) {
		$message .= '<strong>' . __( 'WordCamp is part of WordPress.org', 'wporg' ) . '</strong><br>';
		$message .= __( 'Log in to your WordPress.org account to contribute to WordCamps and meetups around the globe.', 'wporg' );
	
	} else {
		$message .= __( 'Log in to your WordPress.org account to contribute to WordPress, get help in the support forum, or rate and review themes and plugins.', 'wporg' );
	}

	return $message;
}

// This is the login messages, which is displayed on wp-login.php, which does not use wp_login_form() or it's actions.
function wporg_login_errors_message( $errors, $redirect_to ) {
	$errors->add(
		'pre_login_message',
		( isset( $_GET['loggedout'] ) ? '<br>' : '' ) .
			wporg_login_wporg_is_starpress( $redirect_to ),
		'message' // This is not an error..
	);

	return $errors;
}
add_filter( 'wp_login_errors', 'wporg_login_errors_message', 10, 2 );

/**
 * Replace some login related error messages with nicer forms.
 */
function wporg_login_errors_nicify( $errors, $redirect_to ) {
	$sso = WPOrg_SSO::get_instance();

	$replace_errors = [
		'invalid_username' => sprintf(
			/* translators: %s: <strong>UserLogin</strong> */
			__( "<strong>Error:</strong> The username %s is not registered on WordPress.org. If you're unsure of your username, you can attempt to log in using your email address instead.", 'wporg' ),
			'<strong>' . esc_html( wp_unslash( $_POST['log'] ?? '' ) ) . '</strong>'
		),

		'must_change_password' => sprintf(
			/* translators: %s: <code>password reset help queue.</code> */
			__( "<strong>Error:</strong> Your password needs to be changed. Please check your email for a link to set a new password. Please contact %s if you are unable to access your account's registered email.", 'wporg' ),
			'<code>' . $sso::SUPPORT_EMAIL . '</code>'
		)
	];

	foreach ( $replace_errors as $error_code => $error_message ) {
		if ( $errors->get_error_message( $error_code ) ) {
			// Remove the existing one.
			$errors->remove( $error_code );
			// Replace it.
			$errors->add( $error_code, $error_message );
		}
	}

	return $errors;
}
add_filter( 'wp_login_errors', 'wporg_login_errors_nicify', 10, 2 );

/**
 * Fetch the URL to the locales WordPress.org site.
 */
function wporg_login_wordpress_url() {
	/* This pulls the translation from the WordPress translations, mimicking wp-login.php */
	$url = apply_filters( 'login_headerurl', translate( 'https://wordpress.org/' ) );

	if ( ! $url || false === stripos( $url, '.wordpress.org' ) ) {
		$url = 'https://wordpress.org/';
	}

	return esc_url( $url );
}

/**
 * Remember the source of where the user came from,
 * to allow redirects to be kept even when the redirect is lost.
 */
function wporg_remember_where_user_came_from() {
	if ( ! empty( $_COOKIE['wporg_came_from'] ) ) {
		return;
	}

	$came_from = $_REQUEST['redirect_to'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' );
	if ( ! $came_from ) {
		return;
	}

	setcookie( 'wporg_came_from', $came_from, time() + 10*MINUTE_IN_SECONDS, '/', WPOrg_SSO::get_instance()->get_cookie_host(), true, true );
}
add_action( 'init', 'wporg_remember_where_user_came_from' );

/**
 * Override the ultimate login location with the cookie value, if the redirect
 * is going to land the user on somewhere that they did not actually come from.
 */
function wporg_remember_where_user_came_from_redirect( $redirect, $requested_redirect_to, $user ) {
	if ( empty( $_COOKIE['wporg_came_from'] ) || is_wp_error( $user ) ) {
		return $redirect;
	}

	// If the redirect is to a url that doesn't seem right, override it.
	$redirect_host = parse_url( $redirect, PHP_URL_HOST );
	$redirect_qv   = parse_url( $redirect, PHP_URL_QUERY );
	$proper_host   = parse_url( $_COOKIE['wporg_came_from'], PHP_URL_HOST );
	if (
		$redirect_host != $proper_host &&
		in_array(
			$redirect_host,
			[
				'profiles.wordpress.org', // Default redirect for low-priv users.
				'login.wordpress.org',    // Default redirect for priv'd users.
			]
		) &&
		// Don't override if the redirect is back to an OIDC destination.
		! (
			'login.wordpress.org' == $redirect_host &&
			str_contains( $redirect_qv, 'response_type=code' )
		)
	) {
		if ( wp_validate_redirect( $_COOKIE['wporg_came_from'] ) ) {
			$redirect = $_COOKIE['wporg_came_from'];
		}
	}

	return $redirect;
}
add_filter( 'login_redirect', 'wporg_remember_where_user_came_from_redirect', 100, 3 );
