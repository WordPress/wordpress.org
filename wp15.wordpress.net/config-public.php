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

/*
 * WordPress is installed in a subfolder, so the default cookie path would be `/wordpress`. That would prevent
 * cookies from being read by front-end requests, though, like the locale picker.
 */
define( 'COOKIEPATH',        '/' );
define( 'SITECOOKIEPATH',    '/' );
define( 'ADMIN_COOKIE_PATH', '/' );

if ( 'production' === WP15_ENVIRONMENT ) {
	ini_set( 'display_errors', 0 );

	define( 'WP_DEBUG_DISPLAY', false );
	define( 'WP_DEBUG_LOG',     true  );
	define( 'SCRIPT_DEBUG',     false );
	define( 'FORCE_SSL_ADMIN',  true  );

	define( 'GOOGLE_MAPS_PUBLIC_KEY', 'AIzaSyDjIfyktiJI23M5_IxssOEcrmnOFLHAbEs' ); // Restricted to wp15.wordpress.net.
} else {
	define( 'SAVEQUERIES',  true );
	define( 'WP_DEBUG',     true );
	define( 'WP_DEBUG_LOG', true );
	define( 'WP_DEBUG_DISPLAY', true );
	define( 'SCRIPT_DEBUG', true );

	define( 'GOOGLE_MAPS_PUBLIC_KEY', 'AIzaSyBM47qaIt4qOD36XDA9v5lsLKTERCZa2gA' ); // No referrer restrictions.
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}
