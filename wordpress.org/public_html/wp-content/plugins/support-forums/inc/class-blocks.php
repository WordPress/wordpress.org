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

		// Enable the blocks on the server-side.
		add_filter( 'blocks_everywhere_allowed_blocks', [ $this, 'allowed_blocks' ] );

		// Allow the oEmbed proxy endpoint for any user who can publish a thread/reply..
		add_filter( 'rest_api_init', [ $this, 'overwrite_oembed_10_proxy_permission' ], 20 );

		// Hack to make Imgur embeds work. This should be fixed by Imgur.
		add_filter( 'oembed_remote_get_args', [ $this, 'oembed_remote_get_args' ], 10, 2 );
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

	public function allowed_blocks( $blocks ) {
		// See ::editor_settings();
		$blocks[] = 'core/image';
		$blocks[] = 'core/embed';

		return array_unique( $blocks );
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

		$settings['iso']['allowEmbeds'] = array_values( array_diff(
			$settings['iso']['allowEmbeds'],
			[
				// Disable screencast, it seems not to work.
				'screencast',
			]
		) );

		return $settings;
	}

	public function overwrite_oembed_10_proxy_permission() {
		// A register_route_args filter would be handy here... See https://core.trac.wordpress.org/ticket/54087
		$oembed_proxy_route_args = rest_get_server()->get_routes( 'oembed/1.0' )['/oembed/1.0/proxy'] ?? false;
		if ( ! $oembed_proxy_route_args ) {
			return;
		}

		// Flip it from [ GET => true ] to [ GET ]
		$oembed_proxy_route_args[0]['methods'] = array_keys( $oembed_proxy_route_args[0]['methods'] );

		// Overwrite the permission_callback, allow any user who can create replies to use embeds.
		$oembed_proxy_route_args[0]['permission_callback'] = function() {
			return bbp_current_user_can_publish_topics() || bbp_current_user_can_publish_replies();
		};

		register_rest_route(
			'oembed/1.0',
			'/proxy',
			$oembed_proxy_route_args,
			true
		);
	}

	/**
	 * Imgur oEmbed API appears to block any request with a lowercase 'wordpress' in the user-agent.
	 *
	 * @param array  $http_args    The args to pass to wp_safe_remote_get().
	 * @param string $provider_url The URL of the oEmbed provider to be requested.
	 * @return array The modified $http_args.
	 */
	public function oembed_remote_get_args( $http_args, $provider_url ) {
		if ( str_contains( $provider_url, 'imgur.com' ) ) {
			$http_args['user-agent'] = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ), $provider_url );
			$http_args['user-agent'] = str_replace( 'wordpress', 'WordPress', $http_args['user-agent'] );
		}

		return $http_args;
	}
}
