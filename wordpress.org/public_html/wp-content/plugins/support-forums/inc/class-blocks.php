<?php

namespace WordPressdotorg\Forums;

/**
 * Customizations for the Support Forum and the Blocks Everywhere plugin.
 *
 * To enable this file to be loaded on a bbPress install, activate the Blocks Everywhere plugin.
 */
class Blocks {
	public function __construct() {
		// Enable bbPress support.
		add_filter( 'blocks_everywhere_bbpress', '__return_true' );

		// Enable block processing in emails.
		add_filter( 'blocks_everywhere_email', '__return_true' );

		// Enable theme compatibility CSS.
		add_filter( 'blocks_everywhere_theme_compat', '__return_true' );

		// Any Javascript / CSS tweaks needed.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Theme Tweaks, these should be moved to the theme.
		add_filter( 'after_setup_theme', [ $this, 'after_setup_theme' ] );

		// Customize blocks for the Support Forums.
		add_filter( 'blocks_everywhere_editor_settings', [ $this, 'editor_settings' ] );
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			'wporg-bbp-blocks',
			plugins_url( '/js/blocks.js', __DIR__ ),
			[ 'wp-block-editor', 'jquery' ],
			filemtime( dirname( __DIR__ ) . '/js/blocks.js' ),
			true
		);
	}

	public function after_setup_theme() {
		// This will make embeds resize properly.
		add_theme_support( 'responsive-embeds' );
	}

	public function editor_settings( $settings ) {
		// This adds the image block, but only with 'add from url' as an option.
		$settings['iso']['blocks']['allowBlocks'][] = 'core/image';

		// Allows embeds and might fix pasting links sometimes not working.
		$settings['iso']['blocks']['allowBlocks'][] = 'core/embed';

		// Adds a table of contents button in the toolbar.
		$settings['toolbar']['toc'] = true;

		// Adds a navigation button in the toolbar.
		$settings['toolbar']['navigation'] = true;

		// This will display a support link in an ellipsis menu in the top right of the editor.
		$settings['iso']['moreMenu'] = true;
		$settings['iso']['linkMenu'] = [
			[
				/* translators: Link title to the WordPress Editor support article. */
				'title' => __( 'Help & Support', 'wporg-forums' ),
				/* translators: Link to the WordPress Editor article, used as the forum 'Help & Support' destination. */
				'url'   => __( 'https://wordpress.org/support/article/wordpress-editor/', 'wporg-forums' ),
			]
		];

		// TODO: To modify the available embeds modify $settings['iso']['allowEmbeds']

		return $settings;
	}
}
