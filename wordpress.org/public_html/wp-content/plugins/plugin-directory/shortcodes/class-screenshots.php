<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * The [wporg-plugins-screenshots] shortcode handler to display a plugins screenshots.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Screenshots {

	/**
	 * @return string
	 */
	static function display() {
		$plugin = get_post();
		$output = '';

		// All indexed from 1.
		$descriptions = get_post_meta( $plugin->ID, 'screenshots', true );
		$screen_shots = get_post_meta( $plugin->ID, 'assets_screenshots', true );

		if ( empty( $screen_shots ) ) {
			return '';
		}

		/*
		 * Find the image that corresponds with the text.
		 * The image numbers are stored within the 'resolution' key.
		 */
		foreach ( $screen_shots as $image ) {
			$screen_shot = sprintf( '<a href="%1$s" rel="nofollow"><img class="screenshot" src="%1$s" /></a>',
				Template::get_asset_url( $plugin->post_name, $image )
			);

			if ( $descriptions && ! empty( $descriptions[ $image['resolution'] ] ) ) {
				$screen_shot .= '<figcaption>' . $descriptions[ $image['resolution'] ] . '</figcaption>';
			}

			$output .= '<li><figure>' . $screen_shot . '</figure></li>';
		}

		return '<ul class="plugin-screenshots">' . $output . '</ul>';
	}
}
