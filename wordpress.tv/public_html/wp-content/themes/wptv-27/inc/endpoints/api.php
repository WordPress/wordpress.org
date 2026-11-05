<?php
/**
 * Legacy JSON API: /api/{method}.{format}
 *
 * Route contract (docs/routes.md). Ported from wptv2's WordPressTV_REST_API.
 * The response format must not change in Phase 1: external consumers depend on it.
 *
 * Methods:
 * - videos: the posts of the main query. Accepts query vars such as `event`,
 *   `paged` and `posts_per_page`.
 * - events, speakers, languages, tags, categories: terms, 200 per page, `paged`.
 *
 * Formats: `json`, and `array` (print_r, WordPress.com sandboxes only).
 *
 * @package WordPressTV
 */

namespace WordPressTV\Endpoints\Api;

use WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the rewrite rule. Runs on init, priority 5, as in wptv2.
 */
function register() {
	add_rewrite_rule( 'api/(.*)', 'index.php?wptvapi=$matches[1]', 'top' );
}
add_action( 'init', __NAMESPACE__ . '\register', 5 );

/**
 * Registers the `wptvapi` query var.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function query_vars( $vars ) {
	$vars[] = 'wptvapi';
	return $vars;
}
add_filter( 'query_vars', __NAMESPACE__ . '\query_vars' );

/**
 * Sends an error response and exits.
 *
 * @param string $message   Plain-text message.
 * @param int    $http_code HTTP status code.
 */
function error( $message, $http_code = 404 ) {
	status_header( $http_code );
	exit( esc_html( $message ) );
}

/**
 * Returns the term ID of a top-level category branch, looked up by slug.
 *
 * The wptv2 theme hardcoded the production IDs (year = 91093, location = 6418).
 *
 * @param string $slug Category slug.
 * @return int 0 when the category does not exist.
 */
function get_branch_id( $slug ) {
	static $ids = array();
	if ( ! isset( $ids[ $slug ] ) ) {
		$term         = get_category_by_slug( $slug );
		$ids[ $slug ] = $term ? (int) $term->term_id : 0;
	}
	return $ids[ $slug ];
}

/**
 * Handles API requests. Runs on template_redirect, priority 2.
 */
function handle_request() {
	$request = get_query_var( 'wptvapi' );
	if ( ! $request ) {
		return;
	}

	if ( ! preg_match( '/^(.+)\.(json|array)$/i', $request, $matches ) ) {
		error( 'Invalid request.' );
	}

	$method = $matches[1];
	$format = $matches[2];

	switch ( $method ) {
		case 'videos':
			$response = get_videos_response();
			break;
		case 'events':
		case 'speakers':
		case 'languages':
		case 'tags':
		case 'categories':
			$response = get_terms_response( $method );
			break;
		default:
			error( 'Unknown method.' );
	}

	if ( empty( $response ) ) {
		error( 'Empty response.' );
	}

	if ( 'json' === $format ) {
		header( 'Content-type: application/json' );
		echo wp_json_encode( $response );
	} elseif ( 'array' === $format && defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
		header( 'Content-Type: text/plain' );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug format, WordPress.com sandboxes only.
		print_r( $response );
	}
	exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\handle_request', 2 );

/**
 * Builds the `videos` response from the main query.
 *
 * @return array
 */
function get_videos_response() {
	global $wp_query;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only endpoint.
	if ( isset( $_REQUEST['posts_per_page'] ) ) {
		// phpcs:ignore WordPress.WP.DiscouragedFunctions.query_posts_query_posts, WordPress.Security.NonceVerification.Recommended -- Re-runs the main query with a custom page size, as wptv2 did.
		query_posts( array_merge( $wp_query->query, array( 'posts_per_page' => intval( $_REQUEST['posts_per_page'] ) ) ) );
	}

	$response = array(
		'query'  => array(
			'results' => (int) $wp_query->found_posts,
			'pages'   => (int) $wp_query->max_num_pages,
		),
		'videos' => array(),
	);

	while ( have_posts() ) {
		the_post();
		$response['videos'][] = get_video_item( get_post() );
	}

	return $response;
}

/**
 * Builds one item of the `videos` response.
 *
 * @param \WP_Post $post Post.
 * @return array
 */
function get_video_item( $post ) {
	$video = array(
		'title'       => $post->post_title,
		'permalink'   => get_permalink( $post ),
		'thumbnail'   => esc_url_raw( trim( (string) Video\get_image_url( $post ) ) ),
		'date'        => $post->post_date_gmt,
		'description' => get_the_excerpt(),
		'slides'      => get_post_meta( $post->ID, '_wptv_slides_url', true ),
		'speakers'    => array(),
		'event'       => array(),
		'language'    => array(),
		'tags'        => array(),
		'category'    => array(),
		'year'        => array(),
		'location'    => array(),
		'producer'    => array(),
		'video'       => array(
			'mp4'      => array(),
			'ogg'      => array(),
			'original' => false,
		),
		'subtitles'   => array(),
	);

	$year_id     = get_branch_id( 'year' );
	$location_id = get_branch_id( 'location' );

	// 'tags' is not a taxonomy, so it always stays empty (same as wptv2).
	foreach ( array( 'speakers', 'event', 'language', 'tags', 'category' ) as $taxonomy ) {
		$terms = get_the_terms( $post->ID, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			$item = array(
				'slug' => $term->slug,
				'name' => $term->name,
				'link' => get_term_link( $term ),
			);

			if ( 'category' === $taxonomy && $year_id && $year_id === (int) $term->parent ) {
				$video['year'] = $item;
			} elseif ( 'category' === $taxonomy && $location_id && $location_id === (int) $term->parent ) {
				$video['location'] = $item;
			} else {
				$video[ $taxonomy ][] = $item;
			}
		}
	}

	$producer = get_the_terms( $post->ID, 'producer' );
	if ( $producer && ! is_wp_error( $producer ) ) {
		$video['producer']['name'] = $producer[0]->name;
	}
	$producer_username = get_the_terms( $post->ID, 'producer-username' );
	if ( $producer_username && ! is_wp_error( $producer_username ) ) {
		$video['producer']['username'] = $producer_username[0]->name;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- urlencode() (not rawurlencode()) keeps the wptv2 output byte for byte.
		$video['producer']['link'] = 'https://profiles.wordpress.org/' . urlencode( $producer_username[0]->name ) . '/';
	}

	$attachment_url = Video\get_attachment_url( $post );
	if ( $attachment_url ) {
		$video['video']['original'] = $attachment_url;
	}

	return add_videopress_files( $video, $post );
}

/**
 * Adds VideoPress file URLs and subtitles to a video item (WordPress.com only).
 *
 * @param array    $video Video item.
 * @param \WP_Post $post  Post.
 * @return array
 */
function add_videopress_files( $video, $post ) {
	if ( ! function_exists( 'video_get_info_by_guid' ) ) {
		return $video;
	}

	$guid = Video\get_integration_guid( $post );
	if ( ! $guid ) {
		return $video;
	}

	$info = video_get_info_by_guid( $guid );
	if ( ! is_object( $info ) ) {
		return $video;
	}
	$api_data = function_exists( 'video_get_single_response' ) ? video_get_single_response( $info ) : array();

	// Original uploaded file, may vary in format.
	if ( empty( $video['video']['original'] ) ) {
		$video['video']['original'] = $api_data['original'] ?? null;
	}

	// Ogg: no longer generated since May 2021.
	$ogg = function_exists( 'video_highest_resolution_ogg' ) ? video_highest_resolution_ogg( $info ) : false;
	if ( $ogg ) {
		$video['video']['ogg']['low'] = $ogg;
	}

	// MP4: HLS-only formats have no audio in the MP4 file, so they are skipped.
	if ( function_exists( 'video_url_by_format' ) ) {
		foreach ( array(
			'low'  => 'fmt_std',
			'med'  => 'fmt_dvd',
			'high' => 'fmt_hd',
		) as $field => $format ) {
			if ( ! empty( $api_data['files'][ str_replace( 'fmt_', '', $format ) ]['hls'] ) ) {
				continue;
			}
			$video['video']['mp4'][ $field ] = video_url_by_format( $info, $format );
		}
	}

	$video['subtitles'] = (array) ( $api_data['subtitles'] ?? array() );

	return $video;
}

/**
 * Builds a terms response (events, speakers, languages, tags, categories).
 *
 * @param string $method API method.
 * @return array
 */
function get_terms_response( $method ) {
	$taxonomies = array(
		'events'     => 'event',
		'speakers'   => 'speakers',
		'languages'  => 'language',
		'tags'       => 'post_tag',
		'categories' => 'category',
	);

	$taxonomy     = $taxonomies[ $method ];
	$taxonomy_obj = get_taxonomy( $taxonomy );
	$total_count  = (int) wp_count_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	$page         = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
	$per_page     = 200;

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => $per_page * ( $page - 1 ),
			'orderby'    => 'id',
			'order'      => 'DESC',
		)
	);

	$response = array(
		'query' => array(
			'results' => $total_count,
			'page'    => $page,
			'pages'   => ceil( $total_count / $per_page ),
		),
		$method => array(),
	);

	foreach ( $terms as $term ) {
		$item = array(
			'id'     => $term->term_id,
			'name'   => $term->name,
			'link'   => get_term_link( $term ),
			'api'    => add_query_arg( $taxonomy_obj->query_var, $term->slug, home_url( '/api/videos.json' ) ),
			'videos' => $term->count,
		);

		if ( 'event' === $term->taxonomy ) {
			$item['youtube_playlist_id'] = get_option( "term_meta_{$term->term_id}_youtube_playlist_id", '' );
			$item['hashtag']             = get_option( "term_meta_{$term->term_id}_hashtag", '' );
		}

		$response[ $method ][] = $item;
	}

	return $response;
}
