<?php
/**
 * Plugin Name: Support HelpHub
 * Plugin URI: https://wordpress.org/support/
 * Description: Introduces HelpHub functionality to the WordPress.org support structure.
 * Version: 1.0
 * Author: WordPress.org
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-forums
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 *
 * @package HelpHub
 */

namespace WordPressdotorg\HelpHub;

define( 'PLUGIN', __FILE__ );

require __DIR__ . '/inc/helphub-codex-languages/class-helphub-codex-languages.php';
require __DIR__ . '/inc/helphub-contributors/helphub-contributors.php';
require __DIR__ . '/inc/helphub-post-types/helphub-post-types.php';
require __DIR__ . '/inc/helphub-read-time/helphub-read-time.php';
require __DIR__ . '/inc/helphub-front-page-blocks/helphub-front-page-blocks.php';
require __DIR__ . '/inc/helphub-customroles/class-helphub-custom-roles.php';
require __DIR__ . '/inc/helphub-manager/class-helphub-manager.php';
require __DIR__ . '/inc/handbook-toc/index.php';
