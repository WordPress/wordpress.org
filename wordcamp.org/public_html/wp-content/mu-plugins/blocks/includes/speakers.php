<?php
namespace WordCamp\Blocks\Speakers;
defined( 'WPINC' ) || die();

use WP_Post;
use WordCamp\Blocks;

/**
 * Register block types and enqueue scripts.
 *
 * @return void
 */
function init() {
	register_block_type(
		'wordcamp/speakers',
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
	$defaults   = wp_list_pluck( get_attributes_schema(), 'default' );
	$attributes = wp_parse_args( $attributes, $defaults );

	$speakers = get_speaker_posts( $attributes );

	$sessions = [];
	if ( ! empty( $speakers ) && true === $attributes['show_session'] ) {
		$sessions = get_speaker_sessions( wp_list_pluck( $speakers, 'ID' ) );
	}

	$container_classes = [
		'wordcamp-speakers-block',
		'layout-' . sanitize_html_class( $attributes['layout'] ),
		sanitize_html_class( $attributes['className'] ),
	];

	if ( 'grid' === $attributes['layout'] ) {
		$container_classes[] = 'grid-columns-' . absint( $attributes['grid_columns'] );
	}

	$container_classes = implode( ' ', $container_classes );

	ob_start();
	require Blocks\PLUGIN_DIR . 'view/speakers.php';
	$html = ob_get_clean();

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
	$data['speakers'] = [
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
function get_speaker_posts( array $attributes ) {
	$post_args = [
		'post_type'      => 'wcb_speaker',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	];

	$sort = explode( '_', $attributes['sort'] );

	if ( 2 === count( $sort ) ) {
		$post_args['orderby'] = $sort[0];
		$post_args['order']   = $sort[1];
	}

	switch ( $attributes['mode'] ) {
		case 'specific_posts':
			$post_args['post__in'] = $attributes['post_ids'];
			break;

		case 'specific_terms':
			$post_args['tax_query'] = [
				[
					'taxonomy' => 'wcb_speaker_group',
					'field'    => 'id',
					'terms'    => $attributes['term_ids'],
				],
			];
			break;
	}

	return get_posts( $post_args );
}

/**
 * Get session posts grouped by speaker.
 *
 * @param array $speaker_ids
 *
 * @return array
 */
function get_speaker_sessions( array $speaker_ids ) {
	$sessions_by_speaker = [];

	$session_args = [
		'post_type'      => 'wcb_session',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_wcpt_session_time',
		'orderby'        => 'meta_value_num',
	];

	$session_posts = get_posts( $session_args );

	foreach ( $session_posts as $session ) {
		$session_speaker_ids = get_post_meta( $session->ID, '_wcpt_speaker_id', false );

		foreach ( $session_speaker_ids as $speaker_id ) {
			if ( in_array( $speaker_id, $speaker_ids, true ) ) {
				if ( ! isset( $sessions_by_speaker[ $speaker_id ] ) ) {
					$sessions_by_speaker[ $speaker_id ] = [];
				}

				$sessions_by_speaker[ $speaker_id ][] = $session;
			}
		}
	}

	return $sessions_by_speaker;
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
		'post_ids'     => [
			'type'    => 'array',
			'default' => [],
			'items'   => [
				'type' => 'integer',
			],
		],
		'term_ids'     => [
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
				'label' => _x( 'List all speakers', 'mode option', 'wordcamporg' ),
				'value' => 'all',
			],
			[
				'label' => _x( 'Choose speakers', 'mode option', 'wordcamporg' ),
				'value' => 'specific_posts',
			],
			[
				'label' => _x( 'Choose groups', 'mode option', 'wordcamporg' ),
				'value' => 'specific_terms',
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
		if ( ! empty( $options[ $type ] ) ) {
			return $options[ $type ];
		} else {
			return [];
		}
	}

	return $options;
}

/**
 * Get the full content of a post, ignoring more and noteaser tags and pagination.
 *
 * This works similarly to `the_content`, including applying filters, but:
 * - It skips all of the logic in `get_the_content` that deals with tags like <!--more--> and
 *   <!--noteaser-->, as well as pagination and global state variables like `$page`, `$more`, and
 *   `$multipage`.
 * - It returns a string of content, rather than echoing it.
 *
 * @param int|WP_Post $post Post ID or post object.
 *
 * @return string The full, filtered post content.
 */
function get_all_the_content( $post ) {
	$post = get_post( $post );

	$content = $post->post_content;

	/** This filter is documented in wp-includes/post-template.php */
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );

	return $content;
}
