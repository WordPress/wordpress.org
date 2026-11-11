<?php
/**
 * PHP-only video metadata blocks. No editor build or third-party dependencies.
 *
 * @package WordPressTV
 */

namespace WordPressTV\VideoBlocks;

use WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the two metadata features that core blocks cannot express. */
function register_blocks() {
	foreach ( array(
		'producer'        => __( 'Producer', 'wptv' ),
		'video-downloads' => __( 'Video downloads and subtitles', 'wptv' ),
	) as $name => $title ) {
		register_block_type(
			'wptv/' . $name,
			array(
				'api_version'     => 3,
				'title'           => $title,
				'category'        => 'widgets',
				'uses_context'    => array( 'postId', 'postType' ),
				'supports'        => array(
					'autoRegister' => true,
					'html'         => false,
					'inserter'     => false,
				),
				'render_callback' => __NAMESPACE__ . ( 'producer' === $name ? '\\render_producer' : '\\render_downloads' ),
			)
		);
	}
}
add_action( 'init', __NAMESPACE__ . '\\register_blocks' );

/**
 * Renders the legacy producer credit, including its WordPress.org profile link.
 *
 * @param array     $attributes Block attributes.
 * @param string    $content    Saved block content.
 * @param \WP_Block $block      Block instance.
 * @return string
 */
function render_producer( $attributes, $content, $block ) {
	$post_id = (int) ( $block->context['postId'] ?? 0 );
	if ( ! $post_id || post_password_required( $post_id ) ) {
		return '';
	}
	$names = get_the_terms( $post_id, 'producer' );
	$users = get_the_terms( $post_id, 'producer-username' );
	$names = is_array( $names ) ? $names : array();
	$users = is_array( $users ) ? $users : array();
	if ( ! $names && ! $users ) {
		return '';
	}
	$label = $names ? $names[0]->name : $users[0]->name;
	$url   = $users ? 'https://profiles.wordpress.org/' . rawurlencode( $users[0]->name ) : get_term_link( $names[0] );
	if ( is_wp_error( $url ) ) {
		return '';
	}
	return '<div ' . get_block_wrapper_attributes() . '><h3>' . esc_html__( 'Producer', 'wptv' ) . '</h3><p><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p></div>';
}

/**
 * Finds the sidebar video: first legacy shortcode, otherwise the block fallback.
 *
 * @param int $post_id Post ID.
 * @return object|false
 */
function download_video( $post_id ) {
	$post = $post_id ? get_post( $post_id ) : null;
	if ( ! $post || post_password_required( $post ) || ! function_exists( 'video_get_info_by_guid' ) ) {
		return false;
	}
	$guid = Video\get_integration_guid( $post );
	if ( ! $guid ) {
		return false;
	}
	$video = video_get_info_by_guid( $guid );
	return is_object( $video ) ? $video : false;
}

/**
 * Renders available file downloads and the subtitle contribution link.
 *
 * @param array     $attributes Block attributes.
 * @param string    $content    Saved block content.
 * @param \WP_Block $block      Block instance.
 * @return string
 */
function render_downloads( $attributes, $content, $block ) {
	$post_id = (int) ( $block->context['postId'] ?? 0 );
	$video   = $post_id ? download_video( $post_id ) : false;
	if ( ! $video ) {
		return '';
	}
	$output = file_links( $video, $post_id ) . subtitle_links( $video );
	return $output ? '<div ' . get_block_wrapper_attributes() . '>' . $output . '</div>' : '';
}

/**
 * Builds legacy MP4/OGG links, excluding HLS-only transcodes without audio.
 *
 * @param object $video   VideoPress details.
 * @param int    $post_id Post ID.
 * @return string
 */
function file_links( $video, $post_id ) {
	if ( ! function_exists( 'video_get_single_response' ) ) {
		return '';
	}
	$api_data = video_get_single_response( $video );
	$api_data = is_array( $api_data ) ? $api_data : array();
	$formats  = array(
		'fmt_std' => __( 'Low', 'wptv' ),
		'fmt_dvd' => __( 'Med', 'wptv' ),
		'fmt_hd'  => __( 'High', 'wptv' ),
	);
	$links    = array();
	foreach ( $formats as $format => $label ) {
		if ( ! function_exists( 'video_url_by_format' ) || ! empty( $api_data['files'][ str_replace( 'fmt_', '', $format ) ]['hls'] ) ) {
			continue;
		}
		$url = video_url_by_format( $video, $format );
		if ( $url ) {
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
	}
	$original = Video\get_attachment_url( $post_id );
	if ( ! $original ) {
		$original = $api_data['original'] ?? '';
	}
	if ( $original ) {
		$links[] = '<a href="' . esc_url( $original ) . '">' . esc_html__( 'Original', 'wptv' ) . '</a>';
	}
	$ogg = function_exists( 'video_highest_resolution_ogg' ) ? video_highest_resolution_ogg( $video ) : false;
	if ( ! $links && ! $ogg ) {
		return '';
	}
	$output = '<h3>' . esc_html__( 'Download', 'wptv' ) . '</h3>';
	if ( $links ) {
		$output .= '<p>MP4: ' . implode( ', ', $links ) . '</p>';
	}
	if ( $ogg ) {
		$output .= '<p>OGG: <a href="' . esc_url( $ogg ) . '">' . esc_html__( 'Low', 'wptv' ) . '</a></p>';
	}
	return $output;
}

/**
 * Builds legacy TTML links and the existing subtitle-upload URL.
 *
 * @param object $video VideoPress details.
 * @return string
 */
function subtitle_links( $video ) {
	if ( empty( $video->post_id ) || ! class_exists( 'VideoPress_Subtitles' ) || ! class_exists( 'VideoPress_Subtitles_Track' ) || ! is_callable( array( 'VideoPress_Subtitles', 'get_languages' ) ) ) {
		return '';
	}
	$languages = \VideoPress_Subtitles::get_languages();
	$languages = is_array( $languages ) ? $languages : array();
	$subtitles = (array) get_post_meta( $video->post_id, '_videopress_subtitles', true );
	$links     = array();
	foreach ( $subtitles as $track ) {
		if ( ! is_array( $track ) || empty( $track['subtitles_post_id'] ) || empty( $track['language'] ) || empty( $video->guid ) ) {
			continue;
		}
		$subtitle = new \VideoPress_Subtitles_Track(
			array(
				'guid'              => $video->guid,
				'language'          => $track['language'],
				'subtitles_post_id' => $track['subtitles_post_id'],
			)
		);
		if ( ! is_callable( array( $subtitle, 'url' ) ) ) {
			continue;
		}
		$url   = $subtitle->url();
		$label = $languages[ $track['language'] ]['localized_label'] ?? $track['language'];
		if ( $url ) {
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
	}
	$output = '<h3>' . esc_html__( 'Subtitles', 'wptv' ) . '</h3>';
	if ( $links ) {
		$output .= '<p>TTML: ' . implode( ', ', $links ) . '</p>';
	}
	$url = add_query_arg( 'video', (int) $video->post_id, home_url( 'subtitle/' ) );
	return $output . '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Subtitle this video', 'wptv' ) . ' &rarr;</a></p>';
}
