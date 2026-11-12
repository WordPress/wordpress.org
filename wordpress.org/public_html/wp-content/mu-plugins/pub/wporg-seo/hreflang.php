<?php
/**
 * Hreflang alternates for pages that exist on every locale site.
 *
 * Nothing here is hooked. A site that has the same page on the locale
 * subdomains calls the_links() from its own `wp_head` callback.
 *
 * @package WordPressdotorg\SEO
 */

declare( strict_types = 1 );

namespace WordPressdotorg\SEO\Hreflang;

/**
 * Locales Google doesn't support and reports errors for.
 *
 * @var string[]
 */
const UNSUPPORTED_LANGUAGES = array(
	'arq',
	'art',
	'art-xemoji',
	'art-pirate',
	'pirate',
	'ary',
	'ast',
	'az-ir',
	'azb',
	'bal',
	'bcc',
	'bho',
	'brx',
	'dsb',
	'ff-sn',
	'fon',
	'frp',
	'fuc',
	'fur',
	'gax',
	'haz',
	'hsb',
	'ido',
	'io',
	'kaa',
	'kab',
	'li',
	'li-nl',
	'lij',
	'lmo',
	'mai',
	'me',
	'me-me',
	'mfe',
	'nqo',
	'pap-aw',
	'pap-cw',
	'pcd',
	'pcm',
	'rhg',
	'rup',
	'sah',
	'sc-it',
	'scn',
	'skr',
	'srd',
	'syr',
	'szl',
	'tah',
	'twd',
	'ty-tj',
	'tzm',
	'vec',
	'zgh',
);

/**
 * Returns the locale sites that can be listed as hreflang alternates.
 *
 * @link https://support.google.com/webmasters/answer/189077?hl=en Use hreflang for language and regional URLs.
 *
 * @return object[] Sites keyed by locale, each with `locale`, `subdomain` and `hreflang`. Empty when unavailable.
 */
function get_sites(): array {
	global $wpdb;

	if ( ! defined( 'GLOTPRESS_LOCALES_PATH' ) ) {
		return array();
	}

	wp_cache_add_global_groups( array( 'locale-associations' ) );

	// Shared with the hreflang code in the themes and the theme directory, so keep the same shape.
	$sites = wp_cache_get( 'local-sites', 'locale-associations' );
	if ( false !== $sites ) {
		return $sites;
	}

	$sites = $wpdb->get_results( 'SELECT locale, subdomain FROM wporg_locales', OBJECT_K );
	if ( ! $sites ) {
		return array();
	}

	require_once GLOTPRESS_LOCALES_PATH;

	foreach ( $sites as $key => $site ) {
		$gp_locale = \GP_Locales::by_field( 'wp_locale', $site->locale );
		if ( ! $gp_locale ) {
			unset( $sites[ $key ] );
			continue;
		}

		// Skip non-existing subdomains, e.g. 'de_CH_informal'.
		if ( str_contains( (string) $site->subdomain, '_' ) ) {
			unset( $sites[ $key ] );
			continue;
		}

		if ( in_array( $gp_locale->slug, UNSUPPORTED_LANGUAGES, true ) ) {
			unset( $sites[ $key ] );
			continue;
		}

		$hreflang = false;

		// Google only supports ISO 639-1 codes.
		if ( isset( $gp_locale->lang_code_iso_639_1 ) && isset( $gp_locale->country_code ) ) {
			$hreflang = $gp_locale->lang_code_iso_639_1 . '-' . $gp_locale->country_code;
		} elseif ( isset( $gp_locale->lang_code_iso_639_1 ) ) {
			$hreflang = $gp_locale->lang_code_iso_639_1;
		} elseif ( isset( $gp_locale->lang_code_iso_639_2 ) ) {
			$hreflang = $gp_locale->lang_code_iso_639_2;
		} elseif ( isset( $gp_locale->lang_code_iso_639_3 ) ) {
			$hreflang = $gp_locale->lang_code_iso_639_3;
		}

		if ( $hreflang ) {
			$sites[ $key ]->hreflang = strtolower( $hreflang );
		} else {
			unset( $sites[ $key ] );
		}
	}

	$sites['en_US'] = (object) array(
		'locale'    => 'en_US',
		'hreflang'  => 'en',
		'subdomain' => '',
	);

	$sites['x-default'] = (object) array(
		'locale'    => 'x-default',
		'hreflang'  => 'x-default',
		'subdomain' => '',
	);

	uasort(
		$sites,
		static function ( object $a, object $b ): int {
			return strcasecmp( $a->hreflang, $b->hreflang );
		}
	);

	wp_cache_set( 'local-sites', $sites, 'locale-associations' );

	return $sites;
}

/**
 * Outputs a hreflang link to the given path on every locale site.
 *
 * @param string $path Path of the page, the same on every locale site. E.g. `/download/`.
 * @return void
 */
function the_links( string $path ): void {
	// A REST request that calls `wp_head` never expects these.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	foreach ( get_sites() as $site ) {
		$url = sprintf(
			'https://%swordpress.org%s',
			$site->subdomain ? "{$site->subdomain}." : '',
			$path
		);

		printf(
			'<link rel="alternate" href="%s" hreflang="%s" />' . "\n",
			esc_url( $url ),
			esc_attr( $site->hreflang )
		);
	}
}
