<?php

namespace WordCamp\Blocks\Sponsors;
use WordCamp\Blocks;
use function WordCamp\Blocks\Shared\Components\{ render_grid_layout };

defined( 'WPINC' ) || die();

/**
 * Register sponsor block and enqueue scripts.
 */
function init() {
	register_block_type(
		'wordcamp/sponsors',
		[
			'attributes'      => get_attributes_schema(),
			'editor_script'   => 'wordcamp-blocks',
			'editor_style'    => 'wordcamp-blocks',
			'style'           => 'wordcamp-blocks',
			'render_callback' => __NAMESPACE__ . '\render',
		]
	);
}

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Renders content of Sponsor block based on attributes.
 *
 * @param array $attributes
 *
 * @return false|string
 */
function render( $attributes ) {
	$sponsors = get_sponsor_posts( $attributes );

	$container_classes = array(
		'wordcamp-sponsors-block',
		'wordcamp-sponsors-list',
	);

	$rendered_sponsor_posts = array();

	foreach ( $sponsors as $sponsor ) {
		ob_start();
		require Blocks\PLUGIN_DIR . 'view/sponsors.php';
		$rendered_sponsor_posts[] = ob_get_clean();
	}

	$html = render_grid_layout( $attributes['layout'], $attributes['grid_columns'], $rendered_sponsor_posts, $container_classes );
	return $html;
}

/**
 * Return sponsor posts what will rendered based on attributes.
 *
 * @param array $attributes
 *
 * @return array
 */
function get_sponsor_posts( $attributes ) {
	if ( empty( $attributes['mode'] ) ) {
		return array();
	}

	$post_args = array(
		'post_type'      => 'wcb_sponsor',
		'post_status'    => 'publish',
		'posts_per_page' => - 1,
	);

	switch ( $attributes['mode'] ) {
		case 'specific_posts':
			$post_args['post__in'] = $attributes['post_ids'];
			break;
		case 'specific_terms':
			$post_args['tax_query'] = [
				[
					'taxonomy' => 'wcb_sponsor_level',
					'field'    => 'id',
					'terms'    => $attributes['term_ids'],
				],
			];
			break;
	}

	switch ( $attributes['sort_by'] ) {
		case 'name_asc':
			$post_args['orderby'] = 'title';
			$post_args['order']   = 'asc';
			break;
		case 'name_desc':
			$post_args['orderby'] = 'title';
			$post_args['order']   = 'desc';
			break;
		// We will deal with case `sponsor_level` later.
	}

	$posts = get_posts( $post_args );

	if ( 'sponsor_level' === $attributes['sort_by'] ) {
		usort( $posts, sponsor_level_sort( $posts ) );
	}

	return $posts;
}

/**
 * Helper function for sorting based on sponsor levels.
 *
 * @param array $posts Sponsor posts to sort.
 *
 * @return callable
 */
function sponsor_level_sort( $posts ) {
	$sponsor_level_order = get_option( 'wcb_sponsor_level_order' );
	$sponsor_terms_cache = array();

	//Build the terms cache.
	foreach ( $posts as $post ) {
		$sponsor_level_terms = get_the_terms( $post->ID, 'wcb_sponsor_level' );
		if ( is_array( $sponsor_level_terms ) ) {
			$sponsor_terms_cache[ $post->ID ] = wp_list_pluck( $sponsor_level_terms, 'term_id' )[0];
		} else {
			$sponsor_terms_cache[ $post->ID ] = array();
		}
	}

	return function ( $sponsor1, $sponsor2 ) use ( $sponsor_level_order, $sponsor_terms_cache ) {
		$index1 = array_search( $sponsor_terms_cache[ $sponsor1->ID ], $sponsor_level_order, true );
		$index2 = array_search( $sponsor_terms_cache[ $sponsor2->ID ], $sponsor_level_order, true );

		if ( false === $index1 && false === $index2 ) {
			return 0;
		}

		if ( false === $index1 ) {
			return 1;
		}

		if ( false === $index2 ) {
			return -1;
		}

		return $index1 - $index2;
	};
}

/**
 * Get attribute schema for Sponsor block
 *
 * @return array
 */
function get_attributes_schema() {
	return array(
		'mode'                  => array(
			'type' => 'string',
		),
		'post_ids'              => array(
			'type'    => 'array',
			'default' => array(),
			'items'   => array(
				'type' => 'integer',
			),
		),
		'term_ids'              => array(
			'type'    => 'array',
			'default' => array(),
			'items'   => array(
				'type' => 'integer',
			),
		),
		'sponsor_image_urls'    => array(
			'type'    => 'string',
			'default' => '{}',
		),
		'show_name'             => array(
			'type'    => 'bool',
			'default' => true,
		),
		'show_logo'             => array(
			'type'    => 'bool',
			'default' => true,
		),
		'content'               => array(
			'type'    => 'string',
			'default' => 'full',
		),
		'excerpt_more'          => array(
			'type'    => 'bool',
			'default' => true,
		),
		'grid_columns'          => array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 4,
			'default' => 1,
		),
		'layout'                => array(
			'type'    => 'string',
			'enum'    => array( 'list', 'grid' ),
			'default' => 'list',
		),
		'featured_image_height' => array(
			'type'    => 'integer',
			'default' => 150,
		),
		'featured_image_width'  => array(
			'type'    => 'integer',
			'default' => 150,
		),
		'sort_by'               => array(
			'type'    => 'string',
			'default' => 'name_asc',
		),
	);
}
