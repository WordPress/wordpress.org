<?php
/*
Plugin Name: WP I18N Teams
Description: Provides shortcodes and blocks for managing translation teams.
Version:     1.0
License:     GPLv2 or later
Author:      WordPress.org
Author URI:  http://wordpress.org/
Text Domain: wporg
*/

namespace WordPressdotorg\I18nTeams;

const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__;

require_once PLUGIN_DIR . '/inc/namespace.php';
require_once PLUGIN_DIR . '/inc/locales.php';

bootstrap();
