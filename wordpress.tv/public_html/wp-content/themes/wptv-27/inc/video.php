<?php
/**
 * VideoPress helpers.
 *
 * Ported from the wptv2 WordPressTV_Theme class. VideoPress data functions
 * (video_get_info_by_guid() and friends) only exist on WordPress.com, so every
 * call is guarded. Thumbnails also use the standalone plugin's guarded poster
 * resolver on Atomic/Studio (docs/wpcom-constraints.md).
 *
 * @package WordPressTV
 */

namespace WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the VideoPress GUID from a legacy shortcode or VideoPress block.
 *
 * Legacy shortcodes take precedence; the last one wins (same as wptv2).
 * Without a shortcode, use the last valid VideoPress block, including nested blocks.
 *
 * @param int|\WP_Post|null $post Post. Defaults to the current post.
 * @return string|false
 */
function get_guid( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}

	$guid = false;
	if ( preg_match_all( '/\[wpvideo +([a-zA-Z0-9,\#,\&,\/,;,",=, ]*?)\]/i', $post->post_content, $matches ) ) {
		foreach ( $matches[1] as $code ) {
			if ( preg_match( '/([0-9A-Za-z]+)/i', $code, $m ) ) {
				$guid = $m[1];
			}
		}
	}

	if ( ! $guid && has_blocks( $post ) ) {
		$video = get_block_video( parse_blocks( $post->post_content ) );
		$guid  = $video['guid'] ?? false;
	}

	return $guid;
}

/**
 * Finds the last valid VideoPress block's attributes in document order.
 *
 * Supports the current saved block name and the earlier Jetpack block name.
 * Does not inspect cached iframe HTML, resolve synced patterns or render content.
 *
 * @param array $blocks Parsed blocks.
 * @return array Empty when no supported video block has a valid GUID.
 */
function get_block_video( array $blocks ) {
	$video = array();
	foreach ( $blocks as $block ) {
		$attributes = $block['attrs'] ?? array();
		if (
			in_array( $block['blockName'] ?? '', array( 'videopress/video', 'jetpack/videopress' ), true ) &&
			isset( $attributes['guid'] ) && is_string( $attributes['guid'] ) &&
			preg_match( '/^[A-Za-z0-9]{8}$/D', $attributes['guid'] )
		) {
			$video = $attributes;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$nested = get_block_video( $block['innerBlocks'] );
			if ( $nested ) {
				$video = $nested;
			}
		}
	}
	return $video;
}

/**
 * Resolves the first legacy shortcode for integrations, or the block fallback.
 *
 * Unlike get_guid(), these legacy consumers used the production shortcode API's
 * first result. Never replace that behavior with the general last-shortcode rule.
 * A legacy marker without that API/data must not fall through to a block.
 *
 * @param int|\WP_Post|null $post Post.
 * @return string|false
 */
function get_integration_guid( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	if ( function_exists( 'find_all_videopress_shortcodes' ) ) {
		$shortcodes = find_all_videopress_shortcodes( $post->post_content );
		if ( is_array( $shortcodes ) && $shortcodes ) {
			$guids = array_keys( $shortcodes );
			return (string) $guids[0];
		}
	}
	return false === stristr( $post->post_content, '[wpvideo' ) ? get_guid( $post ) : false;
}

/**
 * Returns the VideoPress details of a post's video.
 *
 * @param int|\WP_Post|null $post Post. Defaults to the current post.
 * @return object|false
 */
function get_details( $post = null ) {
	$guid = get_guid( $post );
	if ( ! $guid || ! function_exists( 'video_get_info_by_guid' ) ) {
		return false;
	}
	return video_get_info_by_guid( $guid );
}

/**
 * Returns the URL of a post's video thumbnail.
 *
 * @param int|\WP_Post|null $post Post. Defaults to the current post.
 * @return string|false
 */
function get_image_url( $post = null ) {
	$post = get_post( $post );
	$guid = get_guid( $post );
	if ( ! $post || ! $guid ) {
		return false;
	}

	/**
	 * Short-circuits image lookup. Null continues; false means no image.
	 * Used by opt-in local route tests to avoid real VideoPress data/network calls.
	 *
	 * @param string|false|null $image Image URL, false, or null to continue.
	 * @param string           $guid  VideoPress GUID.
	 * @param \WP_Post         $post  Video post.
	 */
	$image = apply_filters( 'wptv_pre_video_image_url', null, $guid, $post );
	if ( null !== $image ) {
		return is_string( $image ) && $image ? esc_url_raw( $image, array( 'http', 'https' ) ) : false;
	}

	// The featured image is the video poster (docs/decisions.md, 2026-10-21).
	$image = get_featured_image_url( $post );
	if ( $image ) {
		return $image;
	}

	// Keep the production multisite API first, unchanged for existing shortcodes.
	if ( function_exists( 'video_get_highest_resolution_image_url' ) ) {
		$image = video_get_highest_resolution_image_url( $guid );
		if ( is_string( $image ) && $image ) {
			return esc_url_raw( $image, array( 'http', 'https' ) );
		}
	}

	// The standalone plugin owns attachment lookup, privacy checks and API caching.
	$resolver = array( '\\Automattic\\Jetpack\\VideoPress\\Inline_Player', 'get_poster_url' );
	if ( ! is_callable( $resolver ) ) {
		return false;
	}
	$video = has_blocks( $post ) ? get_block_video( parse_blocks( $post->post_content ) ) : array();
	// Never apply another video's poster, attachment ID or privacy to this GUID.
	$attributes = ( $video['guid'] ?? '' ) === $guid ? $video : array();
	$image      = call_user_func( $resolver, $guid, $attributes );
	return is_string( $image ) && $image ? esc_url_raw( $image, array( 'http', 'https' ) ) : false;
}

/**
 * Returns the full-size URL of a post's featured image, if it is a real image.
 *
 * @param int|\WP_Post|null $post Post.
 * @return string|false
 */
function get_featured_image_url( $post = null ) {
	$post = get_post( $post );
	$id   = $post ? (int) get_post_thumbnail_id( $post ) : 0;
	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		return false;
	}
	$url = wp_get_attachment_image_url( $id, 'full' );
	return $url ? esc_url_raw( $url, array( 'http', 'https' ) ) : false;
}

/**
 * Applies the global player settings and the featured-image poster to
 * VideoPress blocks in video posts. Render-time only: stored content is not
 * rewritten, and changing the featured image updates the player.
 *
 * Without a featured image, block poster values are discarded so the
 * VideoPress poster is used. Video-level settings (download, sharing,
 * privacy, rating) belong to VideoPress and are deliberately untouched.
 *
 * @param array $block Parsed block.
 * @return array
 */
function player_attributes( $block ) {
	if ( ! in_array( $block['blockName'] ?? '', array( 'videopress/video', 'jetpack/videopress' ), true ) ) {
		return $block;
	}
	$post_id = get_the_ID();
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return $block;
	}
	$attributes = array_merge(
		$block['attrs'] ?? array(),
		array(
			'autoplay'            => false,
			'loop'                => false,
			'muted'               => false,
			'controls'            => true,
			'preload'             => 'metadata',
			'useAverageColor'     => true,
			'seekbarColor'        => '',
			'seekbarLoadingColor' => '',
			'seekbarPlayedColor'  => '',
			'maxWidth'            => '100%',
			'posterData'          => array(),
		)
	);
	unset( $attributes['align'], $attributes['poster'] );
	$poster = get_featured_image_url( $post_id );
	if ( $poster ) {
		$attributes['poster'] = $poster;
	}
	$block['attrs'] = $attributes;
	return $block;
}
add_filter( 'render_block_data', __NAMESPACE__ . '\player_attributes' );

/**
 * Supplies video thumbnails to the core Featured Image block.
 *
 * A real featured image is the poster and keeps core's sized markup; otherwise
 * the VideoPress thumbnail is used. Keeps core's image/link wrapper,
 * accessibility attributes and aspect ratio.
 *
 * @param string       $html          Existing thumbnail HTML.
 * @param int          $post_id       Post ID.
 * @param int          $thumbnail_id  Attachment ID (unused).
 * @param string|int[] $size          Requested size (unused).
 * @param string|array $attr          Image attributes from core.
 * @return string
 */
function thumbnail_html( $html, $post_id, $thumbnail_id, $size, $attr ) {
	if ( 'post' !== get_post_type( $post_id ) || ( $html && get_featured_image_url( $post_id ) ) ) {
		return $html;
	}
	$url = get_image_url( $post_id );
	if ( ! $url && $html ) {
		return $html;
	}
	if ( ! $url ) {
		$url = get_theme_file_uri( 'assets/images/placeholder.png' );
	}
	$attributes = wp_parse_args(
		$attr,
		array(
			'alt'      => '',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);
	$image      = new \WP_HTML_Tag_Processor( '<img>' );
	$image->next_tag();
	$image->set_attribute( 'src', esc_url_raw( $url ) );
	foreach ( array( 'alt', 'class', 'width', 'height', 'loading', 'decoding', 'style' ) as $name ) {
		if ( isset( $attributes[ $name ] ) ) {
			$value = 'style' === $name ? safecss_filter_attr( $attributes[ $name ] ) : $attributes[ $name ];
			$image->set_attribute( $name, $value );
		}
	}
	return $image->get_updated_html();
}
add_filter( 'post_thumbnail_html', __NAMESPACE__ . '\thumbnail_html', 10, 5 );

/**
 * Returns the URLs of a post's transcoded video files, best quality first.
 *
 * @param int|\WP_Post|null $post Post. Defaults to the current post.
 * @return string[]|null Null when the video has no details (same as wptv2).
 */
function get_file_urls( $post = null ) {
	$details = get_details( $post );
	if ( ! $details ) {
		return null;
	}

	$urls = array();
	if ( function_exists( 'video_url_by_format' ) ) {
		foreach ( array( 'fmt_hd', 'fmt_dvd', 'fmt_std', 'fmt1_ogg' ) as $format ) {
			$url = video_url_by_format( $details, $format );
			if ( $url ) {
				$urls[] = $url;
			}
		}
	}

	return $urls;
}

/**
 * Returns the original uploaded video attachment of a post.
 *
 * @param int|\WP_Post|null $post Post. Defaults to the current post.
 * @return \WP_Post|false
 */
function get_attachment( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}

	$videos = get_children(
		array(
			'post_parent'    => $post->ID,
			'post_mime_type' => 'video/%',
		)
	);

	return $videos ? reset( $videos ) : false;
}

/**
 * Returns the URL of the original uploaded video file of a post.
 *
 * @param int|\WP_Post|null $post Post. Defaults to the current post.
 * @return string|false
 */
function get_attachment_url( $post = null ) {
	$attachment = get_attachment( $post );
	return $attachment ? wp_get_attachment_url( $attachment->ID ) : false;
}

/**
 * Converts a `language` term slug to a WordPress locale.
 *
 * Mapping copied from wptv2 (see https://meta.trac.wordpress.org/ticket/1156).
 * It is known to be incomplete.
 *
 * @param string $slug Language term slug.
 * @return string|false
 */
function language_to_locale( $slug ) {
	static $locales = array(
		'bengali'                 => 'bn_BD',
		'bulgarianбългарски-език' => 'bg_BG',
		'catalan'                 => 'ca',
		'croatianhrvatski'        => 'hr',
		'czechcestina'            => 'cs_CZ',
		'danishdansk'             => 'da_DK',
		'dutchnederlands'         => 'nl_NL',
		'english'                 => 'en',
		'english-swahili'         => 'sw',
		'english-and-dutch'       => 'nl_NL',
		'finnishsuomi'            => 'fi',
		'frenchfrancais'          => 'fr_FR',
		'germandeutsch'           => 'de_DE',
		'greek-ελληνικά'          => 'el',
		'gujarati'                => 'gu',
		'hebrewעברית'             => 'he_IL',
		'hindi'                   => 'hi_IN',
		'indonesian'              => 'id_ID',
		'italianitaliano'         => 'it_IT',
		'ελληνικά'                => 'el',
		'japanese'                => 'ja',
		'japanese-and-english'    => 'ja',
		'japanese日本語'             => 'ja',
		'kananda'                 => 'kn',
		'lithuanian'              => 'lt_LT',
		'malay'                   => 'ms_MY',
		'malay-bahasa-melayu'     => 'ms_MY',
		'malayalam'               => 'ms_MY',
		'marathi'                 => 'mr',
		'maori'                   => 'mri',
		'nepaliनेपाली'            => 'ne_NP',
		'norwegiannorsk'          => 'nb_NO',
		'persian-farsi'           => 'fa_IR',
		'polishpolski'            => 'pl_PL',
		'portugueseportugues'     => 'pt_BR',
		'romanianromana'          => 'ro_RO',
		'russianрусский'          => 'ru_RU',
		'serbianсрпски'           => 'sr_RS',
		'slovakslovencina'        => 'sk_SK',
		'spanishespanol'          => 'es_ES',
		'swedishsvenska'          => 'sv_SE',
		'thai'                    => 'th',
		'traditional-chinese'     => 'zh_CN',
		'ukrainianукраїнська'     => 'uk',
		'urdu'                    => 'ur',
		'urdu-and-english'        => 'ur',
		'vietnamese'              => 'vi',
	);

	return $locales[ $slug ] ?? false;
}
