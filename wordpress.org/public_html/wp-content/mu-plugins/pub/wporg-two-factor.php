<?php
/**
 * Plugin Name: WP.org Two Factor
 * Description: Runs the Two Factor authentication plugins on WordPress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\TwoFactor
 */

namespace WordPressdotorg\TwoFactor;

// Set this to false to disable the two factor plugin entirely
define( 'WPORG_ENABLE_2FA', true );

if ( ! defined( 'WPORG_ENABLE_2FA' ) || ! WPORG_ENABLE_2FA ) {
	return;
}

include_once WP_PLUGIN_DIR . '/two-factor/two-factor.php';
include_once WP_PLUGIN_DIR . '/wporg-two-factor/wporg-two-factor.php';
