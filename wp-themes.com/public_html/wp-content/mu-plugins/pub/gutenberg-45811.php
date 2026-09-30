<?php
namespace WordPressdotorg\Theme_Preview;
use WP_Theme_JSON_Data;

/**
 * Temporary fix for the parent themes theme.json failing to load on wp-themes.com.
 *
 * @see https://meta.trac.wordpress.org/ticket/8079
 * @see https://core.trac.wordpress.org/ticket/57141
 * @see https://wordpress.slack.com/archives/C02QB8GMM/p1668569269811889
 */
add_filter(
	'wp_theme_json_data_theme',
	function( $theme_json ) {
		$wp_theme = wp_get_theme();

		if (
			! is_child_theme() ||
			$wp_theme->parent() ||
			! file_exists( get_template_directory() . '/theme.json' )
		) {
			return $theme_json;
		}

		$parent_json_data = wp_json_file_decode(
			get_template_directory() . '/theme.json',
			[ 'associative' => true ]
		);

		$parent_theme_json = new WP_Theme_JSON_Data( $parent_json_data, 'theme' );

		return $parent_theme_json->update_with( $theme_json->get_data() );
	},
	20
);
