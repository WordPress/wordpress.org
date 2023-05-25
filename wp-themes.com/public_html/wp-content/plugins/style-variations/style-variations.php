<?php
/**
 * Plugin name: Style Variation Preview
 * Description: Adds features to preview a page using its style variation.
 * Version:     1.0.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\Theme_Preview\Style_Variations;

defined( 'WPINC' ) || die();

require_once __DIR__ . '/inc/global-style-page.php';
require_once __DIR__ . '/inc/page-intercept.php';
require_once __DIR__ . '/inc/styles-endpoint.php';
