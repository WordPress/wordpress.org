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
		'post_type'  => 'wordcamp',
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
 * Get a consistent WordCamp name in the 'WordCamp [Location] [Year]' format.
 *
 * The results of bloginfo( 'name' ) don't always contain the year, but the title of the site's corresponding
 * `wordcamp` post is usually named 'WordCamp [Location]', so we can get a consistent name most of the time
 * by using that and adding the year (if available).
 *
 * @return string
 */
function get_wordcamp_name() {
	$name = false;

	if ( $wordcamp = get_wordcamp_post() ) {
		if ( ! empty( $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] ) ) {
			$name = $wordcamp->post_title .' '. date( 'Y', $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] );
		}
	}

	if ( ! $name ) {
		$name = get_bloginfo( 'name' );
	}

	return $name;
}
