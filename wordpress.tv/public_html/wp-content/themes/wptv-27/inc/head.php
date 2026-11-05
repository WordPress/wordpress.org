<?php
/**
 * Document head output: canonical, rel prev/next, JSON-LD.
 *
 * Core still prints its own canonical on singular views (rel_canonical()).
 *
 * @package WordPressTV
 */

namespace WordPressTV\Head;

use WordPressTV\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints `<link rel="canonical">` on the front page and term archives.
 *
 * Paginated views point to their own page, as in wptv2.
 */
function canonical() {
	$canonical = false;

	if ( is_front_page() ) {
		$canonical = home_url( '/' );
	} elseif ( is_tax() || is_tag() || is_category() ) {
		$canonical = get_term_link( get_queried_object() );
		if ( is_wp_error( $canonical ) ) {
			$canonical = false;
		}
	}

	if ( $canonical && get_query_var( 'paged' ) > 1 ) {
		$canonical .= 'page/' . (int) get_query_var( 'paged' ) . '/';
	}

	if ( $canonical ) {
		printf( '<link rel="canonical" href="%s">', esc_url( $canonical ) );
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\canonical' );

/**
 * Prints `<link rel="prev">` and `<link rel="next">` on archives and search results.
 */
function prev_next() {
	global $wp_query;

	if ( ! is_archive() && ! is_search() ) {
		return;
	}

	$paged    = max( 1, (int) get_query_var( 'paged' ) );
	$max_page = (int) $wp_query->max_num_pages;

	if ( $paged - 1 >= 1 ) {
		printf( '<link rel="prev" href="%s">' . "\n", esc_url( get_pagenum_link( $paged - 1 ) ) );
	}

	if ( $paged + 1 <= $max_page ) {
		printf( '<link rel="next" href="%s">' . "\n", esc_url( get_pagenum_link( $paged + 1 ) ) );
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\prev_next' );

/**
 * Prints schema.org VideoObject data on single videos.
 */
function json_ld() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post  = get_post();
	$video = Video\get_details( $post );
	if ( ! $video ) {
		return;
	}

	$lang       = false;
	$lang_terms = get_the_terms( $post, 'language' );
	if ( $lang_terms && ! is_wp_error( $lang_terms ) ) {
		$lang = Video\language_to_locale( $lang_terms[0]->slug );
		$lang = $lang ? str_replace( '_', '-', $lang ) : false;
	}

	$data = array(
		'@type'            => 'VideoObject',
		'actor'            => array(),
		'name'             => get_the_title(),
		'url'              => get_the_permalink(),
		'contentUrl'       => Video\get_file_urls( $post ),
		'description'      => get_the_excerpt(),
		'duration'         => gmdate( '\P0\D\TH\Hi\Ms\S', (int) $video->duration ),
		'height'           => $video->height ?? 530,
		'inLanguage'       => $lang ? $lang : 'en',
		'isFamilyFriendly' => ( 'G' === $video->rating || 'PG' === $video->rating ),
		'recordedAt'       => array(),
		'thumbnailUrl'     => Video\get_image_url( $post ),
		'uploadDate'       => gmdate( 'Y-m-d', strtotime( get_the_date() ) ),
		'width'            => $video->width ?? 940,
	);

	$speakers = get_the_terms( $post, 'speakers' );
	if ( is_array( $speakers ) ) {
		foreach ( $speakers as $speaker ) {
			$data['actor'][] = array(
				'@type' => 'Person',
				'name'  => $speaker->name,
				'url'   => get_term_link( $speaker ),
			);
		}
	}

	// Drop empty values, as wptv2 did.
	$data = array_filter(
		$data,
		function ( $item ) {
			return '' !== $item && array() !== $item;
		}
	);

	printf(
		"\n" . '<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array( $data ),
			),
			JSON_PRETTY_PRINT
		)
	);
}
add_action( 'wp_head', __NAMESPACE__ . '\json_ld' );
