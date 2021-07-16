<?php
namespace WordPressdotorg\HotFixes;
/**
 * Plugin Name: Hotfix for #53677 - load_script_textdomain()
 * Description: See https://core.trac.wordpress.org/ticket/53677
 */

/**
 * On WordPress.org, we use multiple subdirectories of themes,
 * which the core load_script_textdomain() does not handle.
 * 
 * This is a hotfix plugin to work around it, so that javascript translations
 * can be loaded for the Pattern Directory.
 */
add_filter( 'load_script_textdomain_relative_path', function( $relative, $src ) {

	if ( false !== stripos( $src, 'wp-content/themes/pub' ) ) {
		$relative = str_replace(
			[
				trailingslashit( wp_get_theme()->get_stylesheet_directory_uri() ),
				trailingslashit( wp_get_theme()->get_template_directory_uri() ),
			],
			'',
			$src
		);
	}

	return $relative;
}, 10, 2 );
