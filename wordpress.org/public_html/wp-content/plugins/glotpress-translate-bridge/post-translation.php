<?php
/*
 * Plugin Name: GlotPress Post Translation
 * Description: This plugin allows for a post to be translated into another locale, through GlotPress.
 * Version: 0.2
 * Plugin URI: https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/glotpress-translate-bridge/
 * Author: WordPress.org Contributors
 * License: GPLv2
 */

namespace WordPressdotorg\Post_Translation;
use GlotPress_Translate_Bridge;

/*
 * TODO:
 *  - Re-evaluate how the GlotPress interaction in MakePot happens. Other projects
 *    on WordPress.org install that as a helper-plugin to translate.w.org and call
 *    a WP-CLI method instead.
 *  - Some Block Templates are known to not be caught by the `the_content` filters.
 *  - Some strings from post content include `&nbsp;` and `<br>` tags, it might be
 *    better to standardise some of these prior to inserting into GlotPress,
 *    for example, replacing `<br>` with a literal `\n`, although that will make
 *    retrieving them harder.
 *  - Test
 * 
 * Notes:
 *  - Enable the 'test' functions at the bottom of this file, by changing `/*` to `//*`.
 */

const TEXTDOMAIN_PREFIX     = 'dynamic-glotpress/';
const PROJECT_BASE          = 'disabled/posttranslation';
const META_KEY_PROJECT      = 'glotpress_translation_project';
const META_KEY_TRANSLATABLE = 'glotpress_translated';
const PROJECT_INHERIT_SETS  = PROJECT_BASE; // 'wp/dev'; // The project to inherit (copy) translation sets from.

function init() {
	if ( ! class_exists( 'GlotPress_Translate_Bridge' ) ) {
		require_once __DIR__ . '/glotpress-translate-bridge.php';
	}

	include_once __DIR__ . '/inc/post-parser.php';
	include_once __DIR__ . '/inc/front-end.php';

	if ( is_admin() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		include_once __DIR__ . '/inc/admin.php';
	}

}
add_action( 'init', __NAMESPACE__ . '\init', 9 );

/**
 * Get the translation project for a given post.
 *
 * Translation is enabled per-post, with a per-site project and optional project defined per post.
 */
function get_post_translation_project( $post ) {
	$post = get_post( $post );

	// Filter: pass translation status, post as context.
	if ( ! apply_filters( 'post_translation_enabled', (bool) $post->{ META_KEY_TRANSLATABLE }, $post ) ) {
		return false;
	}

	// Filter: project, post as context
	return apply_filters( 'post_translation_project', ( $post->{ META_KEY_PROJECT } ?: get_site_translation_project() ), $post );
}

/**
 * Get the translation project for a given site.
 *
 * This is used as the default fallback for if a post-specific project is not specified.
 */
function get_site_translation_project() {
	$slug = sanitize_title_with_dashes(
		str_replace(
			[
				'http://',
				'https://',
				'/'
			],
			[
				'',
				'',
				'-'
			],
			home_url()
		)
	);

	$project = PROJECT_BASE . '/' . $slug;

	return apply_filters( 'site_translation_project', $project, PROJECT_BASE, $slug );
}

// Temp hackery - this is for debugging and enabling this.
add_filter( 'post_translation_enabled', '__return_true' );
add_filter( 'post_translation_project', function( $project, $post ) {
	if (
		'https://wordpress.org/gutenberg/' === home_url( '/' ) &&
		97589 === $post->ID
	) {
		$project = 'meta/wordpress-org';
	}

	return $project;
}, 10, 2 );

// Make WordPress.org sites presented as Rosetta sites pull from the correct project.
add_filter( 'site_translation_project', function( $project, $base, $slug ) {
	if ( defined( 'IS_ROSETTA_NETWORK' ) && IS_ROSETTA_NETWORK && 'https://test.wordpress.org/' != home_url('/') ) {
		$project = preg_replace( '/^[a-z]{2,6}-wordpress-org/', 'wordpress-org', $project );
	}

	return $project;
}, 10, 3 );

/* Reverse all strings that should be translated, for visibility during test & review
add_filter( 'gettext', function( $translated, $original, $domain ) {
	if ( str_starts_with( $domain, TEXTDOMAIN_PREFIX ) && $translated === $original ) {
		// strrev that supports multibyte..
		$translated = (function( $string ) {
			$chars = mb_str_split( $string, 1, $encoding ?: mb_internal_encoding() );
			return implode( '', array_reverse( $chars ) );
		})($original);
	}

	return $translated;
}, 20, 3 );
//*/

/* CoNvErT aLl StRiNgS tO sTuPiDcAsE fOr ViSiBiLiTy
add_filter( 'gettext', function( $translated, $original, $domain ) {
	if ( str_starts_with( $domain, TEXTDOMAIN_PREFIX ) && $translated === $original ) {
		$translated = preg_replace_callback( '/[a-z]/i', function( $m ) {
			static $last = 0;
			$last = $last ? 0 : 1;
			return $last ? strtoupper( $m[0] ) : strtolower( $m[0] );
		}, $translated );
	}

	return $translated;
}, 20, 3 );
//*/