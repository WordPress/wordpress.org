<?php
namespace WordPressdotorg\Translator;

/**
 * This helper file simply ensures that the proper WordPress.org Projects are loaded where needed.
 */

add_filter( 'translator_textdomains', function( $td ) {
	switch ( \get_stylesheet() ) {
		case 'pub/wporg-plugins':
			$td[] = 'wporg-plugins';
			$td[] = 'dynamic-plugin-i18n';
			break;
		case 'pub/wporg-themes':
			$td[] = 'wporg-themes';
		break;
	}

	$td[] = 'wporg';

	return $td;
} );

add_filter( 'translator_projects', function( $projects ) {
	// WARNING: Project ordering is important. Strings are matched in order.

	switch ( \get_stylesheet() ) {
		case 'pub/wporg-plugins':
			$projects[] = 'meta/plugins-v3';

			// Filter post results..
			add_filter( 'posts_results', function( $posts ) {
				foreach ( $posts as $p ) {
					// Add the plugins project
					if ( $p->post_type == 'plugin' ) {
						$readme_project = ( empty( $p->stable_tag ) || 'trunk' === $p->stable_tag ) ? 'dev-readme' : 'stable-readme';
						Plugin::instance()->register_glotpress_project(
							"wp-plugins/{$p->post_name}/{$readme_project}",
							true // Add at the start of the array.
						);
					}
				}
				return $posts;
			} );

			break;
		case 'pub/wporg-themes':
			$projects[] = 'meta/themes';
		break;
		case 'pub/wporg-main':
			// Always loaded.
		break;
	}

	$projects[] = 'meta/wordpress-org';

	return $projects;
} );