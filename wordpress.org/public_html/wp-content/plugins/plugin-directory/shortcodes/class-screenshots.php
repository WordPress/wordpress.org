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
		$output = '';

		$screenshots = Template::get_screenshots();

		if ( ! $screenshots ) {
			return '';
		}

		foreach ( $screenshots as $image ) {
			$screen_shot = sprintf(
				'<a href="%1$s" rel="nofollow"><img class="screenshot" src="%1$s" alt="" /></a>',
				esc_url( $image['src'] )
			);

			if ( $image['caption'] ) {
				$screen_shot .= '<figcaption>' . $image['caption'] . '</figcaption>';
			}

			$output .= '<li><figure>' . $screen_shot . '</figure></li>';
		}

		return '<ul class="plugin-screenshots">' . $output . '</ul>';
	}
}
