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
 * @return float 0...100.00
 */
function get_site_percentage() {
	global $wp_url;

	static $site_percent;
	if ( $site_percent ) {
		return $site_percent;
	}

	/*
	 * If the site URL hasn't been extracted already, pull it from the global.
	 * NOTE: This may be set by the tests or other codepaths that run before this function.
	 */
	if ( ! $wp_url && preg_match( '#^WordPress/.+; (http.+)$#i', $_SERVER['HTTP_USER_AGENT'] ?? '', $m ) ) {
		$wp_url = $m[1];
	}

	// If no URL is set, delay the update until fully rolled out.
	if ( ! $wp_url ) {
		return 100;
	}

	$site_url = strtolower( $wp_url );

	// Strip off the scheme.
	if ( $pos = strpos( $site_url, '://' ) ) {
		$site_url = substr( $site_url, $pos + 3 );
	}
	// ... and path.
	if (
		( $pos = strpos( $site_url, '/' ) ) ||
		( $pos = strpos( $site_url, '?' ) ) ||
		( $pos = strpos( $site_url, '#' ) )
	) {
		$site_url = substr( $site_url, 0, $pos );
	}

	// Again, if we've reached this point and have no URL, delay the update.
	if ( ! $site_url ) {
		return 100;
	}

	// $site_step represents an integer from 0 to 4095.
	$site_step   = base_convert( substr( md5( $site_url ), 0, 3 ), 16, 10 );
	$site_percent = $site_step / 4095 * 100;

	return $site_percent;
}

/**
 * Determine if the site should update based on the phased rollout details.
 *
 * @param object|array $phase_details The details of the phased rollout.
 * @return bool True if the site should update, false otherwise.
 */
function phased_rollout_should_update( $phase_details ) {
	if ( ! $phase_details ) {
		return true;
	}

	$site_percent = get_site_percentage();

	// If the phased percentage is set directly in the details.
	if ( isset( $phase_details->percentage ) && $phase_details->percentage >= $site_percent ) {
		return true;
	}

	if ( empty( $phase_details['time'] ) ) {
		// If no time is set, assume the update is available.
		return true;
	}

	$hours_since_release = ( time() - $phase_details['time'] ) / 3600;

	switch( $phase_details['strategy'] ?? 'immediate' ) {
		default:
		case 'immediate':
			// Immediate updates are always applied.
			return true;

		// Start at 5%, increases to 100% over the next 48hrs.
		case 'slow':
			$a = -1037.12128;
			$b = -0.00391789;
			$c = 0.988961;
			break;

		// Starts at 5%, increases to 100% over the next 72hrs
		case 'extra-slow':
			$a = 58.4925;
			$b = -0.0111363;
			$c = 0.821332;
			break;

		// Starts at 1%, 
		case 'cautious':
			$a = -41.46565;
			$b = 0.0078509;
			$c = 0.945984;
			break;

		case 'should-switch-to-static-map';
			$map = [
				0 => 0.01, // 1%
				1 => 0.05, // 5%
				2 => 0.10, // 10%
				3 => 0.20, // 20%
				4 => 0.30, // 30%
				5 => 0.40, // 40%
				6 => 0.50, // 50%
				7 => 0.60, // 60%
				8 => 0.70, // 70%
				9 => 0.80, // 80%
				10 => 0.90, // 90%
			];
	}

	// There's a better formula for this I'm sure, but this will do to start with.
	// y = a . log(bx+c)
	$percent = $a * log( $b * $hours_since_release + $c );

	return ( $site_percent <= $percent );
}
