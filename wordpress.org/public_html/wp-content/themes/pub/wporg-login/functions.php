<?php
/**
 * WP.org login functions and definitions.
 *
 * @package wporg-login
 */

require __DIR__ . '/functions-restapi.php';
require __DIR__ . '/functions-registration.php';

/**
 * No-cache headers.
 */
add_action( 'template_redirect', 'nocache_headers', 10, 0 );

/**
 * Registers support for various WordPress features.
 */
function wporg_login_setup() {
	load_theme_textdomain( 'wporg' );
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
function wporg_login_init() {
	show_admin_bar( false );
}
add_action( 'init', 'wporg_login_init' );

/**
 * Replace cores login CSS with our own.
 */
function wporg_login_replace_css() {
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons' ), '20200507' );
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
	wp_enqueue_style( 'wporg-login', get_template_directory_uri() . '/stylesheets/login.css', array( 'login', 'dashicons' ), '20200507' );
}
add_action( 'wp_enqueue_scripts', 'wporg_login_scripts' );

function wporg_login_register_scripts() {
	wp_register_script( 'recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), '2' );
	wp_add_inline_script(
		'recaptcha-api',
		'function onSubmit(token) {
			var form = document.getElementById("registerform");

			if ( form.dataset.submitReady ) {
				form.submit();
			} else {
				// Still waiting on reCaptcha V3, disable the submit button.
				form.dataset.submitReady = true;
				document.getElementById("wp-submit").disabled = true;
			}
		}'
	);

	wp_register_script( 'wporg-registration', get_template_directory_uri() . '/js/registration.js', array( 'recaptcha-api', 'jquery' ), '20191023' );
	wp_localize_script( 'wporg-registration', 'wporg_registration', array(
		'rest_url' => esc_url_raw( rest_url( "wporg/v1" ) )
	) );

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
					form = document.getElementsByTagName("form")[0];

				input.setAttribute( "type", "hidden" );
				input.setAttribute( "name", "_reCaptcha_v3_token" );
				input.setAttribute( "value", token );

				form.appendChild( input );

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

// Don't index login/register pages.
add_action( 'wp_head', 'wp_no_robots', 1 );

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
add_filter( 'posts_request', '__return_empty_string' );
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
 */
function wporg_login_language_switcher() {
	$current_locale = get_locale();

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
}
add_action( 'wp_footer', 'wporg_login_language_switcher', 1 );
add_action( 'login_footer', 'wporg_login_language_switcher', 1 );

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
		$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'body' => $verify ) );
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
	$two_weeks_ago = gmdate( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS );

	$wpdb->query( $wpdb->prepare(
		"DELETE FROM `{$wpdb->base_prefix}user_pending_registrations`  WHERE `user_registered` <= %s",
		$two_weeks_ago
	) );
}
add_action( 'login_purge_pending_registrations', 'wporg_login_purge_pending_registrations' );

/**
 * Add a canonical tag to the login screens.
 */
function wporg_login_canonical_link() {
	$canonical = false;

	$matching_route = stripos( WP_WPOrg_SSO::$matched_route_regex, '(' );
	if ( false === $matching_route ) {
		$canonical = home_url( WP_WPOrg_SSO::$matched_route_regex ?: '/' );

	// Else, if there's a long enough slug followed by a `/`, that's a parent page.
	} elseif ( $matching_route >= 3 && '/' === substr( WP_WPOrg_SSO::$matched_route_regex, $matching_route + 1, 1 ) ) {
		$canonical = home_url( substr( WP_WPOrg_SSO::$matched_route_regex, 0, $matching_route ) );

	}

	if ( $canonical ) {
		printf( '<link rel="canonical" href="%s" />', esc_url( $canonical ) );
	}
}
add_action( 'login_head', 'wporg_login_canonical_link' );
add_action( 'wp_head', 'wporg_login_canonical_link' );

/**
 * Set the title for the wp-login.php page.
 */
function wporg_login_title() {
	return get_bloginfo( 'name', 'display' );
}
add_filter( 'login_title', 'wporg_login_title' );