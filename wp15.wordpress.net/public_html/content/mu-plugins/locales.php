<?php

/*
Plugin Name: WP15 - Locales
Description: Manage front-end locale switching.
Version:     0.1
Author:      WordPress Meta Team
Author URI:  https://make.wordpress.org/meta
*/

namespace WP15\Locales;
defined( 'WPINC' ) || die();

use GP_Locales;

require_once trailingslashit( dirname( __FILE__ ) ) . 'locale-detection/locale-detection.php';

/**
 * Load the wp15 textdomain.
 */
function textdomain() {
	$path   = WP_LANG_DIR . '/wp15';
	$mofile = 'wp15-' . get_locale() . '.mo';

	load_textdomain(
		'wp15',
		$path . '/' . $mofile
	);
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\textdomain' );

/**
 * Modify the key for WP Super Cache to take locale into account.
 *
 * @param string $cache_key
 *
 * @return string
 */
function cache_key( $cache_key ) {
	$locale = get_locale();

	if ( $locale ) {
		$cache_key .= '-' . $locale;
	}

	return $cache_key;
}

add_filter( 'supercache_filename_str', __NAMESPACE__ . '\cache_key' );

/**
 * Register style and script assets for later enqueueing.
 */
function register_assets() {
	// Locale switcher script.
	wp_register_script(
		'locale-switcher',
		WP_CONTENT_URL . '/mu-plugins/assets/locale-switcher.js',
		array( 'jquery', 'select2', 'utils' ),
		1,
		true
	);

	wp_localize_script(
		'locale-switcher',
		'WP15LocaleSwitcher',
		array(
			'locale' => get_locale(),
			'dir'    => is_rtl() ? 'rtl' : 'ltr',
			'cookie' => array(
				'expires' => YEAR_IN_SECONDS,
				'cpath'   => SITECOOKIEPATH,
				'domain'  => '',
				'secure'  => true,
			)
		)
	);
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\register_assets' );

/**
 * Retrieves all available locales with their native names.
 *
 * See https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/themes/pub/wporg-login/functions.php#L150
 *
 * @return array Locales with their native names.
 */
function get_locales() {
	wp_cache_add_global_groups( [ 'locale-associations' ] );

	$wp_locales = wp_cache_get( 'locale-list', 'locale-associations' );
	if ( false === $wp_locales ) {
		$wp_locales = (array) $GLOBALS['wpdb']->get_col( 'SELECT locale FROM wporg_locales' );
		wp_cache_set( 'locale-list', $wp_locales, 'locale-associations' );
	}

	$wp_locales[] = 'en_US';

	require_once trailingslashit( dirname( __FILE__ ) ) . 'locales/locales.php';

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
 * See https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/themes/pub/wporg-login/functions.php#L184
 */
function locale_switcher() {
	$current_locale = get_locale();

	?>

	<div class="wp15-locale-switcher-container">
		<form id="wp15-locale-switcher-form" action="" method="GET">
			<label for="wp15-locale-switcher">
				<span aria-hidden="true" class="dashicons dashicons-translation"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Select the language:', 'wp15' ); ?></span>
			</label>

			<select id="wp15-locale-switcher" name="locale">
				<?php

				foreach ( get_locales() as $locale => $locale_name ) {
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

	<?php

	wp_enqueue_script( 'locale-switcher' );
}

/**
 * Prints markup for a notice when a locale isn't fully translated.
 */
function locale_notice() {
	$current_locale = get_locale();
	$status         = get_option( 'wp15_locale_status', array() );
	$threshold      = 90;
	$is_dismissed   = ! empty( $_COOKIE['wp15-locale-notice-dismissed'] );

	if ( isset( $status[ $current_locale ] ) && absint( $status[ $current_locale ] ) <= $threshold && ! $is_dismissed ) : ?>
		<div class="wp15-locale-notice">
			<p>
				<?php
				printf(
					wp_kses_post( __( 'The translation for this locale is incomplete. Help us get to 100%% by <a href="%s">contributing a translation</a>.', 'wp15' ) ),
					esc_url( sprintf(
						'https://translate.wordpress.org/projects/meta/wp15/%s/default',
						strtolower( str_replace( '_', '-', $current_locale ) )
					) )
				);
				?>
			</p>
			<button type="button" class="wp15-locale-notice-dismiss">
				<span class="screen-reader-text"><?php _e( 'Dismiss this notice.' ); ?></span>
			</button>
		</div>
	<?php endif;
}
