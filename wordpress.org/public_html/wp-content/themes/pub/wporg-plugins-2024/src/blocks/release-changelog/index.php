<?php
/**
 * Block Name: Releases
 * Description: A block to display releases.
 *
 * @package wporg
 */

namespace WordPressdotorg\Theme\Plugins_2024\ReleaseChangelog;

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type( __DIR__ . '/../../../build/blocks/release-changelog' );
}
