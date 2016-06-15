<?php
namespace WordPressdotorg\Plugin_Directory;
/**
 * This is a stand alone script which is regularly run to import plugins into the WordPress Plugin Directory after svn commits.
 */

// This script should only be called in a CLI environment.
if ( 'cli' != php_sapi_name() ) {
	die();
}

$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'wordpress.org';
$_SERVER['REQUEST_URI'] = '/plugins/';

// Assume a standard WordPress wp-content/plugins file structure.
include dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php';

$watcher = new CLI\SVN_Watcher();
$watcher->watch();