<?php
namespace WordPressdotorg\Post_Translation;
use GlotPress_Translate_Bridge;

const CACHE_GROUP = 'translated-post';

// Filter `get_the_title()`, and `the_title()`.
add_filter( 'the_title', __NAMESPACE__ . '\the_title', 2, 2 );

// Filter `get_the_except()` and `the_excerpt()`.
add_filter( 'get_the_excerpt', __NAMESPACE__ . '\get_the_excerpt', 2, 2 );

// Filter `the_content()` output, not `$post->post_content` or `get_the_content()`, as neither have filters.
// Before Block parsing at priority 9 (Ugh, doesn't catch Patterns inserted from within blocks! See render_block)
add_filter( 'the_content', __NAMESPACE__ . '\the_content', 2, 2 );

// Filter `render_block()` data to catch individual blocks in Block Templates
add_filter( 'render_block', __NAMESPACE__ . '\render_block', 10, 3 );

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
	return translate_string_for_post( $title, $id );
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

function render_block( $content, $block, $instance ) {
	/*
	 * Don't translate individual blocks when rendering a block within the content loop.
	 * This avoids double-processing blocks that are present within the content, while also
	 * processing blocks that are used elsewhere (Such as block templates).
	 */
	if ( doing_filter( 'the_content' ) ) {
		return $content;
	}

	$post    = get_post( $post );
	$locale  = get_locale();
	$project = get_frontend_translation_project( $post );

	if ( ! $project || ! $post || ! $locale ) {
		return $content;
	}

	$translated_content = Post_Parser::translate_block(
		$content,
		$block,
		function( $string ) use( $project ) {
			//var_dump( $string );
			return translate_string( $string, $project );
		}
	);

	return $translated_content ?: $content;
}

/**
 * Translate any postmeta fields specified.
 */
add_filter( 'get_post_metadata', __NAMESPACE__ . '\post_meta_filter', 100, 4 );
function post_meta_filter( $value, $post_id, $meta_key, $single ) {
	$translatable_post_meta = apply_filters( 'translatable_post_meta', [] );

	if ( ! in_array( $meta_key, $translatable_post_meta, true ) ) {
		return $value;
	}

	$project = get_frontend_translation_project( $post );

	remove_filter( 'get_post_metadata', __NAMESPACE__ . '\\' . __FUNCTION__ );

	$value = get_post_meta( $post_id, $meta_key, $single );

	if ( $single ) {
		$value = translate_string( $value, $project );
	} else {
		foreach ( $value as $i => $vv ) {
			$value[ $i ] = translate_string( $vv, $project );
		}
	}

	add_filter( 'get_post_metadata', __NAMESPACE__ . '\\' . __FUNCTION__, 100, 4 );

	return $value;
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
