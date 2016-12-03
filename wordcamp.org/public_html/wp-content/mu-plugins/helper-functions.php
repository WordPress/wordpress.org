<?php

use WordCamp\Logger;

/**
 * Retrieves the `wordcamp` post and postmeta associated with the current site.
 *
 * `Site ID` is the most reliable way to associate a site with it's corresponding `wordcamp` post,
 * but wasn't historically assigned when new sites are created. For older sites, we fallback to
 * using the `URL` to associate them. That will only work if the site's site_url() exactly
 * matches the `wordcamp` post's `URL` meta field, though. It could also fail if we ever migrate
 * to a different URL structure.
 *
 * @return false|WP_Post
 */
function get_wordcamp_post() {
	$current_site_id  = get_current_blog_id();
	$current_site_url = site_url();

	switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

	$wordcamp = get_posts( array(
		'post_type'   => 'wordcamp',
		'post_status' => 'any',

		'meta_query' => array(
			'relation'  => 'OR',

			array(
				'key'   => '_site_id',
				'value' => $current_site_id,
			),

			array(
				'key'   => 'URL',
				'value' => $current_site_url,
			),
		),
	) );

	if ( isset( $wordcamp[0]->ID ) ) {
		$wordcamp = $wordcamp[0];
		$wordcamp->meta = get_post_custom( $wordcamp->ID );
	} else {
		$wordcamp = false;
	}

	restore_current_blog();

	return $wordcamp;
}

/**
 * Find the site that corresponds to the given `wordcamp` post
 *
 * @param WP_Post $wordcamp_post
 *
 * @return mixed An integer if successful, or boolean false if failed
 */
function get_wordcamp_site_id( $wordcamp_post ) {
	switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

	if ( ! $site_id = get_post_meta( $wordcamp_post->ID, '_site_id', true ) ) {
		$url = parse_url( get_post_meta( $wordcamp_post->ID, 'URL', true ) );

		if ( isset( $url['host'] ) && isset( $url['path'] ) ) {
			if ( $site = get_site_by_path( $url['host'], $url['path'] ) ) {
				$site_id = $site->blog_id;
			}
		}
	}

	restore_current_blog();

	return $site_id;
}

/**
 * Get a consistent WordCamp name in the 'WordCamp [Location] [Year]' format.
 *
 * The results of bloginfo( 'name' ) don't always contain the year, but the title of the site's corresponding
 * `wordcamp` post is usually named 'WordCamp [Location]', so we can get a consistent name most of the time
 * by using that and adding the year (if available).
 *
 * @param int $site_id Optionally, get the name for a site other than the current one.
 *
 * @return string
 */
function get_wordcamp_name( $site_id = 0 ) {
	$name = false;

	switch_to_blog( $site_id );

	if ( $wordcamp = get_wordcamp_post() ) {
		if ( ! empty( $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] ) ) {
			$name = $wordcamp->post_title .' '. date( 'Y', $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] );
		}
	}

	if ( ! $name ) {
		$name = get_bloginfo( 'name' );
	}

	restore_current_blog();

	return $name;
}

/**
 * Determine if a specific feature should be skipped on the current site
 *
 * Often times we want to add new functionality to plugins and themes, but can't let it run on older sites
 * because that would break backwards compatibility. To get around that, we set a flag on older sites to
 * indicate that they should not have the new feature, and then setup the feature to run on sites that
 * don't have the flag, i.e., to run by default.
 *
 * Doing it this way means that local development environments like the Meta Environment don't to have add any
 * new filters in order to start using the new functionality.
 *
 * See WordCamp_CLI_Miscellaneous::set_skip_feature_flag() for how to set the flags.
 *
 * @param string $flag
 *
 * @return bool
 */
function wcorg_skip_feature( $flag ) {
	$flags = get_option( 'wordcamp_skip_features', array() );

	return isset( $flags[ $flag ] );
}

/**
 * Get a user by the username or nicename
 *
 * Note: This intentionally doesn't lookup users by the display name or nickname, because those can be set by the
 * user, which could result in false-positive matches.
 *
 * @param string $name
 *
 * @return false|WP_User
 */
function wcorg_get_user_by_canonical_names( $name ) {
	if ( ! $user = get_user_by( 'login', $name ) ) {    // user_login
		$user = get_user_by( 'slug', $name );           // user_nicename
	}

	return $user;
}

/**
 * Extract pieces from a WordCamp.org URL
 *
 * @todo find other code that's doing this same task in an ad-hoc manner, and convert it to use this instead
 *
 * @param string $url
 * @param string $part 'city', 'city-domain' (without the year, e.g. seattle.wordcamp.org), 'year'
 *
 * @return false|string|int False on errors; an integer for years; a string for city and city-domain
 */
function wcorg_get_url_part( $url, $part ) {
	$url_parts = explode( '.', parse_url( $url, PHP_URL_HOST ) );
	$result    = false;

	// Make sure it matches the typical year.city.wordcamp.org structure
	if ( 4 !== count( $url_parts ) ) {
		return $result;
	}

	switch( $part ) {
		case 'city':
			$result = $url_parts[1];
			break;

		case 'city-domain':
			$result = ltrim( strstr( $url, '.' ), '.' );
			break;

		case 'year':
			$result = absint( $url_parts[0] );
			break;
	}

	return $result;
}

/**
 * Get ISO-3166 country names and codes
 *
 * @todo move the real functionality from get_valid_countries_iso3166() to here, then have the Budgets plugin,
 * QBO, etc call this.
 *
 * @return array
 */
function wcorg_get_countries() {
	require_once( WP_PLUGIN_DIR . '/wordcamp-payments/includes/wordcamp-budgets.php' );

	return WordCamp_Budgets::get_valid_countries_iso3166();
}

/**
 * Escape a string to be used in a CSV context
 *
 * This is just a wrapper to somewhat de-couple things, without having to manually sync updates to the canonical
 * function. See CampTix_Plugin::esc_csv() for details.
 *
 * @param array $fields
 *
 * @return array
 */
function wcorg_esc_csv( $fields ) {
	require_once( WP_CONTENT_DIR . '/plugins/camptix/camptix.php' );

	if ( is_callable( 'CampTix_Plugin::esc_csv' ) ) {
		$fields = CampTix_Plugin::esc_csv( $fields );
	} else {
		$fields = array();
	}

	return $fields;
}

/**
 * Make a remote HTTP request, and retry if it fails
 *
 * Sometimes the HTTP request times out, or there's a temporary server-side error, etc. Some use cases require a
 * successful request, like stats scripts, where the resulting data would be distorted by a failed response.
 *
 * @todo Remove this if https://github.com/rmccue/Requests/issues/222 is implemented
 *
 * @param string $request_url
 * @param array  $request_args
 *
 * @return array|WP_Error
 */
function wcorg_redundant_remote_get( $request_url, $request_args = array() ) {
	$attempt_count = 1;

	while ( true ) {
		$response    = wp_remote_get( $request_url, $request_args );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$retry_after = wp_remote_retrieve_header( $response, 'retry-after' ) ?: 5;
		$retry_after = min( $retry_after * $attempt_count, 30 );

		if ( ! is_wp_error( $response ) && 200 === $status_code && $body ) {
			break;
		}

		if ( $attempt_count < 3 ) {
			Logger\log( 'request_failed_temporarily', compact( 'request_url', 'request_args', 'response', 'attempt_count', 'retry_after' ) );
			sleep( $retry_after );
		} else {
			Logger\log( 'request_failed_permenantly', compact( 'request_url', 'request_args', 'response' ) );
			break;
		}

		$attempt_count++;
	}

	return $response;
}

/**
 * Take the start and end dates for a WordCamp and calculate how many days it lasts.
 *
 * @param WP_Post $wordcamp
 *
 * @return int
 */
function wcord_get_wordcamp_duration( WP_Post $wordcamp ) {
	// @todo Make sure $wordcamp is the correct post type

	$start = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );
	$end   = get_post_meta( $wordcamp->ID, 'End Date (YYYY-mm-dd)', true );

	// Assume 1 day duration if there is no end date
	if ( ! $end ) {
		return 1;
	}

	$duration_raw = $end - $start;

	$duration_days = floor( $duration_raw / DAY_IN_SECONDS );

	return absint( $duration_days );
}