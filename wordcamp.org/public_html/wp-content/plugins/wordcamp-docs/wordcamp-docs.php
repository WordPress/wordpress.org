<?php
/*
 * Plugin Name: WordCamp Docs
 * Plugin URI: http://central.wordcamp.org/
 * Description: Generate various WordCamp-related documents.
 * Author: Konstantin Kovshenin
 * Version: 1.0-dev
 * License: GPL2+
 */

define( 'WORDCAMP_DOCS__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( WORDCAMP_DOCS__PLUGIN_DIR . 'classes/class-wordcamp-docs.php' );
require_once( WORDCAMP_DOCS__PLUGIN_DIR . 'classes/class-wordcamp-docs-template.php' );