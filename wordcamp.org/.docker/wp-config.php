<?php
/**
 * The base configuration for WordPress
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

/** Database configuration */
define( 'DB_NAME',           'wordcamp_dev' );
define( 'DB_USER',           'root'         );
define( 'DB_PASSWORD',       'mysql'        );
define( 'DB_HOST',           'wordcamp.db'  );
define( 'DB_CHARSET',        'utf8'         );
define( 'DB_COLLATE',        ''             );
define( 'JETPACK_DEV_DEBUG', true           );
define( 'SAVEQUERIES',       true           );
define( 'SCRIPT_DEBUG',      true           );
define( 'MULTISITE',         true           );

/** Error logging configurations */
ini_set( 'log_errors',           'On' );
ini_set( 'display_errors',       'On' );
ini_set( 'error_reporting',      E_ALL );

/**
 * It doesn't matter for local environments, but use `wp config shuffle-salts` to change this in production
 * environments, because generating the keys locally is safer than using the API (and exposing the keys to
 * your OS/browser if you copy/paste, etc).
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

/**
 * WordPress Database Table prefix.
 */
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is a correct and recommended place to define $table_prefix
$table_prefix  = 'wc_';

/**
 * For developers: WordPress debugging mode.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', true );

// Set central blog_id as base site to access network admin.
define( 'PATH_CURRENT_SITE',     '/'             );
define( 'SITE_ID_CURRENT_SITE',  1               );
define( 'BLOG_ID_CURRENT_SITE',  2               ); // Set central.wordcamp.org as main site in network.
define( 'DOMAIN_CURRENT_SITE',   'wordcamp.test' );
define( 'CLI_HOSTNAME_OVERRIDE', 'wordcamp.test' );


define( 'WP_ALLOW_MULTISITE', true  ); // @todo - temporary workaround for https://github.com/Automattic/wp-super-cache/issues/97.
define( 'SUBMITDISABLED',     false ); // work around https://github.com/Automattic/wp-super-cache/issues/213.
define( 'SUBDOMAIN_INSTALL',  true  );

// External users tables.
define( 'CUSTOM_USER_TABLE',      'wc_users'    );
define( 'CUSTOM_USER_META_TABLE', 'wc_usermeta' );

define( 'WP_DEBUG_LOG',     true );
define( 'WP_DEBUG_DISPLAY', true );

define( 'SLACK_ERROR_REPORT_URL', '(Optional) you can configure slack and add your webhook url. Errors will be posted to this slack url' );
define( 'WORDCAMP_LOGS_SLACK_CHANNEL', '(Optional) @your_slack_username' );

/** Sets up WordPress vars and included files. */
define( 'WORDCAMP_ENVIRONMENT', 'development' );

define( 'WORDCAMP_CAMPTIX_STRIPE_TEST_PUBLIC', '(Optional) add stripe test public key');
define( 'WORDCAMP_CAMPTIX_STRIPE_TEST_SECRET', '(Optional) add stripe test secret key');

define( 'WPLANG',          ''                                        );
define( 'WP_CONTENT_DIR',  dirname( __FILE__ ) . '/wp-content' );
define( 'FORCE_SSL_ADMIN', true                                      );

define( 'WP_CONTENT_URL',        'https://' . preg_replace( '/[^-_.0-9a-z:]/i', '', $_SERVER['HTTP_HOST'] ) . '/wp-content' );

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( ! defined('ABSPATH') ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/mu' );
}
require_once( ABSPATH . 'wp-settings.php' );