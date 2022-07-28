<?php
/*
 * Plugin Name: GlotPress Post Translation
 * Description: This plugin allows for a post to be translated into another locale, through GlotPress.
 * Version: 0.1
 * Plugin URI: https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/glotpress-translate-bridge/
 * Author: WordPress.org Contributors
 * License: GPLv2
 */

namespace WordPressdotorg\Post_Translation;
use GlotPress_Translate_Bridge;

/*
 * TODO:
 *  - Create GlotPress projects within a parent-project scope for translations
 *  - Re-evaluate how it selects projects for where to pull a string from,
 *    currently this is done through post_meta and filtered, but this should
 *    probably be defined per-site rather than per-post or be 100% automatically
 *    selected.
 *  - Some Block Templates are known to not be caught by the `the_content` filters.
 *  - Some strings from post content include `&nbsp;` and `<br>` tags, it might be
 *    better to standardise some of these prior to inserting into GlotPress,
 *    for example, replacing `<br>` with a literal `\n`, although that will make
 *    retrieving them harder.
 *  - Test
 * 
 * Notes:
 *  - Enable the 'test' functions at the bottom of this file, but changing `/*` to `//*`.
 */

const TEXTDOMAIN_PREFIX     = 'dynamic-glotpress/';
const DEFAULT_PROJECT       = 'disabled/posttranslation';
const META_KEY_PROJECT      = 'glotpress_translation_project';
const META_KEY_TRANSLATABLE = 'glotpress_translated';

function init() {
	if ( ! class_exists( 'GlotPress_Translate_Bridge' ) ) {
		require_once __DIR__ . '/glotpress-translate-bridge.php';
	}

	include_once __DIR__ . '/inc/post-parser.php';
	include_once __DIR__ . '/inc/front-end.php';

	if ( is_admin() || wp_doing_cron() ) {
		include_once __DIR__ . '/inc/admin.php';
	}

}
add_action( 'init', __NAMESPACE__ . '\init', 9 );

/**
 * Get the project for a posts translation project.
 */
function get_post_translation_project( $post ) {
	$post = get_post( $post );

	// Filter: pass translation status, post as context.
	if ( ! apply_filters( 'post_translation_enabled', (bool) $post->{ META_KEY_TRANSLATABLE }, $post ) ) {
		return false;
	}

	// Filter: project, post as context
	return apply_filters( 'post_translation_project', ( $post->{ META_KEY_PROJECT } ?: DEFAULT_PROJECT ), $post );
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

	if (
		'https://wordpress.org/main-test/' === home_url( '/' )
	) {
		$project = DEFAULT_PROJECT . '/wordpress-org-main-test';
	}

	// TODO: This filter might deserve to be set to select the appropriate GlotPress project automatically.

	return $project;
}, 10, 2 );

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