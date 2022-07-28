<?php
namespace WordPressdotorg\Post_Translation;
use GlotPress_Translate_Bridge;

const CACHE_GROUP = 'translated-post';

// Filter `get_the_title()`, and `the_title()`.
add_filter( 'the_title', __NAMESPACE__ . '\the_title', 2, 2 );

// Filter `get_the_except()` and `the_excerpt()`.
add_filter( 'get_the_excerpt', __NAMESPACE__ . '\get_the_excerpt', 2, 2 );

// Filter `the_content()` output, not `$post->post_content` or `get_the_content()`, as neither have filters.
// Before Block parsing at priority 9
add_filter( 'the_content', __NAMESPACE__ . '\the_content', 2, 2 );

/**
 * Get the translation post for use on the front-end, returns false for English(US) and admin contexts.
 * 
 * @return bool|string False on translation disabled / no project, or the translation project on success.
 */
function get_frontend_translation_project( $id ) {
	if ( 'en_US' === get_locale() || is_admin() ) {
		if ( 'https://wordpress.org/main-test/' === home_url( '/' ) ) {
			// fall through.. en_US but we want to affect it..
		} else {
			return false;
		}
	}

	return get_post_translation_project( $id );
}

/**
 * Translate the Post title.
 */
function the_title( $title, $id = 0 ) {
	return translate_string_for_post( $title, $post );
}

/**
 * Translate the Post except.
 */
function get_the_excerpt( $excerpt, $post = null ) {
	return translate_string_for_post( $excerpt, $post );
}

/**
 * Translate the Post content.
 */
function the_content( $content, $post = null ) {
	$post    = get_post( $post );
	$locale  = get_locale();
	$project = get_frontend_translation_project( $post );

	if ( ! $project || ! $post || ! $locale ) {
		return $content;
	}

	// FOR DEBUG ONLY
	wp_cache_add_non_persistent_groups( CACHE_GROUP );

	// Check the cache.
	$last_changed = wp_cache_get_last_changed( 'posts' );
	$cache_key    = "{$post->ID}:{$locale}:{$last_changed}";
	$cached       = wp_cache_get( $cache_key, CACHE_GROUP, false, $found );
	if ( $cached || $found ) {
		return $cached ?: $content;
	}

	$translated_content = Post_Parser::translate_blocks(
		$content,
		function( $string ) use( $project ) {
			return translate_string( $string, $project );
		}
	);

	wp_cache_set( $cache_key, $translated_content, CACHE_GROUP, 6 * HOUR_IN_SECONDS );

	return $translated_content ?: $content;
}

/**
 * Translate a given string in the context of a post.
 */
function translate_string_for_post( $string, $post ) {
	// Note: No caching is present in this function, as it's assumed the caching within `GlotPress_Translate_Bridge::translate()` is enough for singular strings.
	$translated = false;
	$project    = get_frontend_translation_project( $post );

	if ( $project ) {
		$translated = translate_string( $string, $project );
	}

	return $translated ?: $string;
}

function translate_string( $string, $project ) {

	// 1. Direct translation.
	$translated = GlotPress_Translate_Bridge::translate( $string, $project, null, $found );
	if ( $found ) {
		return apply_filters( 'gettext', $translated, $string, TEXTDOMAIN_PREFIX . $project );
	}

	// Try variations - These are only really here for testing purposes, they shouldn't be needed in production usually.
	// 2. Wrapping tags.
	//  TODO: This is only really needed due to a difference between the existing /gutenberg/ page and the Post_Parser extractions.
	if ( preg_match( '#^(?P<start><([a-z]+)[^>]*>)(?P<content>.*?)(?P<end></\\2>)$#i', $string, $m ) ) {
		$html_less = GlotPress_Translate_Bridge::translate( $m['content'], $project, null, $found );
		if ( $found ) {
			$translated = $m['start'] . $html_less . $m['end'];

			return apply_filters( 'gettext', $translated, $string, TEXTDOMAIN_PREFIX . $project );
		}
	}

	// 3. HTML entities.
	$translated = GlotPress_Translate_Bridge::translate( htmlentities( $string ), $project, null, $found );
	if ( $found ) {
		return apply_filters( 'gettext', $translated, $string, TEXTDOMAIN_PREFIX . $project );
	}

	return apply_filters( 'gettext', $string, $string, TEXTDOMAIN_PREFIX . $project );
}
