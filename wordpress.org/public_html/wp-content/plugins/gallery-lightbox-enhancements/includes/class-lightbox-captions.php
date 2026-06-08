<?php
/**
 * Restores caption rendering inside the core image lightbox overlay.
 *
 * Polyfill for https://github.com/WordPress/gutenberg/pull/77477.
 *
 * @package GalleryLightboxEnhancements
 */

namespace GalleryLightboxEnhancements;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the figcaption polyfill into the core image lightbox.
 */
class Lightbox_Captions {

	const STYLE_HANDLE  = 'gallery-lightbox-enhancements-captions';
	const SCRIPT_HANDLE = 'gallery-lightbox-enhancements-captions';

	/**
	 * Wires up the WordPress hooks.
	 */
	public static function init() {
		// Priority 16 keeps us strictly after block_core_image_render_lightbox (priority 15).
		add_filter( 'render_block_core/image', array( __CLASS__, 'add_caption_to_state' ), 16, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Stores the figcaption text alongside the lightbox state for the given image.
	 *
	 * @param string $content Rendered block markup.
	 * @param array  $block   Parsed block.
	 * @return string Unchanged content; we only mutate Interactivity API state.
	 */
	public static function add_caption_to_state( $content, $block ) {
		if ( ! str_contains( $content, 'wp-lightbox-container' ) ) {
			return $content;
		}

		// WP_HTML_Tag_Processor does not expose inner text for arbitrary tags
		// like <figcaption>, so fall back to a regex against the rendered markup.
		if ( ! preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#is', $content, $matches ) ) {
			return $content;
		}

		$caption = trim( wp_strip_all_tags( $matches[1] ) );
		$id      = isset( $block['attrs']['id'] ) ? absint( $block['attrs']['id'] ) : 0;

		if ( '' === $caption || ! $id ) {
			return $content;
		}

		wp_interactivity_state(
			'core/image',
			array(
				'metadata' => array(
					$id => array(
						'caption' => $caption,
					),
				),
			)
		);

		return $content;
	}

	/**
	 * Enqueues the runtime CSS and JS that augment the lightbox overlay.
	 *
	 * Loaded unconditionally on the front end. The runtime JS exits early when
	 * no `.wp-lightbox-overlay` is present in the DOM, so the overhead on pages
	 * without a gallery is negligible. We cannot gate this with `has_block()`
	 * because the shortcodes that wrap the lightbox markup (e.g. the Plugin
	 * Directory's `[wporg-plugins-screenshots]`) only emit the block markup
	 * inside their render callback, after the enqueue hook has already fired.
	 */
	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$plugin_main = dirname( __DIR__ ) . '/gallery-lightbox-enhancements.php';
		$plugin_dir  = dirname( __DIR__ );

		$css_path = $plugin_dir . '/assets/lightbox-captions.css';
		$js_path  = $plugin_dir . '/assets/lightbox-captions.js';

		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/lightbox-captions.css', $plugin_main ),
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/lightbox-captions.js', $plugin_main ),
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0',
			true
		);
	}
}
