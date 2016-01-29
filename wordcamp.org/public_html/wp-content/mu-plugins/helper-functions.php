<?php

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
