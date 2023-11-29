<?php
/**
 * Plugin Name: Tour
 * Plugin URI: http://wordpress.org/plugins/tour/
 * Description: A WordPress plugin for creating tours for your site.
 * Version: 1.0
 * Author: Automattic
 * Author URI: http://automattic.com/
 * Text Domain: tour
 * License: GPLv2 or later
 *
 * @package Tour
 */

defined( 'ABSPATH' ) || die();
define( 'TOUR_VERSION', '1.0' );

require __DIR__ . '/class-tour.php';
Tour::register_hooks();
