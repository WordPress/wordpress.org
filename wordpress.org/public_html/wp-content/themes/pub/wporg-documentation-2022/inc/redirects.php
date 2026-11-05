<?php
/**
 * Redirects for the WordPress.org Documentation site.
 *
 * Search results go to the global WordPress.org search, and moved or removed
 * content goes to its new home.
 *
 * @package WordPressdotorg\Theme\Documentation_2022
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Theme\Documentation_2022\Redirects;

add_action( 'template_redirect', __NAMESPACE__ . '\redirect_to_global_search' );
add_action( 'template_redirect', __NAMESPACE__ . '\redirect_old_content', 9 ); // Before redirect_canonical().

/**
 * Redirects search results to the global WordPress.org search.
 *
 * @return void
 */
function redirect_to_global_search(): void {
	$search_terms = get_search_query( false );

	if ( $search_terms ) {
		$search_url = sprintf(
			'https://wordpress.org/search/%s/?in=support_docs',
			rawurlencode( $search_terms )
		);
		wp_safe_redirect( esc_url_raw( $search_url ) );
		exit;
	}
}

/**
 * Redirects old content to the new URL structure.
 *
 * This does not include the article renaming since core will handle that.
 *
 * @return void
 */
function redirect_old_content(): void {
	$path_redirects = array(
		// Removed articles.
		'/documentation/article/wordpress-features/'       => 'https://wordpress.org/about/features/',
		'/documentation/article/requirements/'             => 'https://wordpress.org/about/requirements/',

		// Renamed categories.
		'/documentation/category/basic-administration/'    => '/documentation/category/dashboard/',
		'/documentation/category/troubleshooting/'         => '/documentation/category/faqs/',
		'/documentation/category/getting-started/'         => '/documentation/category/where-to-start/',

		// Top-level category landing pages.
		'/documentation/category/customizing/'             => '/documentation/customization/',
		'/documentation/category/basic-usage/'             => '/documentation/support-guides/',

		// WordPress releases that never were.
		'/documentation/wordpress-version/version-6-5-1/'  => '/documentation/wordpress-version/version-6-5-2/',
		'/documentation/wordpress-version/version-6-8-4/'  => '/documentation/wordpress-version/version-6-8-5/',

		// Redirect articles to Advanced Administration handbook on devhub.
		'/documentation/article/administration-over-ssl/'  => 'https://developer.wordpress.org/advanced-administration/security/https/',
		'/documentation/article/backing-up-your-database/' => 'https://developer.wordpress.org/advanced-administration/security/backup/database/',
		'/documentation/article/backing-up-your-wordpress-files/' => 'https://developer.wordpress.org/advanced-administration/security/backup/files/',
		'/documentation/article/before-you-create-a-network/' => 'https://developer.wordpress.org/advanced-administration/multisite/prepare-network/',
		'/documentation/article/before-you-install/'       => 'https://developer.wordpress.org/advanced-administration/before-install/',
		'/documentation/article/brute-force-attacks/'      => 'https://developer.wordpress.org/advanced-administration/security/brute-force/',
		'/documentation/article/changing-file-permissions/' => 'https://developer.wordpress.org/advanced-administration/server/file-permissions/',
		'/documentation/article/changing-the-site-url/'    => 'https://developer.wordpress.org/advanced-administration/upgrade/migrating/',
		'/documentation/article/common-wordpress-errors/'  => 'https://developer.wordpress.org/advanced-administration/wordpress/common-errors/',
		'/documentation/article/configuring-automatic-background-updates/' => 'https://developer.wordpress.org/advanced-administration/upgrade/upgrading/',
		'/documentation/article/configuring-wildcard-subdomains/' => 'https://developer.wordpress.org/advanced-administration/server/subdomains-wildcard/',
		'/documentation/article/cookies/'                  => 'https://developer.wordpress.org/advanced-administration/wordpress/cookies/',
		'/documentation/article/create-a-network/'         => 'https://developer.wordpress.org/advanced-administration/multisite/create-network/',
		'/documentation/article/creating-database-for-wordpress/' => 'https://developer.wordpress.org/advanced-administration/before-install/creating-database/',
		'/documentation/article/css/'                      => 'https://developer.wordpress.org/advanced-administration/wordpress/css/',
		'/documentation/article/debugging-a-wordpress-network/' => 'https://developer.wordpress.org/advanced-administration/debug/debug-network/',
		'/documentation/article/debugging-in-wordpress/'   => 'https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/',
		'/documentation/article/editing-files/'            => 'https://developer.wordpress.org/advanced-administration/wordpress/edit-files/',
		'/documentation/article/editing-wp-config-php/'    => 'https://developer.wordpress.org/advanced-administration/wordpress/wp-config/',
		'/documentation/article/emptying-a-database-table/' => 'https://developer.wordpress.org/advanced-administration/server/empty-database/',
		'/documentation/article/faq-troubleshooting-2/'    => 'https://developer.wordpress.org/advanced-administration/resources/faq/',
		'/documentation/article/finding-server-info/'      => 'https://developer.wordpress.org/advanced-administration/server/server-info/',
		'/documentation/article/ftp-clients/'              => 'https://developer.wordpress.org/advanced-administration/upgrade/ftp/',
		'/documentation/article/giving-wordpress-its-own-directory/' => 'https://developer.wordpress.org/advanced-administration/server/wordpress-in-directory/',
		'/documentation/article/hardening-wordpress/'      => 'https://developer.wordpress.org/advanced-administration/security/hardening/',
		'/documentation/article/how-to-install-wordpress/' => 'https://developer.wordpress.org/advanced-administration/before-install/howto-install/',
		'/documentation/article/htaccess/'                 => 'https://developer.wordpress.org/advanced-administration/server/web-server/httpd/',
		'/documentation/article/https-for-wordpress/'      => 'https://developer.wordpress.org/advanced-administration/security/https/',
		'/documentation/article/importing-content/'        => 'https://developer.wordpress.org/advanced-administration/wordpress/import/',
		'/documentation/article/installing-multiple-blogs/' => 'https://developer.wordpress.org/advanced-administration/before-install/multiple-instances/',
		'/documentation/article/installing-wordpress-at-popular-hosting-companies/' => 'https://developer.wordpress.org/advanced-administration/before-install/popular-providers/',
		'/documentation/article/installing-wordpress-in-your-language/' => 'https://developer.wordpress.org/advanced-administration/before-install/in-your-language/',
		'/documentation/article/installing-wordpress-on-your-own-computer/' => 'https://developer.wordpress.org/advanced-administration/before-install/development/',
		'/documentation/article/loopbacks/'                => 'https://developer.wordpress.org/advanced-administration/wordpress/loopback/',
		'/documentation/article/migrating-multiple-blogs-into-wordpress-multisite/' => 'https://developer.wordpress.org/advanced-administration/multisite/sites-multisite/',
		'/documentation/article/moving-wordpress/'         => 'https://developer.wordpress.org/advanced-administration/upgrade/migrating/',
		'/documentation/article/multilingual-wordpress/'   => 'https://developer.wordpress.org/advanced-administration/wordpress/multilingual/',
		'/documentation/article/multisite-network-administration/' => 'https://developer.wordpress.org/advanced-administration/multisite/administration/',
		'/documentation/article/must-use-plugins/'         => 'https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/',
		'/documentation/article/network-admin-settings-screen/' => 'https://developer.wordpress.org/advanced-administration/multisite/admin/settings/',
		'/documentation/article/network-admin-sites-screen/' => 'https://developer.wordpress.org/advanced-administration/multisite/admin/',
		'/documentation/article/network-admin-updates-screen/' => 'https://developer.wordpress.org/advanced-administration/multisite/admin/',
		'/documentation/article/network-admin/'            => 'https://developer.wordpress.org/advanced-administration/multisite/admin/',
		'/documentation/article/nginx/'                    => 'https://developer.wordpress.org/advanced-administration/server/web-server/nginx/',
		'/documentation/article/optimization-caching/'     => 'https://developer.wordpress.org/advanced-administration/performance/cache/',
		'/documentation/article/optimization/'             => 'https://developer.wordpress.org/advanced-administration/performance/optimization/',
		'/documentation/article/phpmyadmin/'               => 'https://developer.wordpress.org/advanced-administration/upgrade/phpmyadmin/',
		'/documentation/article/plugins-editor-screen/'    => 'https://developer.wordpress.org/advanced-administration/plugins/editor-screen/',
		'/documentation/article/post-formats/'             => 'https://developer.wordpress.org/advanced-administration/wordpress/post-formats/',
		'/documentation/article/restoring-your-database-from-backup/' => 'https://developer.wordpress.org/advanced-administration/security/backup/',
		'/documentation/article/running-a-development-copy-of-wordpress/' => 'https://developer.wordpress.org/advanced-administration/before-install/development/',
		'/documentation/article/test-driving-wordpress/'   => 'https://developer.wordpress.org/advanced-administration/debug/test-driving/',
		'/documentation/article/two-step-authentication/'  => 'https://developer.wordpress.org/advanced-administration/security/mfa/',
		'/documentation/article/update-services/'          => 'https://developer.wordpress.org/advanced-administration/wordpress/update-services/',
		'/documentation/article/upgrading-wordpress-extended-instructions/' => 'https://developer.wordpress.org/advanced-administration/upgrade/upgrading/',
		'/documentation/article/using-cpanel/'             => 'https://developer.wordpress.org/advanced-administration/server/control-panel/',
		'/documentation/article/using-filezilla/'          => 'https://developer.wordpress.org/advanced-administration/upgrade/ftp/filezilla/',
		'/documentation/article/using-your-browser-to-diagnose-javascript-errors/' => 'https://developer.wordpress.org/advanced-administration/debug/debug-javascript/',
		'/documentation/article/why-should-i-use-https/'   => 'https://developer.wordpress.org/advanced-administration/security/https/',
		'/documentation/article/wordpress-backups/'        => 'https://developer.wordpress.org/advanced-administration/security/backup/',
		'/documentation/article/wordpress-feeds/'          => 'https://developer.wordpress.org/advanced-administration/wordpress/feeds/',
		'/documentation/article/wordpress-multisite-domain-mapping/' => 'https://developer.wordpress.org/advanced-administration/multisite/domain-mapping/',
	);

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/documentation/';

	foreach ( $path_redirects as $old_path => $new_url ) {
		if ( str_starts_with( $request_uri, $old_path ) ) {
			do_redirect_and_exit( $new_url );
		}
	}
}

/**
 * Does the 301 redirect and exits the script.
 *
 * @param string $location The URL to redirect to.
 * @return never
 */
function do_redirect_and_exit( string $location ): never {
	header_remove( 'expires' );
	header_remove( 'cache-control' );

	wp_safe_redirect( $location, 301 );
	exit;
}
