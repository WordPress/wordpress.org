<?php
namespace WordCamp\Blocks\Shared\Definitions;
defined( 'WPINC' ) || die();

/**
 * Retrieve an array of definitions for use within a block.
 *
 * Currently there are two types of definitions: attributes, which make up a block's schema, and options, which
 * are label/value pairs used in situations where the value of an attribute must come from an enumerated list.
 *
 * @param array|string $keys The keys of the definitions to retrieve. An array of strings, or 'all'.
 * @param string       $type The type of definition to retrieve. 'attribute' or 'option'.
 *
 * @return array
 */
function get_shared_definitions( $keys, $type ) {
	switch ( $type ) {
		case 'attribute':
			$definitions = [
				// Generic attributes.
				'boolean_false'     => [
					'type'    => 'bool',
					'default' => false,
				],
				'boolean_true'      => [
					'type'    => 'bool',
					'default' => true,
				],
				'string_empty'      => [
					'type'    => 'string',
					'default' => '',
				],
				// Specific attributes.
				'align_block'       => [
					'type'    => 'string',
					'enum'    => wp_list_pluck( get_shared_definition( 'align_block', 'option' ), 'value' ),
					'default' => '',
				],
				'align_image'       => [
					'type'    => 'string',
					'enum'    => wp_list_pluck( get_shared_definition( 'align_image', 'option' ), 'value' ),
					'default' => 'none',
				],
				'content'           => [
					'type'    => 'string',
					'enum'    => wp_list_pluck( get_shared_definition( 'content', 'option' ), 'value' ),
					'default' => 'full',
				],
				'grid_columns'      => [
					'type'    => 'integer',
					'minimum' => 2,
					'maximum' => 4,
					'default' => 2,
				],
				'image_size'        => [
					'type'    => 'integer',
					'minimum' => 100,
					'maximum' => 1024,
					'default' => 150,
				],
				'image_size_avatar' => [
					'type'    => 'integer',
					'minimum' => 25,
					'maximum' => 600,
					'default' => 150,
				],
				'item_ids'          => [
					'type'    => 'array',
					'default' => [],
					'items'   => [
						'type' => 'integer',
					],
				],
				'layout'            => [
					'type'    => 'string',
					'enum'    => wp_list_pluck( get_shared_definition( 'layout', 'option' ), 'value' ),
					'default' => 'list',
				],
			];
			break;

		case 'option':
			$definitions = [
				'align_block' => [
					[
						'label' => _x( 'Wide', 'alignment option', 'wordcamporg' ),
						'value' => 'wide',
					],
					[
						'label' => _x( 'Full', 'alignment option', 'wordcamporg' ),
						'value' => 'full',
					],
				],
				'align_image' => [
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
				'content'     => [
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
				'layout'      => [
					[
						'label' => _x( 'List', 'content option', 'wordcamporg' ),
						'value' => 'list',
					],
					[
						'label' => _x( 'Grid', 'content option', 'wordcamporg' ),
						'value' => 'grid',
					],
				],
				'sort_title'  => [
					[
						'label' => _x( 'A → Z', 'sort option', 'wordcamporg' ),
						'value' => 'title_asc',
					],
					[
						'label' => _x( 'Z → A', 'sort option', 'wordcamporg' ),
						'value' => 'title_desc',
					],
				],
				'sort_date'   => [
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
			break;

		default:
			$definitions = [];
			break;
	}

	if ( 'all' === $keys ) {
		return $definitions;
	}

	$keys = array_fill_keys( (array) $keys, '' );

	return array_intersect_key( $definitions, $keys );
}

/**
 * Retrieve a single definition for use within a block.
 *
 * @param string $key   The key of the definition to retrieve.
 * @param string $type  The type of definition to retrieve. 'attribute' or 'option'.
 * @param array  $props Properties to override in the shared definition.
 *
 * @return array
 */
function get_shared_definition( $key, $type, $props = [] ) {
	$result = get_shared_definitions( $key, $type );

	if ( ! empty( $result ) ) {
		$result = wp_parse_args( $props, array_shift( $result ) );
	}

	return $result;
}
