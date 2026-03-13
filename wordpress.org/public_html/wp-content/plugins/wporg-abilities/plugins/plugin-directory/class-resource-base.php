<?php
/**
 * Base class for plugin directory resource abilities.
 *
 * Provides shared helpers for resources that pull content from
 * developer.wordpress.org (blog ID 33).
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Resource_Base class.
 */
class Resource_Base {

	/**
	 * Blog ID for developer.wordpress.org in the multisite network.
	 *
	 * @var int
	 */
	const DEVELOPER_BLOG_ID = 33;

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

		$mime_type = 'text/html';

		if ( function_exists( 'wp_html_to_markdown' ) ) {
			$text      = wp_html_to_markdown( $text );
			$mime_type = 'text/markdown';
		}

		return array(
			array(
				'uri'      => $uri,
				'text'     => $text,
				'mimeType' => $mime_type,
			),
		);
	}
}
