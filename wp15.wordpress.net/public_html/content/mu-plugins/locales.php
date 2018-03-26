<?php

/*
Plugin Name: WP15 - Locales
Description: Manage front-end locale switching.
Version:     0.1
Author:      WordPress Meta Team
Author URI:  https://make.wordpress.org/meta
*/

namespace WP15\Locales;
defined( 'WPINC' ) or die();

require_once trailingslashit( dirname( __FILE__ ) ) . 'locale-detection/locale-detection.php';
require_once trailingslashit( dirname( __FILE__ ) ) . 'locales/locales.php';
