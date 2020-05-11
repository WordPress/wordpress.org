<?php
/**
 * Class providing blocks for the block editor.
 *
 * @package handbook
 */

class WPorg_Handbook_Blocks {

	/**
	 * Initializes handbook blocks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Fires on 'init' action.
	 *
	 * @access public
	 */
	public static function do_init() {
		$script_path       = plugin_dir_path( WPORG_HANDBOOK_PLUGIN_FILE ) . 'scripts/blocks.js';
		$script_asset_path = plugin_dir_path( WPORG_HANDBOOK_PLUGIN_FILE ) . 'scripts/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ?
			require $script_asset_path :
			[
				'dependencies' => [],
				'version'      => filemtime( $script_path ),
			];

		// TODO: Update after https://github.com/WordPress/gutenberg/issues/14801 is fixed.
		wp_register_style(
			'wporg-handbook-blocks',
			plugins_url( 'scripts/src/blocks/callout/editor.css', __DIR__ ),
			[],
			$script_asset['version']
		);

		wp_register_script(
			'wporg-handbook-blocks',
			plugins_url( 'scripts/blocks.js', __DIR__ ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations( 'wporg-handbook-blocks', 'wporg' );

		register_block_type(
			'wporg/callout',
			[
				'editor_style'  => 'wporg-handbook-blocks',
				'editor_script' => 'wporg-handbook-blocks',
			]
		);
	}
}

WPorg_Handbook_Blocks::init();
