<?php
namespace WordPressdotorg\Plugin_Directory\API;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * Registers all REST API meta and custom fields for the plugin post type.
 *
 * Called during `init` from Plugin_Directory::init().
 */
class Plugin_Fields {

	/**
	 * Register all meta and REST fields for the plugin post type.
	 */
	public static function register(): void {
		self::register_meta();
		self::register_rest_fields();
	}

	/**
	 * Register post meta with show_in_rest for the /wp/v2/plugin response.
	 */
	private static function register_meta(): void {
		register_meta( 'post', 'version', [
			'description'  => 'Current stable version.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'stable_tag', [
			'description'  => 'Stable version of the plugin.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'tested', [
			'description'  => 'The version of WordPress the plugin was tested with.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'requires', [
			'description'  => 'The minimum version of WordPress the plugin needs to run.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'requires_php', [
			'description'  => 'The minimum version of PHP the plugin needs to run.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'requires_plugins', [
			'description'  => 'Comma-separated slugs of required plugins.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'header_name', [
			'description'  => 'Name of the plugin.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'header_author', [
			'description'  => 'Name of the plugin author.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'header_description', [
			'description'  => 'Description of the plugin.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'assets_banners_color', [
			'description'  => 'Fallback color for the plugin.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'last_updated', [
			'description'  => 'Date the plugin was last updated.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'external_support_url', [
			'description'  => 'External support URL.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'external_repository_url', [
			'description'  => 'External repository URL.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'donate_link', [
			'description'       => 'Link to donate to the plugin.',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'header_plugin_uri', [
			'description'       => 'URL to the homepage of the plugin.',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'header_author_uri', [
			'description'       => 'URL to the homepage of the author.',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'rating', [
			'type'         => 'number',
			'description'  => 'Overall rating of the plugin.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'author_block_rating', [
			'type'         => 'number',
			'description'  => 'Average rating of blocks by this author.',
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'active_installs', [
			'type'              => 'integer',
			'description'       => 'Number of installations.',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'downloads', [
			'type'              => 'integer',
			'description'       => 'Number of downloads.',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'num_ratings', [
			'type'              => 'integer',
			'description'       => 'Number of ratings.',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'support_threads', [
			'type'              => 'integer',
			'description'       => 'Amount of support threads for the plugin.',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'support_threads_resolved', [
			'type'              => 'integer',
			'description'       => 'Amount of resolved support threads for the plugin.',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'author_block_count', [
			'type'              => 'integer',
			'description'       => 'Number of blocks by this author.',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'sections', [
			'type'         => 'array',
			'description'  => 'List of readme section names present for the plugin.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
		] );

		register_meta( 'post', 'tags', [
			'type'         => 'object',
			'description'  => 'Tagged SVN versions with metadata (tag, author, date).',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		] );

		register_meta( 'post', 'upgrade_notice', [
			'type'         => 'object',
			'description'  => 'Upgrade notices keyed by version.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'string' ],
				],
			],
		] );

		register_meta( 'post', 'ratings', [
			'type'         => 'object',
			'description'  => 'Rating breakdown by star count.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		] );

		register_meta( 'post', 'assets_icons', [
			'type'         => 'object',
			'description'  => 'Icon images of the plugin.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		] );

		register_meta( 'post', 'assets_banners', [
			'type'         => 'object',
			'description'  => 'Banner images of the plugin.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		] );

		register_meta( 'post', 'assets_blueprints', [
			'type'         => 'object',
			'description'  => 'Plugin blueprint asset data keyed by filename.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
				'prepare_callback' => fn( $value ) => is_array( $value ) ? (object) $value : $value,
			],
		] );

		register_meta( 'post', 'all_blocks', [
			'type'         => 'object',
			'description'  => 'Block data provided by the plugin.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		] );

		register_meta( 'post', 'tagged_versions', [
			'type'         => 'array',
			'description'  => 'Tagged SVN versions of the plugin.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
		] );

		register_meta( 'post', 'block_files', [
			'type'         => 'array',
			'description'  => 'Block JSON file paths.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
		] );

		register_meta( 'post', 'assets_screenshots', [
			'type'         => 'object',
			'description'  => 'Screenshot asset data keyed by filename.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
		] );

		register_meta( 'post', 'screenshots', [
			'type'         => 'object',
			'description'  => 'Screenshot captions keyed by screenshot number.',
			'single'       => true,
			'show_in_rest' => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => [ 'type' => 'string' ],
				],
			],
		] );

	}

	/**
	 * Register custom REST fields for data not stored as post meta.
	 */
	private static function register_rest_fields(): void {
		// Resolved asset URLs — computed from raw meta, not stored directly.
		register_rest_field( 'plugin', 'banners', [
			'get_callback' => fn( array $object ): array => Template::get_plugin_banner( $object['id'] ) ?: [],
			'schema'       => [
				'description' => 'Plugin banner URLs.',
				'type'        => 'object',
				'context'     => [ 'view' ],
				'properties'  => [
					'banner'    => [ 'type' => 'string' ],
					'banner_x2' => [ 'type' => 'string' ],
				],
			],
		] );

		register_rest_field( 'plugin', 'icons', [
			'get_callback' => fn( array $object ): array => Template::get_plugin_icon( $object['id'] ),
			'schema'       => [
				'description' => 'Plugin icon URLs.',
				'type'        => 'object',
				'context'     => [ 'view' ],
				'properties'  => [
					'svg'       => [ 'type' => 'string' ],
					'icon'      => [ 'type' => 'string' ],
					'icon_x2'   => [ 'type' => 'string' ],
					'generated' => [ 'type' => 'boolean' ],
				],
			],
		] );

		register_rest_field( 'plugin', 'screenshots', [
			'get_callback' => fn( array $object ): array => array_values( array_map(
				fn( array $image ): array => [
					'src'     => $image['src'],
					'caption' => $image['caption'],
				],
				Template::get_screenshots( $object['id'] )
			) ),
			'schema'       => [
				'description' => 'Plugin screenshot URLs with captions.',
				'type'        => 'array',
				'context'     => [ 'view' ],
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'src'     => [ 'type' => 'string' ],
						'caption' => [ 'type' => 'string' ],
					],
				],
			],
		] );

		register_rest_field( 'plugin', 'raw_content', [
			'get_callback' => fn( array $object ): string => get_post( $object['id'] )?->post_content ?? '',
			'schema'       => [
				'description' => 'Raw post content.',
				'type'        => 'string',
				'context'     => [ 'view' ],
			],
		] );

		register_rest_field( 'plugin', 'raw_excerpt', [
			'get_callback' => fn( array $object ): string => get_post( $object['id'] )?->post_excerpt ?? '',
			'schema'       => [
				'description' => 'Raw post excerpt.',
				'type'        => 'string',
				'context'     => [ 'view' ],
			],
		] );

		// Enhance plugin_contributors taxonomy terms with user data.
		// These fields appear on each term in _embedded['wp:term'] when using ?_embed.
		register_rest_field( 'plugin_contributors', 'display_name', [
			'get_callback' => function ( array $term ): string {
				$user = get_user_by( 'slug', $term['slug'] );

				return $user ? $user->display_name : $term['name'];
			},
			'schema'       => [
				'description' => 'Display name of the contributor.',
				'type'        => 'string',
				'context'     => [ 'view', 'embed' ],
			],
		] );

		register_rest_field( 'plugin_contributors', 'avatar', [
			'get_callback' => function ( array $term ): string {
				$user = get_user_by( 'slug', $term['slug'] );

				return $user ? get_avatar_url( $user->ID, [ 'size' => 96 ] ) : '';
			},
			'schema'       => [
				'description' => 'Avatar URL of the contributor.',
				'type'        => 'string',
				'context'     => [ 'view', 'embed' ],
			],
		] );

		register_rest_field( 'plugin_contributors', 'profile', [
			'get_callback' => fn( array $term ): string => 'https://profiles.wordpress.org/' . $term['slug'] . '/',
			'schema'       => [
				'description' => 'Profile URL of the contributor.',
				'type'        => 'string',
				'context'     => [ 'view', 'embed' ],
			],
		] );
	}
}
