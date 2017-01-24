<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Plugin_i18n;

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
		$descriptions = get_post_meta( $plugin->ID, 'screenshots', true ) ?: array();
		$screen_shots = get_post_meta( $plugin->ID, 'assets_screenshots', true ) ?: array();;

		if ( empty( $screen_shots ) ) {
			return '';
		}

		/*
		 * Find the image that corresponds with the text.
		 * The image numbers are stored within the 'resolution' key.
		 */
		foreach ( $screen_shots as $image ) {
			$screen_shot = sprintf( '<a href="%1$s" rel="nofollow"><img class="screenshot" src="%1$s" alt="" /></a>',
				Template::get_asset_url( $plugin, $image )
			);

			if ( $descriptions && ! empty( $descriptions[ (int)$image['resolution'] ] ) ) {
				$caption = $descriptions[ (int)$image['resolution'] ];
				$caption = Plugin_I18n::instance()->translate( 'screenshot-' . $image['resolution'], $caption, [ 'post_id' => $$plugin->ID ] );
				$screen_shot .= '<figcaption>' . $caption . '</figcaption>';
			}

			$output .= '<li><figure>' . $screen_shot . '</figure></li>';
		}

		return '<ul class="plugin-screenshots">' . $output . '</ul>';
	}
}
