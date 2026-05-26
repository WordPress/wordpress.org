<?php
namespace WordPressdotorg\Post_Translation;

use GlotPress_Translate_Bridge;

/**
 * Applies translations from GlotPress to post content on the frontend.
 */
class Frontend {
	const CACHE_GROUP = 'post-translation';

	public static function init() {
		// Skip in admin and for English.
		if ( is_admin() ) {
			return;
		}

		add_filter( 'the_title', [ __CLASS__, 'translate_title' ], 2, 2 );
		add_filter( 'get_the_excerpt', [ __CLASS__, 'translate_excerpt' ], 2, 2 );

		// Priority 2: before block rendering at priority 9.
		add_filter( 'the_content', [ __CLASS__, 'translate_content' ], 2 );

		// Catch blocks rendered outside the_content (block templates, patterns).
		add_filter( 'render_block', [ __CLASS__, 'translate_rendered_block' ], 10, 3 );

		// Translate post meta fields.
		add_filter( 'get_post_metadata', [ __CLASS__, 'translate_meta' ], 100, 4 );
	}

	/**
	 * Translate the post title.
	 */
	public static function translate_title( $title, $id = 0 ) {
		$project = self::get_project_for_display( $id );
		if ( ! $project ) {
			return $title;
		}

		return self::translate_string( $title, $project ) ?: $title;
	}

	/**
	 * Translate the post excerpt.
	 */
	public static function translate_excerpt( $excerpt, $post = null ) {
		$post    = get_post( $post );
		$project = $post ? self::get_project_for_display( $post ) : false;

		if ( ! $project ) {
			return $excerpt;
		}

		return self::translate_string( $excerpt, $project ) ?: $excerpt;
	}

	/**
	 * Translate the post content (block content).
	 */
	public static function translate_content( $content ) {
		$post    = get_post();
		$project = $post ? self::get_project_for_display( $post ) : false;

		if ( ! $project ) {
			return $content;
		}

		// Cache the parsed/translated content in the object cache. The compound
		// cache key relies on last_changed tokens that only persist with an
		// external object cache, so skip caching entirely without one (e.g. local
		// dev) rather than churn through per-request keys. The bridge still caches
		// individual string lookups on its own.
		$use_cache = wp_using_ext_object_cache();
		$cache_key = '';

		if ( $use_cache ) {
			$cache_key = self::cache_key( $post->ID, get_locale() );
			$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

			// Empty string means "no translations found" (distinct from false = no cache).
			if ( false !== $cached ) {
				return $cached ?: $content;
			}
		}

		/**
		 * Fires when a post is about to be translated on the frontend.
		 *
		 * This allows other plugins (e.g., the translator UI) to register
		 * the GlotPress project and textdomain for this post.
		 */
		do_action( 'post_translation_translating', $project, $post );

		$parser     = new Post_Parser();
		$translated = $parser->translate_content(
			$content,
			function ( $string ) use ( $project ) {
				return self::translate_string( $string, $project );
			}
		);

		if ( $use_cache ) {
			wp_cache_set( $cache_key, $translated ?: '', self::CACHE_GROUP, 6 * HOUR_IN_SECONDS );
		}

		return $translated ?: $content;
	}

	/**
	 * Translate individual blocks rendered outside the_content.
	 *
	 * This catches blocks in block templates and patterns that are
	 * rendered via render_block() rather than through the_content filter.
	 */
	public static function translate_rendered_block( $content, $block, $instance ) {
		// Don't double-process blocks within the_content.
		if ( doing_filter( 'the_content' ) ) {
			return $content;
		}

		$post    = get_post();
		$project = $post ? self::get_project_for_display( $post ) : false;

		if ( ! $project ) {
			return $content;
		}

		$parser = new Post_Parser();

		// Extract strings from this single block (not innerBlocks, they get their own render_block call).
		$block_parser = $parser->get_parser( $block['blockName'] ?? null );
		$strings      = $block_parser->to_strings( $block );
		if ( ! $strings ) {
			return $content;
		}

		// Build a single replacement map and apply it in one strtr() pass, so an
		// earlier replacement can't become the match target of a later one.
		$map = [];
		foreach ( $strings as $string ) {
			$translated = self::translate_string( $string, $project );
			if ( $translated !== $string ) {
				$map[ $string ] = wp_kses_post( $translated );
			}
		}

		return $map ? strtr( $content, $map ) : $content;
	}

	/**
	 * Translate post meta values.
	 */
	public static function translate_meta( $value, $post_id, $meta_key, $single ) {
		$meta_keys = apply_filters( 'post_translation_meta_keys', [] );

		if ( ! in_array( $meta_key, $meta_keys, true ) ) {
			return $value;
		}

		$project = self::get_project_for_display( $post_id );
		if ( ! $project ) {
			return $value;
		}

		// Temporarily remove our filter to avoid recursion.
		remove_filter( 'get_post_metadata', [ __CLASS__, 'translate_meta' ], 100 );
		$value = get_post_meta( $post_id, $meta_key, $single );
		add_filter( 'get_post_metadata', [ __CLASS__, 'translate_meta' ], 100, 4 );

		if ( $single && is_string( $value ) ) {
			$value = self::translate_string( $value, $project );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as &$item ) {
				if ( is_string( $item ) ) {
					$item = self::translate_string( $item, $project );
				}
			}
		}

		return $value;
	}

	/**
	 * Translate a single string via GlotPress and fire the gettext filter.
	 *
	 * The gettext filter is fired with a dynamic textdomain so that the
	 * future translator UI plugin can capture post-content strings.
	 *
	 * @param string $string  Original string.
	 * @param string $project GlotPress project path.
	 * @return string Translated string.
	 */
	protected static function translate_string( string $string, string $project ): string {
		if ( '' === $string ) {
			return $string;
		}

		$translated = GlotPress_Translate_Bridge::translate( $string, $project, null, $found );

		if ( $found ) {
			return apply_filters( 'gettext', $translated, $string, TEXTDOMAIN_PREFIX . $project );
		}

		// Fire gettext even for untranslated strings so the translator UI can see them.
		return apply_filters( 'gettext', $string, $string, TEXTDOMAIN_PREFIX . $project );
	}

	/**
	 * Check if translation should be applied for display of a post.
	 *
	 * Returns the project path if translation is active, false otherwise.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @return string|false Project path or false.
	 */
	protected static function get_project_for_display( $post ) {
		if ( 'en_US' === get_locale() ) {
			return false;
		}

		return get_translation_project( $post );
	}

	/**
	 * Generate a cache key for translated content.
	 *
	 * The key includes both the posts last_changed and a GlotPress translations
	 * last_changed token, so the cache invalidates when either changes.
	 */
	protected static function cache_key( int $post_id, string $locale ): string {
		$posts_changed = wp_cache_get_last_changed( 'posts' );
		$gp_changed    = wp_cache_get_last_changed( self::CACHE_GROUP );

		return sprintf( 'pt_%d_%s_%s_%s', $post_id, $locale, $posts_changed, $gp_changed );
	}
}
