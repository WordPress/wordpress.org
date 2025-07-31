<?php
namespace WordPressdotorg\Plugin_Directory\Standalone;

/**
 * This file contains some functions that are used in the plugin update-check API.
 * The API is currently not open-source, but these functions are made public through this file.
 *
 * NOTE: This file is executed without WordPress being loaded.
 *       Please ensure no WordPress dependencies, or other plugin file dependencies are added.
 *       Certain methods MAY BE polyfilled in that environment with no-op variants such as __() and apply_filters().
 *
 * @link https://api.wordpress.org/plugins/update-check/{1.0,1.1}/
 */

/**
 * This function acts as a filter on the update that's presented to the site.
 *
 * @param object $plugin_info       The plugin update details.
 * @param object $plugin_details    The plugin details.
 * @param string $installed_version The currently installed version of the plugin.
 * @param string $wp_version        The WordPress version. Empty if not a WordPress client. Excludes `-alpha` type suffixes.
 * @param string $wp_url            The WordPress site URL. Extracted from the HTTP User Agent header.
 * @return object The plugin update details.
 */
function alter_update( $plugin_info, $plugin_details, $installed_version, $wp_version, $wp_url ) {

	// Apply the Phased Rollout / Staged Rollout / Gradual Rollout strategy to the plugin update.
	$plugin_info = phased_rollout( $plugin_info, $plugin_details, $installed_version );

	return $plugin_info;
}

/**
 * Apply the Phased / Staged rollout strategies to the plugin update.
 *
 * @see https://meta.trac.wordpress.org/ticket/8009
 *
 * @param object $plugin_info       The plugin update details.
 * @param object $plugin_details    The plugin details.
 * @param string $installed_version The currently installed version of the plugin.
 * @return object The updated plugin update details.
 */
function phased_rollout( $plugin_info, $plugin_details, $installed_version ) {
	$strategy = $plugin_info->meta->rollout['strategy'] ?? false;

	// If no strategy is set, or it's immediate, return the plugin info unchanged.
	if ( ! $strategy || 'immediate' === $strategy ) {
		return $plugin_info;
	}

	// Calculate the number of hours since the plugin was released.
	$hours_since_release = ( time() - ( $plugin_details->meta->release_time ?? '' ) ) / 3600 /* HOUR_IN_SECONDS */;

	// If more than 5 days have passed, always assume the update is available.
	if ( $hours_since_release > 120 ) {
		return $plugin_info;
	}

	// If the strategy is manual updates only for the first 24hrs, and we've not passed that, then disable auto-updates.
	if (
		'manual-updates-24hr' === $strategy &&
		$hours_since_release <= 24
	) {
		$plugin_info->disable_autoupdates = true;
	}

	return $plugin_info;
}
