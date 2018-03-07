<?php

/*
 * SECURITY WARNING: This file is _public_. Add passwords, etc to `config-private.php`.
 */

$table_prefix = 'wp_';

define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', ''        );

define( 'WP_CONTENT_URL', WP_HOME . '/content'                        );
define( 'WP_SITEURL',     WP_HOME . '/wordpress'                      );
define( 'WP_CONTENT_DIR', __DIR__ . '/public_html/content'            );
define( 'WPCACHEHOME',    WP_CONTENT_DIR . '/plugins/wp-super-cache/' );
define( 'WP_CACHE',       true                                        );

define( 'DISALLOW_FILE_EDIT',       true );
define( 'DISALLOW_UNFILTERED_HTML', true );

if ( 'production' === WP15_ENVIRONMENT ) {
	ini_set( 'display_errors', 0 );

	define( 'WP_DEBUG_DISPLAY', false );
	define( 'WP_DEBUG_LOG',     true  );
	define( 'SCRIPT_DEBUG',     false );
	define( 'FORCE_SSL_ADMIN',  true  );
} else {
	define( 'SAVEQUERIES',  true );
	define( 'WP_DEBUG',     true );
	define( 'SCRIPT_DEBUG', true );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

require_once( ABSPATH . 'wp-settings.php' );
