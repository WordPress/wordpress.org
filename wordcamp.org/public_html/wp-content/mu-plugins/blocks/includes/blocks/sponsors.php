<?php
namespace WordCamp\Blocks\Sponsors;

use WordCamp\Blocks;
use function WordCamp\Blocks\Components\{ render_post_list };
use function WordCamp\Blocks\Definitions\{ get_shared_definitions, get_shared_definition };

defined( 'WPINC' ) || die();

/**
 * Register sponsor block and enqueue scripts.
 */
function init() {
	register_block_type(
		'wordcamp/sponsors',
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
 * Renders content of Sponsor block based on attributes.
 *
 * @param array $attributes
 *
 * @return false|string
 */
function render( $attributes ) {
	$defaults   = wp_list_pluck( get_attributes_schema(), 'default' );
	$attributes = wp_parse_args( $attributes, $defaults );

	$sponsors               = get_sponsor_posts( $attributes );
	$rendered_sponsor_posts = [];

	foreach ( $sponsors as $sponsor ) {
		ob_start();
		require Blocks\PLUGIN_DIR . 'views/sponsor.php';
		$rendered_sponsor_posts[] = ob_get_clean();
	}

	$container_classes = [
		'wordcamp-sponsors-block',
		sanitize_html_class( $attributes['className'] ),
	];

	if ( ! empty( $attributes['align'] ) ) {
		$container_classes[] = 'align' . sanitize_html_class( $attributes['align'] );
	}

	return render_post_list( $rendered_sponsor_posts, $attributes['layout'], $attributes['grid_columns'], $container_classes );
}

/**
 * Add data to be used by the JS scripts in the block editor.
 *
 * @param array $data
 *
 * @return array
 */
function add_script_data( array $data ) {
	$data['sponsors'] = [
		'schema'  => get_attributes_schema(),
		'options' => get_options(),
	];

	return $data;
}

add_filter( 'wordcamp_blocks_script_data', __NAMESPACE__ . '\add_script_data' );

/**
 * Return sponsor posts what will rendered based on attributes.
 *
 * @param array $attributes
 *
 * @return array
 */
function get_sponsor_posts( $attributes ) {
	if ( empty( $attributes['mode'] ) ) {
		return [];
	}

	$post_args = array(
		'post_type'      => 'wcb_sponsor',
		'post_status'    => 'publish',
		'posts_per_page' => - 1,
	);

	$sort = explode( '_', $attributes['sort'] );

	if ( 2 === count( $sort ) ) {
		$post_args['orderby'] = $sort[0];
		$post_args['order']   = $sort[1];
	}

	switch ( $attributes['mode'] ) {
		case 'wcb_sponsor':
			$post_args['post__in'] = $attributes['item_ids'];
			break;

		case 'wcb_sponsor_level':
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
 * Get attribute schema for Sponsor block
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
			'align'                => get_shared_definition( 'align_block', 'attribute' ),
			'className'            => get_shared_definition( 'string_empty', 'attribute' ),
			'featured_image_width' => get_shared_definition( 'image_size', 'attribute', [ 'default' => 600 ] ),
			'image_align'          => get_shared_definition( 'align_image', 'attribute' ),
			'mode'                 => [
				'type'    => 'string',
				'enum'    => wp_list_pluck( get_options( 'mode' ), 'value' ),
				'default' => '',
			],
			'show_logo'            => get_shared_definition( 'boolean_true', 'attribute' ),
			'show_name'            => get_shared_definition( 'boolean_true', 'attribute' ),
			'sort'                 => [
				'type'    => 'string',
				'enum'    => wp_list_pluck( get_options( 'sort' ), 'value' ),
				'default' => 'title_asc',
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
					'label' => _x( 'List all sponsors', 'mode option', 'wordcamporg' ),
					'value' => 'all',
				],
				[
					'label' => _x( 'Choose sponsors', 'mode option', 'wordcamporg' ),
					'value' => 'wcb_sponsor',
				],
				[
					'label' => _x( 'Choose sponsor level', 'mode option', 'wordcamporg' ),
					'value' => 'wcb_sponsor_level',
				],
			],
			'sort' => array_merge(
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
