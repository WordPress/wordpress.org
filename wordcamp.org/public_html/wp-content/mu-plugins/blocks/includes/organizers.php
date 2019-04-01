<?php

namespace WordCamp\Blocks\Organizers;
use WordCamp\Blocks;

defined( 'WPINC' ) || die();


/**
 * Register block types and enqueue scripts.
 *
 * @return void
 */
function init() {
	register_block_type(
		'wordcamp/organizers',
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
	$html       = '';
	$defaults   = wp_list_pluck( get_attributes_schema(), 'default' );
	$attributes = wp_parse_args( $attributes, $defaults );
	$organizers = get_organizer_posts( $attributes );

	$container_classes = [
		'wordcamp-block',
		'wordcamp-block-post-list',
		'wordcamp-organizers-block',
		'layout-' . sanitize_html_class( $attributes['layout'] ),
		sanitize_html_class( $attributes['className'] ),
	];

	if ( 'grid' === $attributes['layout'] ) {
		$container_classes[] = 'grid-columns-' . absint( $attributes['grid_columns'] );
	}

	$container_classes = implode( ' ', $container_classes );

	if ( $attributes['mode'] ) {
		ob_start();
		require Blocks\PLUGIN_DIR . 'view/organizers.php';
		$html = ob_get_clean();
	}

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
	$data['organizers'] = [
		'schema'  => get_attributes_schema(),
		'options' => get_options(),
	];

	return $data;
}
add_filter( 'wordcamp_blocks_script_data', __NAMESPACE__ . '\add_script_data' );

/**
 * Get the posts to display in the block.
 *
 * @param array $attributes
 *
 * @return array
 */
function get_organizer_posts( array $attributes ) {
	$post_args = [
		'post_type'      => 'wcb_organizer',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	];

	$sort = explode( '_', $attributes['sort'] );

	if ( 2 === count( $sort ) ) {
		$post_args['orderby'] = $sort[0];
		$post_args['order']   = $sort[1];
	}

	switch ( $attributes['mode'] ) {
		case 'wcb_organizer':
			$post_args['post__in'] = $attributes['item_ids'];
			break;

		case 'wcb_organizer_team':
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
 * Get the schema for the block's attributes.
 *
 * @return array
 */
function get_attributes_schema() {
	return [
		'mode'         => [
			'type'    => 'string',
			'enum'    => wp_list_pluck( get_options( 'mode' ), 'value' ),
			'default' => '',
		],
		'item_ids'     => [
			'type'    => 'array',
			'default' => [],
			'items'   => [
				'type' => 'integer',
			],
		],
		'sort'         => [
			'type'    => 'string',
			'enum'    => wp_list_pluck( get_options( 'sort' ), 'value' ),
			'default' => 'title_asc',
		],
		'layout'       => [
			'type'    => 'string',
			'enum'    => wp_list_pluck( get_options( 'display' ), 'value' ),
			'default' => 'list',
		],
		'grid_columns' => [
			'type'    => 'integer',
			'minimum' => 2,
			'maximum' => 4,
			'default' => 2,
		],
		'className'    => [
			'type'    => 'string',
			'default' => '',
		],
		'show_avatars' => [
			'type'    => 'bool',
			'default' => true,
		],
		'avatar_size'  => [
			'type'    => 'integer',
			'minimum' => 25,
			'maximum' => 600,
			'default' => 150,
		],
		'avatar_align' => [
			'type'    => 'string',
			'enum'    => wp_list_pluck( get_options( 'align' ), 'value' ),
			'default' => 'none',
		],
		'content'      => [
			'type'    => 'string',
			'enum'    => wp_list_pluck( get_options( 'content' ), 'value' ),
			'default' => 'full',
		],
		'excerpt_more' => [
			'type'    => 'bool',
			'default' => false,
		],
		'show_session' => [
			'type'    => 'bool',
			'default' => false,
		],
	];
}

/**
 * Get the label/value pairs for all options or a specific type.
 *
 * @param string $type
 *
 * @return array
 */
function get_options( $type = '' ) {
	$options = [
		'align'   => [
			[
				'label' => _x( 'None', 'alignment option', 'wordcamporg' ),
				'value' => 'none',
			],
			[
				'label' => _x( 'Left', 'alignment option', 'wordcamporg' ),
				'value' => 'left',
			],
			[
				'label' => _x( 'Center', 'alignment option', 'wordcamporg' ),
				'value' => 'center',
			],
			[
				'label' => _x( 'Right', 'alignment option', 'wordcamporg' ),
				'value' => 'right',
			],
		],
		'content' => [
			[
				'label' => _x( 'Full', 'content option', 'wordcamporg' ),
				'value' => 'full',
			],
			[
				'label' => _x( 'Excerpt', 'content option', 'wordcamporg' ),
				'value' => 'excerpt',
			],
			[
				'label' => _x( 'None', 'content option', 'wordcamporg' ),
				'value' => 'none',
			],
		],
		'layout'  => [
			[
				'label' => _x( 'List', 'content option', 'wordcamporg' ),
				'value' => 'list',
			],
			[
				'label' => _x( 'Grid', 'content option', 'wordcamporg' ),
				'value' => 'grid',
			],
		],
		'mode'    => [
			[
				'label' => '',
				'value' => '',
			],
			[
				'label' => _x( 'List all organizers', 'mode option', 'wordcamporg' ),
				'value' => 'all',
			],
			[
				'label' => _x( 'Choose organizers', 'mode option', 'wordcamporg' ),
				'value' => 'wcb_organizer',
			],
			[
				'label' => _x( 'Choose teams', 'mode option', 'wordcamporg' ),
				'value' => 'wcb_organizer_team',
			],
		],
		'sort'    => [
			[
				'label' => _x( 'A → Z', 'sort option', 'wordcamporg' ),
				'value' => 'title_asc',
			],
			[
				'label' => _x( 'Z → A', 'sort option', 'wordcamporg' ),
				'value' => 'title_desc',
			],
			[
				'label' => _x( 'Newest to Oldest', 'sort option', 'wordcamporg' ),
				'value' => 'date_desc',
			],
			[
				'label' => _x( 'Oldest to Newest', 'sort option', 'wordcamporg' ),
				'value' => 'date_asc',
			],
		],
	];

	if ( $type ) {
		return empty( $options[ $type ] ) ? [] : $options[ $type ];
	}

	return $options;
}
