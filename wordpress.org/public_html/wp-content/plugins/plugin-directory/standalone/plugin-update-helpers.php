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
 * @param object $plugin_info       The plugin update details.
 * @param object $plugin_details    The plugin details.
 * @param string $installed_version The currently installed version of the plugin.
 * @param string $wp_version        The WordPress version. Empty if not a WordPress client. Excludes `-alpha` type suffixes.
 * @param string $wp_url            The WordPress site URL. Extracted from the HTTP User Agent header.
 * @return object The plugin update details.
 */
function alter_update( $plugin_info, $plugin_details, $installed_version, $wp_version, $wp_url ) {

	$plugin_info = phased_rollout_alter_update( $plugin_info, $plugin_details, $installed_version );

	return $plugin_info;
}

/**
 * Return the current sites update-percentage.
 *
 * @global string $wp_url         The WordPress site URL. Extracted from the HTTP User Agent header.
 *
 * @param string $slug    The plugin slug.
 * @param string $version The plugin version.
 *
 * @return float 0...100.00
 */
function get_site_percentage( string $slug = '', string $version = '' ) {
	global $wp_url;

	/*
	 * If the site URL hasn't been extracted already, pull it from the global.
	 * NOTE: This may be set by the tests or other codepaths that run before this function.
	 */
	if ( empty( $wp_url ) && preg_match( '#^WordPress/.+; (http.+)$#i', $_SERVER['HTTP_USER_AGENT'] ?? '', $m ) ) {
		$wp_url = $m[1];
	}

	$site_domain = strtolower( parse_url( $wp_url, PHP_URL_HOST ) ?: '' );

	// If we've reached this point and have no URL, delay the update until 100% is reached.
	if ( ! $site_domain ) {
		return 100;
	}

	// $site_step represents an integer from 0 to 4095.
	$site_step   = base_convert( substr( md5( "{$site_domain}|{$slug}|{$version}" ), 0, 3 ), 16, 10 );
	$site_percent = $site_step / 4095 * 100;

	return $site_percent;
}

/**
 * This function acts as a filter on the update that's presented to the site.
 *
 * @global string $wp_url              The WordPress site URL. Extracted from the HTTP User Agent header.
 * @global string $req_wp_version_base The WordPress client version. Empty if not a WordPress client. Excludes `-alpha` type suffixes.
 *
 * @param object $plugin_info       The plugin update details.
 * @param object $plugin_details    The plugin details.
 * @param string $installed_version The currently installed version of the plugin.
 * @return object The updated plugin update details.
 */
function phased_rollout_alter_update( $plugin_info, $plugin_details, $installed_version ) {
	global $wp_url, $req_wp_version_base;

	$strategy = $phase_details['strategy'] ?? false;
	if ( ! $strategy ) {
		return $plugin_info;
	}

	// This is effectively a NOOP strategy, no changes.
	if ( 'immediate' === $strategy ) {
		return $plugin_info;
	}

	// Calculate the number of hours since the plugin was released.
	$hours_since_release = ( time() - $plugin_details->meta->release_time ) / 3600;
	if ( $hours_since_release > 120 ) {
		// If more than 5 days have passed, always assume the update is available.
		return $plugin_info;
	}

	// If the strategy is manual updates only for the first 24hrs, then we can just disable the sites ability to perform autoupdates.
	if ( 'manual-updates-24hr' === $strategy ) {

		// If less than 24 hours have passed, do not update.
		if ( $hours_since_release <= 24 ) {
			$plugin_info->disable_autoupdates = true;
		}
		// Else: The plugin update is unchanged, sites will update.

		return $plugin_info;
	}

	$do_not_offer_update = false;

	// Handle the percent-based strategies.
	$plugin_percent_rollout = phased_rollout_get_plugin_percent( $strategy, $hours_since_release, $plugin_details );
	if ( $plugin_percent_rollout !== false ) {

		$site_percent = get_site_percentage( $plugin_details->plugin_slug, $plugin_details->version );

		if ( $site_percent > $plugin_percent_rollout ) {
			$do_not_offer_update = true;
		}
	}

	// If the site should not update, we'll return the last-version if possible.
	if ( $do_not_offer_update ) {
		$plugin_info->version = $plugin_details->meta->last_version ?? $installed_version;

		// Match update-check API.
		unset(
			$plugin_info->tested,
			$plugin_info->requires_php,
			$plugin_info->requires_plugins,
			$plugin_info->compatibility,
			$plugin_info->upgrade_notice
		);
	}

	return $plugin_info;
}

/**
 * Get the percentage of sites that should receive the update for the plugin.
 *
 * @link https://www.desmos.com/calculator/59sl7efajq
 *
 * @param string $strategy            The rollout strategy.
 * @param float  $hours_since_release The number of hours since the plugin was released.
 * @param object $update_details      The plugin update details.
 *
 * @return float|false The percentage of sites that should receive the update, or false invalid details.
 */
function phased_rollout_get_plugin_percent( string $strategy, float $hours_since_release, object $update_details ) {
	$percent_based_strategies = [
		'custom',
		'slow',
		'extra-slow',
		'cautious',
	];

	$phase_details = $update_details->meta->phased_rollout ?? false;

	if (
		! $phase_details ||
		! in_array( $strategy, $percent_based_strategies, true )
	) {
		return false;
	}

	switch( $strategy ) {
		default:
			return false;

		// Custom defined by the plugin author, they must update this value in settings.
		case 'custom':
			return $phase_details['percentage'] ?? 100;

		/*
		 * Straight curve, start at 5%, increases to 100% over the next 48hrs (2d).
		 *
		 * At 6 hours,  the percentage is 5 + (6/48) * 95  = 16.875%
		 * At 12 hours, the percentage is 5 + (12/48) * 95 = 28.75%
		 * At 24 hours, the percentage is 5 + (24/48) * 95 = 52.5%
		 * At 36 hours, the percentage is 5 + (36/48) * 95 = 72.25%
		 * At 48 hours, the percentage is 5 + (48/48) * 95 = 100%
		 */
		case 'slow':
			return 5 + ( $hours_since_release / 48 ) * 95;

		/*
		 * Polynomial curve, starts at 5%, increases to 100% over the next 72hrs (3d).
		 *
		 * At 6 hours,  the percentage is 9 * ( 1.0345 ** 6  ) - 3.9 = 7.13%
		 * At 12 hours, the percentage is 9 * ( 1.0345 ** 12 ) - 3.9 = 9.62%
		 * At 24 hours, the percentage is 9 * ( 1.0345 ** 24 ) - 3.9 = 16.41%
		 * At 36 hours, the percentage is 9 * ( 1.0345 ** 36 ) - 3.9 = 26.61%
		 * At 48 hours, the percentage is 9 * ( 1.0345 ** 48 ) - 3.9 = 41.95%
		 * At 60 hours, the percentage is 9 * ( 1.0345 ** 60 ) - 3.9 = 64.97%
		 * At 72 hours, the percentage is 9 * ( 1.0345 ** 72 ) - 3.9 = 100%
		 */
		case 'extra-slow':
			return 9 * ( 1.0345 ** $hours_since_release ) - 3.9;

		/*
		 * Polynomial curve, starts at 1%, with an increase to 100% over the next 120hrs (5d).
		 *
		 * At 6 hours,  the percentage is 11 * ( 1.0195 ** 6  ) - 10 = 2.35%
		 * At 12 hours, the percentage is 11 * ( 1.0195 ** 12 ) - 10 = 3.87%
		 * At 24 hours, the percentage is 11 * ( 1.0195 ** 24 ) - 10 = 7.49%
		 * At 36 hours, the percentage is 11 * ( 1.0195 ** 36 ) - 10 = 12%
		 * At 48 hours, the percentage is 11 * ( 1.0195 ** 48 ) - 10 = 17.8%
		 * At 72 hours, the percentage is 11 * ( 1.0195 ** 72 ) - 10 = 34.18%
		 * At 96 hours, the percentage is 11 * ( 1.0195 ** 96 ) - 10 = 60.24%
		 * At 120 hours, the percentage is 11 * ( 1.0195 ** 120 ) - 10 = 101.65 ~= 100%
		 *
		 */
		case 'cautious':
			return 11 * ( 1.0195 ** $hours_since_release ) - 10;
	}

	// If we reach this point, something is wrong.
	return false;
}
