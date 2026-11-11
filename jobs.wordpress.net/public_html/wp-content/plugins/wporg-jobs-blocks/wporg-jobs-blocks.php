<?php
/**
 * Plugin Name:       WordPress.org Jobs Blocks
 * Description:       Block-editor layer for jobs.wordpress.net: Block Bindings sources for job data, the Open to Work block, Interactivity filtering for the job browser, the job form partials and site-level hooks. Companion to the Jobs plugin, and meant to move into it.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.0
 * Requires Plugins:  jobswp
 * License:           GPL-2.0-or-later
 * Text Domain:       wporg-jobs-blocks
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'WPORG_JOBS_BLOCKS_VERSION', '0.1.0' );
define( 'WPORG_JOBS_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPORG_JOBS_BLOCKS_URL', plugin_dir_url( __FILE__ ) );

require_once WPORG_JOBS_BLOCKS_DIR . 'includes/data.php';
require_once WPORG_JOBS_BLOCKS_DIR . 'includes/bindings.php';
require_once WPORG_JOBS_BLOCKS_DIR . 'includes/render-filters.php';
require_once WPORG_JOBS_BLOCKS_DIR . 'includes/job-browser.php';
require_once WPORG_JOBS_BLOCKS_DIR . 'includes/partials.php';

// The classic theme prints its own analytics, feed links and robots rules.
add_action(
	'after_setup_theme',
	static function (): void {
		if ( wp_is_block_theme() ) {
			require_once WPORG_JOBS_BLOCKS_DIR . 'includes/site.php';
		}
	}
);

/**
 * Registers the plugin's blocks.
 */
function wporg_jobs_blocks_register_blocks(): void {
	register_block_type( WPORG_JOBS_BLOCKS_DIR . 'blocks/open-to-work' );
}
add_action( 'init', 'wporg_jobs_blocks_register_blocks' );
