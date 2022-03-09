<?php
/**
 * Plugin Name: Locale Detection
 * Description: Sets a blog's locale to the users preferred locale.
 * Version: 1
 * Author: the WordPress team
 * Author URI: https://wordpress.org
 * License: GPLv2 or later
 *
 * Locale Detection is based on Browse Happy's Browse_Happy_Locale https://meta.trac.wordpress.org/browser/sites/trunk/browsehappy.com/public_html/inc/locale.php?rev=5790.
 * Browse Happy is distributed under the terms of the GNU GPL v2 or later.
 *
 * @package WordPressdotorg\LocaleDetection
 */

namespace WordPressdotorg\LocaleDetection;

/**
 * Initialize plugin.
 */
function init() {
	require_once 'class-detector.php';

	$detector = new Detector();

	add_filter( 'locale', [ $detector, 'get_locale' ] );
	add_filter( 'pre_determine_locale', [ $detector, 'get_locale' ] );
}

// Override the locale ASAP.
init();
