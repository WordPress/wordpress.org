<?php
/**
 * Plugin Name: Plugin Directory
 * Plugin URI: https://wordpress.org/plugins/
 * Description: Transforms a WordPress site in The Official Plugin Directory.
 * Version: 3.0
 * Author: the WordPress team
 * Author URI: https://wordpress.org/
 * Text Domain: wporg-plugins
 * License: GPLv2
 * License URI: https://opensource.org/licenses/gpl-2.0.php
 *
 * @package WordPressdotorg_Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory;

/**
 * Store the root plugin file for usage with functions which use the plugin basename.
 */
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

/**
 * Store the root plugin folder for usage with functions which need the relative path.
 */
define( __NAMESPACE__ . '\PLUGIN_DIR', __DIR__ );

/**
 * Delay between a plugin release being committed (or its final author confirmation)
 * and the new version being written to the `update_source` table — and so served to
 * sites by the api.wordpress.org plugin update-check API. The previous version remains
 * served until the cooldown elapses. Mitigates supply-chain attacks by giving scanners
 * and humans a window to flag bad releases. Plugin reviewers can bypass the cooldown
 * via the wp-admin force-release action; see Jobs\API_Update_Updater::update_single_plugin().
 *
 * Defers to the shared WPORG_PLUGIN_THEME_RELEASE_DELAY constant when it's defined
 * so the plugin and theme directories can be tuned (or disabled) in lockstep from a
 * single override point.
 */
define( __NAMESPACE__ . '\RELEASE_COOL_DOWN_DELAY', defined( 'WPORG_PLUGIN_THEME_RELEASE_DELAY' ) ? WPORG_PLUGIN_THEME_RELEASE_DELAY : 24 * HOUR_IN_SECONDS );

/**
 * Returns the release cooldown delay, in seconds, for a plugin.
 *
 * The RELEASE_COOL_DOWN_DELAY constant provides the default, which is then passed through
 * the `wporg_plugins_release_cooldown_delay` filter so the delay can be shortened,
 * extended, or removed (return 0 to disable the cooldown) on a per-plugin basis. The
 * plugin slug is passed to the filter when it is known.
 *
 * This is captured onto each release at creation time (see Plugin_Directory::add_release()),
 * so changing the filter does not retroactively alter the cooldown of in-flight releases.
 *
 * @param string $plugin_slug The slug of the plugin being released, if known.
 * @return int Delay in seconds. 0 disables the cooldown (the version is served immediately).
 */
function get_release_cooldown_delay( $plugin_slug = '' ) {
	/**
	 * Filters the release cooldown delay for a plugin.
	 *
	 * Return 0 to disable the cooldown (the version is served as soon as it's imported), or
	 * a larger/smaller number of seconds to lengthen or shorten the delay for this plugin.
	 *
	 * @param int    $delay       The default delay in seconds (the RELEASE_COOL_DOWN_DELAY constant).
	 * @param string $plugin_slug The slug of the plugin being released, or '' when not known.
	 */
	return (int) apply_filters( 'wporg_plugins_release_cooldown_delay', RELEASE_COOL_DOWN_DELAY, $plugin_slug );
}

// Register an Autoloader for all files
require __DIR__ . '/class-autoloader.php';
Autoloader\register_class_path( __NAMESPACE__, __DIR__ );

// Instantiate the Plugin Directory
Plugin_Directory::instance();
