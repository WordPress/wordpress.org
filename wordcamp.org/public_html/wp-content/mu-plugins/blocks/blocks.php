<?php
namespace WordCamp\Blocks;
defined( 'WPINC' ) || die();

use WP_Post;

define( __NAMESPACE__ . '\PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_URL', \plugins_url( '/', __FILE__ ) );

/**
 * Load files.
 *
 * @return void
 */
function load() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	require_once PLUGIN_DIR . 'includes/speakers.php';
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );

/**
 * Add block categories for custom blocks.
 *
 * @param array   $default_categories
 * @param WP_Post $post
 *
 * @return array
 */
function register_block_categories( $default_categories, $post ) {
	$default_categories[] = array(
		'slug'  => 'wordcamp',
		'title' => __( 'WordCamp Blocks', 'wordcamporg' ),
	);

	return $default_categories;
}

add_filter( 'block_categories', __NAMESPACE__ . '\register_block_categories', 10, 2 );

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

	if ( function_exists( 'wp_set_script_translations' ) ) {
		// todo: Remove the existence check once we are running on 5.0.x
		wp_set_script_translations( 'wordcamp-blocks', 'wordcamporg' );
	}
}

add_action( 'init', __NAMESPACE__ . '\register_assets', 9 );

/**
 * Fix a CSS bug in Gutenberg when PanelRows are used with RangeControls.
 *
 * @todo Remove this when https://github.com/WordPress/gutenberg/pull/4564 is fixed.
 */
function fix_core_max_width_bug() {
	?>

	<style>
		.components-panel__row label {
			max-width: 100% !important;
		}
	</style>

	<?php
}

add_action( 'admin_print_styles', __NAMESPACE__ . '\fix_core_max_width_bug' );
