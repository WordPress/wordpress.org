<?php
/**
 * Plugin Name: WordPress.org Robots.txt
 */

/**
 * Output specific WordPress.org robots.txt contents.
 */
function wporg_robots_txt( $robots ) {
	$blog_details = get_blog_details();

	if ( 'translate.wordpress.org' === $blog_details->domain ) {
		$robots .= "\nUser-agent: *\n" .
		           "Disallow: /*\n" .
		           "Allow: /$\n" .
		           "Allow: /stats/$\n" .
		           "Allow: /consistency/$\n" .
		           "Allow: /locale/$\n" .
		           "Allow: /locale/*/glossary/$\n" .
		           "Allow: /locale/*/stats/plugins/$\n" .
		           "Allow: /locale/*/stats/themes/$\n";

	} elseif ( 'wordpress.org' === $blog_details->domain ) {
		// WordPress.org/search/ should not be indexed.
		$robots .= "\nUser-agent: *\n" .
		           "Disallow: /search\n" .
		           "Disallow: /?s=\n";

	} elseif ( 's-origin.wordpress.org' === $blog_details->domain ) {
		// Placeholder for the s.w.org domain. See https://meta.trac.wordpress.org/ticket/5668
		// Intentional overwrite of value.
		$robots = "User-agent: *\n" .
		          "Disallow:\n";

	}

	// WordPress.org/plugins/search/* should not be indexed for now. See https://meta.trac.wordpress.org/ticket/5323
	if ( 'wordpress.org' === $blog_details->domain || defined( 'IS_ROSETTA_NETWORK' ) ) {
		$robots .= "\nUser-agent: *\n" .
		           "Disallow: /plugins/search\n";
	}

	// Allow access to the load-scripts.php & load-styles.php admin files.
	$robots = str_replace(
		"Allow: /wp-admin/admin-ajax.php\n",
		"Allow: /wp-admin/admin-ajax.php\n" .
			"Allow: /wp-admin/load-scripts.php\n" .
			"Allow: /wp-admin/load-styles.php\n",
		$robots
	);

	return $robots;
}
add_filter( 'robots_txt', 'wporg_robots_txt', 100 );

/**
 * Prefix any subsite Sitemaps where needed.
 */
function wporg_robots_prefix_sitemaps( $robots ) {
	$blog_details = get_blog_details();

	// Prefix the News and Showcase sitemaps
	if ( 'wordpress.org' === $blog_details->domain ) {
		$robots = "Sitemap: https://wordpress.org/news/sitemap.xml\n" .
		          "Sitemap: https://wordpress.org/showcase/sitemap.xml\n" .
		          $robots;
	}

	/*
	 * Add the Plugins and Theme directory Sitemaps
	 * Currently disabled for Rosetta as Jetpack sitemaps aren't working there.
	 */
	if (
		'wordpress.org' === $blog_details->domain
		// || defined( 'WPORG_GLOBAL_NETWORK_ID' ) && WPORG_GLOBAL_NETWORK_ID === $blog_details->site_id
	) {
		$robots = "Sitemap: https://{$blog_details->domain}/plugins/sitemap.xml\n" . $robots;
		$robots = "Sitemap: https://{$blog_details->domain}/themes/sitemap.xml\n" . $robots;
	}

	return $robots;
}
add_filter( 'robots_txt', 'wporg_robots_prefix_sitemaps', 1 );
