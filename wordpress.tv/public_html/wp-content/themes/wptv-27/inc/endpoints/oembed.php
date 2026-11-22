<?php
/**
 * Legacy oEmbed provider: /oembed/?url=…&format=(json|xml)
 *
 * Route contract (docs/routes.md). Ported from wptv2's WordCampTV_oEmbed.
 * It returns `video` responses with the VideoPress player. Core's own oEmbed
 * endpoint (/wp-json/oembed/1.0/) keeps working alongside it.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Endpoints\Oembed;

use WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the rewrite rule. Runs on init, priority 5, as in wptv2.
 */
function register() {
	add_rewrite_rule( 'oembed/?(.*)', 'index.php?$matches[1]&oembed=1', 'top' );
}
add_action( 'init', __NAMESPACE__ . '\register', 5 );

/**
 * Registers the `oembed` query var.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function query_vars( $vars ) {
	$vars[] = 'oembed';
	return $vars;
}
add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );

/**
 * Sends a 404 response and exits.
 */
function not_found() {
	status_header( 404 );
	exit( 'Missing or invalid URL parameter.' );
}

/**
 * Handles oEmbed requests. Runs on template_redirect, priority 2.
 */
function handle_request() {
	if ( ! get_query_var( 'oembed' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint.
	if ( empty( $_GET['url'] ) || ! is_string( $_GET['url'] ) ) {
		not_found();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared and resolved with url_to_postid().
	$url        = wp_unslash( $_GET['url'] );
	$lookup_url = is_ssl() ? str_replace( 'http:', 'https:', $url ) : str_replace( 'https:', 'http:', $url );

	// The URL must belong to this site.
	$home = get_bloginfo( 'url' );
	if ( substr( $lookup_url, 0, strlen( $home ) ) !== $home ) {
		not_found();
	}

	$post_id = url_to_postid( $lookup_url );
	$post    = get_post( $post_id );
	if ( empty( $post_id ) || ! $post ) {
		not_found();
	}

	// url_to_postid() resolves ?p= URLs of any status: only embed public videos.
	if ( ! is_post_publicly_viewable( $post ) || '' !== $post->post_password ) {
		not_found();
	}

	$maxwidth  = ! empty( $_GET['maxwidth'] ) ? (int) $_GET['maxwidth'] : 0;
	$maxheight = ! empty( $_GET['maxheight'] ) ? (int) $_GET['maxheight'] : 0;
	$maxwidth  = $maxwidth ? $maxwidth : 400;
	$maxheight = $maxheight ? $maxheight : 300;

	// Case-sensitive, as in wptv2: only lowercase json and xml are supported.
	$format = ! empty( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'json';
	// phpcs:enable

	// Only VideoPress videos are supported.
	$has_shortcode = false !== stristr( $post->post_content, '[wpvideo' );
	if ( ! $has_shortcode && ! Video\get_guid( $post ) ) {
		status_header( 501 );
		exit( 'Support has not been added for this non-VideoPress type video yet.' );
	}

	// Preserve the endpoint's first-shortcode parser, including malformed input.
	preg_match( '#\[wpvideo ([a-zA-Z0-9]+)#i', $post->post_content, $guid );
	$guid = $has_shortcode ? ( $guid[1] ?? '' ) : Video\get_guid( $post );
	$info = ( $guid && function_exists( 'video_get_info_by_guid' ) ) ? video_get_info_by_guid( $guid ) : false;
	if ( ! $info ) {
		status_header( 500 );
		exit( 'An error has occurred on our end. Please contact WordPress.com support.' );
	}

	list( $width, $height ) = wp_expand_dimensions( $info->width, $info->height, $maxwidth, $maxheight );

	$data = array(
		'type'    => 'video',
		'version' => '1.0',
		'title'   => $info->title ?? null,
		'width'   => $width,
		'height'  => $height,
		'html'    => function_exists( 'videopress_2015_player_get_html' )
			? videopress_2015_player_get_html(
				array(
					'guid'   => $info->guid,
					'width'  => $width,
					'height' => $height,
				)
			)
			: '',
	);

	switch ( $format ) {
		case 'json':
			header( 'Content-Type: application/json' );
			echo wp_json_encode( $data );
			exit;
		case 'xml':
			header( 'Content-Type: text/xml' );
			echo '<' . '?xml version="1.0" encoding="utf-8" standalone="yes"?>' . "\n";
			echo "<oembed>\n";
			foreach ( $data as $tag => $value ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal tag names; values are escaped with htmlspecialchars().
				echo "\t<{$tag}>" . htmlspecialchars( (string) $value ) . "</{$tag}>\n";
			}
			echo '</oembed>';
			exit;
		default:
			status_header( 501 );
			exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\handle_request', 2 );

/**
 * Prints oEmbed discovery links on single posts that embed a VideoPress video.
 */
function discovery_links() {
	if ( ! is_single() || ( false === stristr( get_post()->post_content, '[wpvideo' ) && ! Video\get_guid() ) ) {
		return;
	}

	foreach ( array(
		'json' => 'application/json+oembed',
		'xml'  => 'text/xml+oembed',
	) as $format => $type ) {
		printf(
			'<link rel="alternate" type="%1$s" href="%2$s" title="%3$s" />' . "\n",
			esc_attr( $type ),
			esc_url(
				add_query_arg(
					array(
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Keeps the wptv2 discovery links byte for byte.
						'url'    => urlencode( get_permalink() ),
						'format' => $format,
					),
					home_url( '/oembed/' )
				)
			),
			the_title_attribute( array( 'echo' => false ) )
		);
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\discovery_links' );
