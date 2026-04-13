<?php
namespace WordPressdotorg\Post_Translation;

/**
 * Registers the Gutenberg editor sidebar panel for enabling post translation.
 */
class Editor {
	public static function init() {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
	}

	/**
	 * Register the post meta field for the block editor.
	 */
	public static function register_meta() {
		register_post_meta( '', META_KEY_ENABLED, [
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'boolean',
			'default'       => false,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		] );
	}

	/**
	 * Enqueue the editor sidebar script.
	 */
	public static function enqueue_assets() {
		$asset_file = __DIR__ . '/../build/editor/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wporg-post-translation-editor',
			plugins_url( 'build/editor/index.js', __DIR__ . '/../wporg-post-translation.php' ),
			$asset['dependencies'],
			$asset['version']
		);

		wp_set_script_translations( 'wporg-post-translation-editor', 'wporg-post-translation' );
	}
}
