<?php
/**
 * Plugin Name: mu-plugins Loader
 * Description: Bootstraps mu-plugins from pub/ and wporg-mu-plugins/ directories.
 */

// Load all mu-plugins from pub/.
foreach ( glob( __DIR__ . '/pub/*.php' ) as $file ) {
	require_once $file;
}

// Load the wporg-mu-plugins loader.
require_once __DIR__ . '/wporg-mu-plugins/mu-plugins/loader.php';
