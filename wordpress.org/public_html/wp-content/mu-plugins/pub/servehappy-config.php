<?php
namespace WordPressdotorg\API\Serve_Happy;
/**
 * Plugin Name: Servehappy Versions
 * Description: Defines PHP version constants used by the Servehappy API and throughout wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 */

// The latest branch of PHP which WordPress.org recommends.
define( 'RECOMMENDED_PHP', '7.4' );

// The oldest branch of PHP which WordPress core still works with.
define( 'MINIMUM_PHP', '7.0' );

// The lowest branch of PHP which is actively supported.
define( 'SUPPORTED_PHP', '7.4' );

// The lowest branch of PHP which is receiving security updates.
define( 'SECURE_PHP', '7.4' );

// The lowest branch of PHP which is still considered acceptable in WordPress.
// Sites with a version lower than this will see the ServeHappy dashboard widget urging them to update.
define( 'ACCEPTABLE_PHP', '7.4' );
