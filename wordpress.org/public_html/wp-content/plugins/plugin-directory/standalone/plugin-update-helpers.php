<?php
namespace WordPressdotorg\Plugin_Directory\Standalone;

/**
 * This file contains some functions that are used in the plugin update-check API.
 * The API is currently closed source, but these functions are made public through this file.
 *
 * NOTE: This file is executed without WordPress being loaded.
 *       Please ensure no WordPress dependencies, or other plugin file dependencies are added.
 *       Certain methods are polyfilled in that environment with no-op variants such as __() and apply_filters().
 *
 * @link https://api.wordpress.org/plugins/update-check/{1.0,1.1}/
 */

/**
 * This function acts as a filter on the update that's presented to the site.
 *
 * @global string $wp_url              The WordPress site URL. Extracted from the HTTP User Agent header.
 * @global string $req_wp_version_base The WordPress client version. Empty if not a WordPress client. Excludes `-alpha` type suffixes.
 *
 * @param object $plugin_info       The plugin update details.
 * @param object $plugin_details    The plugin details.
 * @param string $installed_version The currently installed version of the plugin.
 * @param string $wp_version        The WordPress version. Empty if not a WordPress client.
 * @return object The plugin update details.
 */
function alter_update( $plugin_info, $plugin_details, $installed_version ) {
	global $wp_url, $req_wp_version_base;

	return $plugin_info;
}
