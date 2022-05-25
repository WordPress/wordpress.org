<?php
/**
 * Plugin Name: Photo Directory
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * Text Domain: wporg-photos
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Adds functionality for the WordPress Photo Directory.
 */

namespace WordPressdotorg\Photo_Directory;

define( 'WPORG_PHOTO_DIRECTORY_DIRECTORY', __DIR__ );
define( 'WPORG_PHOTO_DIRECTORY_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/badges.php';
require_once __DIR__ . '/inc/head.php';
require_once __DIR__ . '/inc/moderation.php';
require_once __DIR__ . '/inc/photo.php';
require_once __DIR__ . '/inc/posts.php';
require_once __DIR__ . '/inc/registrations.php';
require_once __DIR__ . '/inc/rejection.php';
require_once __DIR__ . '/inc/search.php';
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/uploads.php';
require_once __DIR__ . '/inc/google-cloud-storage-stream-metadata.php';
require_once __DIR__ . '/inc/google-cloud-storage.php';
require_once __DIR__ . '/inc/google-vision-api.php';
require_once __DIR__ . '/inc/wporg.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/cli.php';
}
