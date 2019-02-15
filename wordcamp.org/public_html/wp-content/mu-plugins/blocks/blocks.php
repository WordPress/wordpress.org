<?php
namespace WordCamp\Blocks;
defined( 'WPINC' ) || die();

define( __NAMESPACE__ . '\PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', \plugins_url( '/', __FILE__ ) );

/**
 * Load files.
 *
 * @return void
 */
function load() {
	require_once PLUGIN_DIR . 'includes/speakers.php';
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );

/**
 * Add block categories for custom blocks.
 *
 * @param array $default_categories
 *
 * @return array
 */
function register_block_categories( $default_categories ) {
	$default_categories[] = array(
		'slug'  => 'wordcamp',
		'title' => __( 'WordCamp Blocks', 'wordcamporg' ),
	);

	return $default_categories;
}

add_filter( 'block_categories', __NAMESPACE__ . '\register_block_categories' );

/**
 * Register assets.
 *
 * The assets get enqueued automatically by the registered block types.
 *
 * @return void
 */
function register_assets() {
	wp_register_style(
		'wordcamp-blocks',
		PLUGIN_URL . 'assets/blocks.min.css',
		[],
		filemtime( PLUGIN_DIR . 'assets/blocks.min.css' )
	);

	wp_register_script(
		'wordcamp-blocks',
		PLUGIN_URL . 'assets/blocks.min.js',
		array(
			'lodash',
			'wp-api-fetch',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-editor',
			'wp-element',
			'wp-html-entities',
			'wp-i18n',
			'wp-url',
		),
		filemtime( PLUGIN_DIR . 'assets/blocks.min.js' ),
		false
	);

	/**
	 * Filter: Add/modify data sent to WordCamp Blocks JS scripts.
	 *
	 * @param array $data Associative multidimensional array of data.
	 */
	$data = apply_filters( 'wordcamp_blocks_script_data', [] );

	wp_add_inline_script(
		'wordcamp-blocks',
		sprintf(
			'var WordCampBlocks = %s;',
			wp_json_encode( $data )
		),
		'before'
	);

	wp_set_script_translations( 'wordcamp-blocks', 'wordcamporg' );
}

add_action( 'init', __NAMESPACE__ . '\register_assets', 9 );
