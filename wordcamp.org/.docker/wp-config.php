<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

/** The name of the database for WordPress */
define('DB_NAME', 'wordcamp_dev');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'mysql');

/** MySQL hostname */
define('DB_HOST', 'wordcamp.db');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define( 'JETPACK_DEV_DEBUG', true);
define( 'SAVEQUERIES', true );
define( 'SCRIPT_DEBUG', true );
define( 'MULTISITE', true );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is a correct and recommended place to define $table_prefix
$table_prefix  = 'wc_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', true);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( ! defined('ABSPATH') ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

	/*
 * Some code (like .config) is shared between WordCamp.org from other WordPress.org sites.
 * This constant can be used in that code to distinguish between the sites, without having to
 * check hostnames, which can be impractical on Multisite.
 */
define( 'IS_WORDCAMP_NETWORK', true );

// Set central blog_id as base site to access network admin.
define( 'PATH_CURRENT_SITE',    '/' );
define( 'SITE_ID_CURRENT_SITE', 1  );
define( 'BLOG_ID_CURRENT_SITE', 2  ); // Set central.wordcamp.org as main site in network.


define( 'WP_ALLOW_MULTISITE', true ); // @todo - temporary workaround for https://github.com/Automattic/wp-super-cache/issues/97.
define( 'SUBMITDISABLED',     false ); // work around https://github.com/Automattic/wp-super-cache/issues/213.
define( 'SUBDOMAIN_INSTALL',  true );
// External users tables
define( 'CUSTOM_USER_TABLE',      'wc_users'    );
define( 'CUSTOM_USER_META_TABLE', 'wc_usermeta' );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );

define( 'SLACK_ERROR_REPORT_URL', '(Optional) you can configure slack and add your webhook url. Errors will be posted to this slack url' );
define( 'WORDCAMP_LOGS_SLACK_CHANNEL', '(Optional) @your_slack_username' );

/** Sets up WordPress vars and included files. */
define( 'WORDCAMP_ENVIRONMENT', 'development' );

define('WORDCAMP_CAMPTIX_STRIPE_TEST_PUBLIC', '(Optional) add stripe test public key');
define('WORDCAMP_CAMPTIX_STRIPE_TEST_SECRET', '(Optional) add stripe test secret key');

define( 'WPLANG',         ''   );
define( 'WP_CONTENT_DIR', dirname( __FILE__ ) . '/wp-content' );
define( 'FORCE_SSL_ADMIN', true );

require_once(ABSPATH . 'wp-settings.php');
