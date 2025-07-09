<?php
namespace WordPressdotorg\Plugin_Directory\Standalone;

/**
 * This file contains some functions that are used in the plugin update-check API.
 * The API is currently closed source, but these functions are made public through this file.
 *
 * NOTE: This file is executed without WordPress being loaded.
 *       Please ensure no WordPress dependencies, or other plugin file dependencies are added.
 *
 * @link https://api.wordpress.org/plugins/update-check/{1.0,1.1}/
 */

/**
 * Return the current sites update-percentage.
 *
 * @global $wp_url
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
	if ( ! $wp_url && preg_match( '#^WordPress/.+; (http.+)$#i', $_SERVER['HTTP_USER_AGENT'] ?? '', $m ) ) {
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
 * Determine if the site should update based on the phased rollout details.
 *
 * @link https://www.desmos.com/calculator/59sl7efajq
 *
 * @param object $update_details The plugin update details.
 * @return bool True if the site should update, false otherwise.
 */
function phased_rollout_should_update( object $update_details ) {
	$phase_details = $update_details->meta->phased_rollout ?? false;
	if ( ! $phase_details ) {
		return true;
	}

	/*
	 * $phase_details are expected to be in the format of...
	 * {
	 *   "strategy": "slow",
	 *   "time": Release time in seconds since the epoch,
	 *   "percentage": If the strategy is "custom", this is the percentage of sites that should receive the update.
	 * }
	 */

	$site_percent        = get_site_percentage( $update_details->plugin_slug, $update_details->version );
	$hours_since_release = ( time() - $phase_details['time'] ) / 3600;

	if ( $hours_since_release >= 120 ) {
		// If more than 5 days have passed, assume the update is available.
		return true;
	}

	switch( $phase_details['strategy'] ?? 'immediate' ) {
		default:
		case 'immediate':
			$percent = 100;
			break;

		// Custom defined by the plugin author, they must update this value in settings.
		case 'custom':
			$percent = $phase_details['percentage'] ?? 100;

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
			$percent = 5 + ( $hours_since_release / 48 ) * 95;
			break;

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
			$percent = 9 * ( 1.0345 ** $hours_since_release ) - 3.9;
			break;

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
			$percent = 11 * ( 1.0195 ** $hours_since_release ) - 10;
			break;
	}

	if ( $percent >= 100 ) {
		return true;
	}

	return ( $site_percent <= $percent );
}
