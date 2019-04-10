<?php

namespace WordCamp\Blocks\Sessions;
use WordCamp\Blocks;
use function WordCamp\Blocks\Shared\Components\{ render_grid_layout };
use function WordCamp\Blocks\Shared\Definitions\{ get_shared_definitions, get_shared_definition };

defined( 'WPINC' ) || die();

/**
 * Register block types and enqueue scripts.
 *
 * @return void
 */
function init() {
	register_block_type(
		'wordcamp/sessions',
		[
			'attributes'      => get_attributes_schema(),
			'render_callback' => __NAMESPACE__ . '\render',
			'editor_script'   => 'wordcamp-blocks',
			'editor_style'    => 'wordcamp-blocks',
			'style'           => 'wordcamp-blocks',
		]
	);
}

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Render the block on the front end.
 *
 * @param array $attributes Block attributes.
 *
 * @return string
 */
function render( $attributes ) {
	if ( ! $attributes['mode'] ) {
		return;
	}

	$html       = '';
	$defaults   = wp_list_pluck( get_attributes_schema(), 'default' );
	$attributes = wp_parse_args( $attributes, $defaults );
	$sessions   = get_session_posts( $attributes );

	$speakers = [];
	if ( ! empty( $sessions ) && true === $attributes['show_speaker'] ) {
		$speakers = get_session_speakers( wp_list_pluck( $sessions, 'ID' ) );
	}

	$container_classes = [
		'wordcamp-block',
		'wordcamp-block-post-list',
		'wordcamp-sessions-block',
		sanitize_html_class( $attributes['className'] ),
	];

	$rendered_session_posts = [];
	foreach ( $sessions as $session ) {
		ob_start();
		require Blocks\PLUGIN_DIR . 'view/sessions.php';
		$rendered_session_posts[] = ob_get_clean();
	}

	$html = render_grid_layout(
		$attributes['layout'],
		$attributes['grid_columns'],
		$rendered_session_posts,
		$container_classes
	);

	return $html;
}

/**
 * Add data to be used by the JS scripts in the block editor.
 *
 * @param array $data
 *
 * @return array
 */
function add_script_data( array $data ) {
	$data['sessions'] = [
		'schema'  => get_attributes_schema(),
		'options' => get_options(),
	];

	return $data;
}

add_filter( 'wordcamp_blocks_script_data', __NAMESPACE__ . '\add_script_data' );

/**
 * Get the schema for the block's attributes.
 *
 * @return array
 */
function get_attributes_schema() {
	$schema = array_merge(
		get_shared_definitions(
			[
				'content',
				'grid_columns',
				'item_ids',
				'layout',
			],
			'attribute'
		),
		[
			'className'            => get_shared_definition( 'string_empty', 'attribute' ),
			'excerpt_more'         => get_shared_definition( 'boolean_false', 'attribute' ),
			'featured_image_width' => array(
				'type'    => 'integer',
				'default' => 150,
			),
			'image_align'          => get_shared_definition( 'align_image', 'attribute' ),
			'image_size'           => [
				'type'    => 'integer',
				'minimum' => 25,
				'maximum' => 600,
				'default' => 150,
			],
			'mode'                 => [
				'type'    => 'string',
				'enum'    => wp_list_pluck( get_options( 'mode' ), 'value' ),
				'default' => '',
			],
			'show_category'        => get_shared_definition( 'boolean_false', 'attribute' ),
			'show_images'          => get_shared_definition( 'boolean_true', 'attribute' ),
			'show_meta'            => get_shared_definition( 'boolean_false', 'attribute' ),
			'show_speaker'         => get_shared_definition( 'boolean_false', 'attribute' ),
			'sort'                 => [
				'type'    => 'string',
				'enum'    => wp_list_pluck( get_options( 'sort' ), 'value' ),
				'default' => 'session_time',
			],
		]
	);

	return $schema;
}

/**
 * Get the label/value pairs for all options or a specific type.
 *
 * @param string $type
 *
 * @return array
 */
function get_options( $type = '' ) {
	$options = array_merge(
		get_shared_definitions(
			[
				'align_block',
				'align_image',
				'content',
				'layout',
			],
			'option'
		),
		[
			'mode' => [
				[
					'label' => '',
					'value' => '',
				],
				[
					'label' => _x( 'List all sessions', 'mode option', 'wordcamporg' ),
					'value' => 'all',
				],
				[
					'label' => _x( 'Choose sessions', 'mode option', 'wordcamporg' ),
					'value' => 'wcb_session',
				],
				[
					'label' => _x( 'Choose tracks', 'mode option', 'wordcamporg' ),
					'value' => 'wcb_track',
				],
				[
					'label' => _x( 'Choose session categories', 'mode option', 'wordcamporg' ),
					'value' => 'wcb_session_category',
				],
			],
			'sort' => array_merge(
				[
					[
						'label' => _x( 'Day and Time', 'sort option', 'wordcamporg' ),
						'value' => 'session_time',
					],
				],
				get_shared_definition( 'sort_title', 'option' ),
				get_shared_definition( 'sort_date', 'option' )
			),
		]
	);

	if ( $type ) {
		if ( ! empty( $options[ $type ] ) ) {
			return $options[ $type ];
		} else {
			return [];
		}
	}

	return $options;
}

/**
 * Get the posts to display in the block.
 *
 * @param array $attributes
 *
 * @return array
 */
function get_session_posts( array $attributes ) {
	$post_args = [
		'post_type'      => 'wcb_session',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'     => '_wcpt_session_type',
					'value'   => 'session',
					'compare' => '=',
				],
				[
					'key'     => '_wcpt_session_type',
					'value'   => '',
					'compare' => 'NOT EXISTS',
				],
			],
		],
	];

	switch ( $attributes['sort'] ) {
		case 'session_time':
			$post_args['meta_key'] = '_wcpt_session_time';
			$post_args['orderby']  = 'meta_value_num title';
			$post_args['order']    = 'asc';
			break;

		case 'title_asc':
		case 'title_desc':
		case 'date_desc':
		case 'date_asc':
			$sort = explode( '_', $attributes['sort'] );

			if ( 2 === count( $sort ) ) {
				$post_args['orderby'] = $sort[0];
				$post_args['order']   = $sort[1];
			}
			break;
	}

	switch ( $attributes['mode'] ) {
		case 'wcb_session':
			$post_args['post__in'] = $attributes['item_ids'];
			break;

		case 'wcb_track':
		case 'wcb_session_category':
			$post_args['tax_query'] = [
				[
					'taxonomy' => $attributes['mode'],
					'field'    => 'id',
					'terms'    => $attributes['item_ids'],
				],
			];
			break;
	}

	return get_posts( $post_args );
}

/**
 * Get speaker posts grouped by session.
 *
 * @param array $session_ids
 *
 * @return array
 */
function get_session_speakers( array $session_ids ) {
	$speakers_by_session = [];

	$session_args = [
		'post_type'      => 'wcb_session',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'post__in'       => $session_ids,
	];

	$session_posts = get_posts( $session_args );

	foreach ( $session_posts as $session ) {
		$speaker_ids = get_post_meta( $session->ID, '_wcpt_speaker_id', false );

		if ( ! empty( $speaker_ids ) ) {
			$speaker_args = [
				'post_type'      => 'wcb_speaker',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__in'       => $speaker_ids,
				'orderby'        => 'post__in',
				'order'          => 'ASC',
			];

			$speakers_by_session[ $session->ID ] = get_posts( $speaker_args );
		}
	}

	return $speakers_by_session;
}
