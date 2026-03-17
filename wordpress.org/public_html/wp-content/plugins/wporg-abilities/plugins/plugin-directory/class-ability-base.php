<?php
/**
 * Base class for plugin directory abilities.
 *
 * Provides shared utilities for abilities that interact with the
 * plugin-directory plugin or developer.wordpress.org content.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Ability_Base class.
 */
class Ability_Base {

	/**
	 * Blog ID for developer.wordpress.org in the multisite network.
	 *
	 * @var int
	 */
	const DEVELOPER_BLOG_ID = 33;

	/**
	 * Blog ID for the plugin directory (wordpress.org/plugins) in the multisite network.
	 *
	 * @var int
	 */
	const PLUGINS_BLOG_ID = 367;

	/**
	 * Load the plugin-directory autoloader and switch to the plugins blog.
	 *
	 * Permanently switches to the plugins blog for the remainder of the request.
	 * All plugin-directory abilities expect to run in this context.
	 */
	protected static function maybe_load_plugin_directory(): void {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}

		$autoloader = WP_PLUGIN_DIR . '/plugin-directory/class-autoloader.php';

		if ( ! file_exists( $autoloader ) ) {
			return;
		}

		require_once $autoloader;
		\WordPressdotorg\Plugin_Directory\Autoloader\register_class_path(
			'WordPressdotorg\\Plugin_Directory',
			WP_PLUGIN_DIR . '/plugin-directory'
		);

		switch_to_blog( self::PLUGINS_BLOG_ID );

		$loaded = true;
	}

	/**
	 * Convert HTML to markdown when available, falling back to plain text.
	 *
	 * Uses the wp_html_to_markdown() function from the html-to-md mu-plugin
	 * when available. Falls back to stripping tags if the converter is not loaded.
	 *
	 * @param string $html The HTML string to convert.
	 * @return string The converted text.
	 */
	protected static function html_to_text( string $html ): string {
		if ( function_exists( 'wp_html_to_markdown' ) ) {
			return wp_html_to_markdown( $html );
		}

		return wp_strip_all_tags( $html );
	}

	/**
	 * Fetch post content from developer.wordpress.org, converting to markdown when available.
	 *
	 * Uses the html-to-md mu-plugin to convert rendered post content to markdown,
	 * which is more useful for AI agents consuming the resources. Falls back to
	 * HTML if the converter is not available.
	 *
	 * @param int    $post_id The post ID on developer.wordpress.org.
	 * @param string $uri     The resource URI.
	 * @return array MCP resource contents array.
	 */
	protected static function get_devhub_post_content( int $post_id, string $uri ): array {
		switch_to_blog( self::DEVELOPER_BLOG_ID );

		try {
			$post = get_post( $post_id );

			if ( ! $post ) {
				return array(
					array(
						'uri'      => $uri,
						'text'     => sprintf( 'Error: Could not load content for this resource (post %d not found).', $post_id ),
						'mimeType' => 'text/plain',
					),
				);
			}

			setup_postdata( $post );

			$text = apply_filters( 'the_content', $post->post_content );

			wp_reset_postdata();
		} finally {
			restore_current_blog();
		}

		$text      = self::html_to_text( $text );
		$mime_type = function_exists( 'wp_html_to_markdown' ) ? 'text/markdown' : 'text/plain';

		return array(
			array(
				'uri'      => $uri,
				'text'     => $text,
				'mimeType' => $mime_type,
			),
		);
	}
}
