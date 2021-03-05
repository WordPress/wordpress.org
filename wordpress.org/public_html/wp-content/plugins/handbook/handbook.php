<?php
/**
 * Plugin Name: Handbook
 * Description: Features for a handbook, complete with glossary and table of contents
 * Version:     2.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 * Text Domain: wporg
 */

const WPORG_HANDBOOK_PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/handbook.php';
require_once __DIR__ . '/inc/admin-notices.php';
require_once __DIR__ . '/inc/callout-boxes.php';
require_once __DIR__ . '/inc/glossary.php';
require_once __DIR__ . '/inc/navigation.php';
require_once __DIR__ . '/inc/breadcrumbs.php';
require_once __DIR__ . '/inc/table-of-contents.php';
require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/email-post-changes.php';
require_once __DIR__ . '/inc/walker.php';
require_once __DIR__ . '/inc/watchlist.php';
require_once __DIR__ . '/inc/blocks.php';

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WordPressdotorg\\Markdown\\Importer' ) ) {
		require_once __DIR__ . '/inc/importer.php';
	}
}, 1 );
