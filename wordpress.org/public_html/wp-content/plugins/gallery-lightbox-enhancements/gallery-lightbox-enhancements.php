<?php
/**
 * Plugin Name:       Gallery Lightbox Enhancements
 * Description:       Polyfill for two pending Gutenberg pull requests: lightbox captions in the Image block (#77477) and a masonry style for the Gallery block (#77615). Self-disables once the upstream changes land in core.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            WordPress.org
 * Author URI:        https://wordpress.org/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gallery-lightbox-enhancements
 * Domain Path:       /languages
 *
 * @package GalleryLightboxEnhancements
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-version-guard.php';

if ( ! GalleryLightboxEnhancements\Version_Guard::should_load() ) {
	return;
}

require_once __DIR__ . '/includes/class-masonry-style.php';
require_once __DIR__ . '/includes/class-lightbox-captions.php';

GalleryLightboxEnhancements\Masonry_Style::init();
GalleryLightboxEnhancements\Lightbox_Captions::init();
