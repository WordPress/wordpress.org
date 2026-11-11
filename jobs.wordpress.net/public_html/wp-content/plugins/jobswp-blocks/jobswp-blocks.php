<?php
/**
 * Plugin Name:       JobsWP Blocks
 * Description:       Block-editor layer for jobs.wordpress.net: Block Bindings sources for job data, the Open to Work block, Interactivity filtering for the job browser, the job form partials and site-level hooks. Companion to the jobswp plugin, and meant to move into it.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      7.4
 * Requires Plugins:  jobswp
 * License:           GPL-2.0-or-later
 * Text Domain:       jobswp-blocks
 *
 * @package jobswp-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'JOBSWP_BLOCKS_VERSION', '0.1.0' );
define( 'JOBSWP_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'JOBSWP_BLOCKS_URL', plugin_dir_url( __FILE__ ) );

require_once JOBSWP_BLOCKS_DIR . 'includes/data.php';
require_once JOBSWP_BLOCKS_DIR . 'includes/bindings.php';
require_once JOBSWP_BLOCKS_DIR . 'includes/render-filters.php';
require_once JOBSWP_BLOCKS_DIR . 'includes/job-browser.php';
require_once JOBSWP_BLOCKS_DIR . 'includes/partials.php';

// The classic theme prints its own analytics, feed links and robots rules.
add_action(
	'after_setup_theme',
	static function (): void {
		if ( wp_is_block_theme() ) {
			require_once JOBSWP_BLOCKS_DIR . 'includes/site.php';
		}
	}
);

/**
 * Registers the plugin's blocks.
 */
function jobswp_blocks_register_blocks(): void {
	register_block_type( JOBSWP_BLOCKS_DIR . 'blocks/open-to-work' );
}
add_action( 'init', 'jobswp_blocks_register_blocks' );
