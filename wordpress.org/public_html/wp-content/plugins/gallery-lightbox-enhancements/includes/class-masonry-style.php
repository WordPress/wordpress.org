<?php
/**
 * Registers the "Masonry" block style variation for core/gallery.
 *
 * Polyfill for https://github.com/WordPress/gutenberg/pull/77615.
 *
 * @package GalleryLightboxEnhancements
 */

namespace GalleryLightboxEnhancements;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Masonry" block-style variation for core/gallery.
 */
class Masonry_Style {

	const STYLE_HANDLE = 'gallery-lightbox-enhancements-masonry';

	/**
	 * Wires up the WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_style' ) );
	}

	/**
	 * Registers the block style variation and enqueues its stylesheet.
	 */
	public static function register_style() {
		register_block_style(
			'core/gallery',
			array(
				'name'  => 'masonry',
				'label' => __( 'Masonry', 'gallery-lightbox-enhancements' ),
			)
		);

		$plugin_main = dirname( __DIR__ ) . '/gallery-lightbox-enhancements.php';

		wp_enqueue_block_style(
			'core/gallery',
			array(
				'handle' => self::STYLE_HANDLE,
				'src'    => plugins_url( 'assets/masonry.css', $plugin_main ),
				'ver'    => '1.0.0',
				'path'   => dirname( __DIR__ ) . '/assets/masonry.css',
			)
		);
	}
}
