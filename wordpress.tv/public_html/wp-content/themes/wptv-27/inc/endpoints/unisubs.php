<?php
/**
 * Universal Subtitles endpoint (/unisubs/) and the `?guid=` redirect.
 *
 * Route contract (docs/routes.md). Ported from wptv2's WordCampTV_Unisubs.
 * - Any URL with `?guid=<VideoPress GUID>` redirects (302) to the video post.
 * - /unisubs/?guid=… or /unisubs/?url=… returns video data as JSON or XML.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Endpoints\Unisubs;

use WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the rewrite rule. Runs on init, priority 5, as in wptv2.
 */
function register() {
	add_rewrite_rule( 'unisubs/?(.*)', 'index.php?$matches[1]&unisubs=1', 'top' );
}
add_action( 'init', __NAMESPACE__ . '\register', 5 );

/**
 * Registers the `unisubs` and `guid` query vars.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function query_vars( $vars ) {
	$vars[] = 'unisubs';
	$vars[] = 'guid';
	return $vars;
}
add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );

/**
 * Sends a 404 response and exits.
 */
function not_found() {
	status_header( 404 );
	exit( 'Invalid request.' );
}

/**
 * Finds the published post that embeds a VideoPress GUID.
 *
 * @param string $guid VideoPress GUID.
 * @return \WP_Post|false
 */
function get_post_by_guid( $guid ) {
	$posts = get_posts(
		array(
			's'           => "[wpvideo $guid]",
			'post_status' => 'publish',
		)
	);
	if ( $posts ) {
		return $posts[0];
	}

	// Keep the legacy search first. Only valid block GUIDs enter the fallback.
	if ( ! is_string( $guid ) || ! preg_match( '/^[A-Za-z0-9]{8}$/D', $guid ) ) {
		return false;
	}
	$page = 1;
	do {
		$candidates = get_posts(
			array(
				's'              => $guid,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'paged'          => $page++,
			)
		);
		$count      = count( $candidates );
		foreach ( $candidates as $candidate ) {
			// A textual mention, cached iframe or shortcode is not a block match.
			if ( false === stristr( $candidate->post_content, '[wpvideo' ) && Video\get_guid( $candidate ) === $guid ) {
				return $candidate;
			}
		}
	} while ( 100 === $count );
	return false;
}

/**
 * Handles the `?guid=` redirect and /unisubs/ requests. Runs on template_redirect, priority 2.
 */
function handle_request() {
	$guid_var = get_query_var( 'guid' );

	// ?guid= on any URL other than /unisubs/ redirects to the video post.
	if ( $guid_var && ! get_query_var( 'unisubs' ) ) {
		$post = get_post_by_guid( $guid_var );
		if ( $post ) {
			wp_safe_redirect( get_permalink( $post ) );
			exit;
		}
	}

	if ( ! get_query_var( 'unisubs' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint.
	if ( ! isset( $_GET['guid'] ) && ! isset( $_GET['url'] ) ) {
		not_found();
	}

	$post    = false;
	$post_id = 0;

	if ( isset( $_GET['guid'] ) ) {
		$post = get_post_by_guid( sanitize_text_field( wp_unslash( $_GET['guid'] ) ) );
		if ( ! $post ) {
			not_found();
		}
	}

	if ( isset( $_GET['url'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared and resolved with url_to_postid().
		$url = wp_unslash( $_GET['url'] );
		if ( substr( $url, 0, strlen( home_url() ) ) !== home_url() ) {
			not_found();
		}

		$post_id = url_to_postid( $url );
		$post    = $post_id ? get_post( $post_id ) : false;
		if ( ! $post ) {
			not_found();
		}
	}

	// url_to_postid() resolves ?p= URLs of any status, and ?guid= finds published
	// posts with a password too: only describe public videos.
	if ( ! is_post_publicly_viewable( $post ) || '' !== $post->post_password ) {
		not_found();
	}

	$format = ! empty( $_GET['format'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['format'] ) ) ) : 'json';
	// phpcs:enable

	// Only VideoPress videos are supported.
	$has_shortcode = false !== stristr( $post->post_content, '[wpvideo' );
	if ( ! $has_shortcode && ! Video\get_guid( $post ) ) {
		status_header( 501 );
		exit( 'Support has not been added for this non-VideoPress type video yet.' );
	}

	$data = array(
		'type'      => 'video',
		'version'   => '1.0',
		// Known wptv2 bug, kept in Phase 1: $post_id is 0 for ?guid= requests, so
		// this is the permalink of the global post. To be fixed (docs/backlog.md, B2).
		'permalink' => esc_url_raw( get_permalink( $post_id ) ),
	);

	// Preserve the endpoint's first-shortcode parser for existing content.
	preg_match( '#\[wpvideo ([a-zA-Z0-9]+)#i', $post->post_content, $guid );
	$guid = $has_shortcode ? ( $guid[1] ?? '' ) : Video\get_guid( $post );
	$info = ( $guid && function_exists( 'video_get_info_by_guid' ) ) ? video_get_info_by_guid( $guid ) : false;
	if ( ! $info ) {
		status_header( 500 );
		exit( 'An error has occurred on our end. Please contact WordPress.com support.' );
	}

	// Best available thumbnail.
	$thumb = false;
	foreach ( array( 'hd_files', 'dvd_files', 'std_files' ) as $key ) {
		if ( isset( $info->{$key} ) && ! $thumb ) {
			$files = maybe_unserialize( $info->{$key} );
			$thumb = $files['original_img'] ?? false;
		}
	}

	$data['guid']        = $info->guid;
	$data['post_id']     = $post->ID;
	$data['title']       = ! empty( $post->post_title ) ? $post->post_title : 'Untitled';
	$data['description'] = ! empty( $info->description ) ? $info->description : 'No description given.';
	$data['thumbnail']   = esc_url_raw( sprintf( 'http://videos.videopress.com/%s/%s', $info->guid, $thumb ) );
	$data['width']       = absint( $info->width );
	$data['height']      = absint( $info->height );
	$data['duration']    = absint( $info->duration );
	$data['swf']         = esc_url_raw( sprintf( 'http://v.wordpress.com/wp-content/plugins/video/assets/player.wptv.swf?guid=%s', $info->guid ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint.
	if ( isset( $_GET['redirect_to_swf'] ) ) {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed v.wordpress.com URL built above.
		wp_redirect( $data['swf'] );
		exit;
	}

	switch ( $format ) {
		case 'json':
			header( 'Content-Type: application/json' );
			echo wp_json_encode( $data );
			exit;
		case 'xml':
			header( 'Content-Type: text/xml' );
			echo '<' . '?xml version="1.0" encoding="utf-8" standalone="yes"?>' . "\n";
			echo "<item>\n";
			foreach ( $data as $tag => $value ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal tag names; values are escaped with htmlspecialchars().
				echo "\t<{$tag}>" . htmlspecialchars( (string) $value ) . "</{$tag}>\n";
			}
			echo '</item>';
			exit;
		case 'array':
			if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug format, WordPress.com sandboxes only.
				print_r( $data );
				exit;
			}
			break;
		default:
			status_header( 501 );
			exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\handle_request', 2 );
