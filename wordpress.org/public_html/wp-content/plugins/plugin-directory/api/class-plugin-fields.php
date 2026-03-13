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
			'description'  => __( 'Current stable version.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'stable_tag', [
			'description'  => __( 'Stable version of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'tested', [
			'description'  => __( 'The version of WordPress the plugin was tested with.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'requires', [
			'description'  => __( 'The minimum version of WordPress the plugin needs to run.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'requires_php', [
			'description'  => __( 'The minimum version of PHP the plugin needs to run.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'requires_plugins', [
			'description'  => __( 'Comma-separated slugs of required plugins.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'header_name', [
			'description'  => __( 'Name of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'header_author', [
			'description'  => __( 'Name of the plugin author.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'header_description', [
			'description'  => __( 'Description of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'assets_banners_color', [
			'description'  => __( 'Fallback color for the plugin.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'last_updated', [
			'description'  => __( 'Date the plugin was last updated.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'external_support_url', [
			'description'  => __( 'External support URL.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'external_repository_url', [
			'description'  => __( 'External repository URL.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'donate_link', [
			'description'       => __( 'Link to donate to the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'header_plugin_uri', [
			'description'       => __( 'URL to the homepage of the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'header_author_uri', [
			'description'       => __( 'URL to the homepage of the author.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'rating', [
			'type'         => 'number',
			'description'  => __( 'Overall rating of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'author_block_rating', [
			'type'         => 'number',
			'description'  => __( 'Average rating of blocks by this author.', 'wporg-plugins' ),
			'single'       => true,
			'show_in_rest' => true,
		] );

		register_meta( 'post', 'active_installs', [
			'type'              => 'integer',
			'description'       => __( 'Number of installations.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'downloads', [
			'type'              => 'integer',
			'description'       => __( 'Number of downloads.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'num_ratings', [
			'type'              => 'integer',
			'description'       => __( 'Number of ratings.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'support_threads', [
			'type'              => 'integer',
			'description'       => __( 'Amount of support threads for the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'support_threads_resolved', [
			'type'              => 'integer',
			'description'       => __( 'Amount of resolved support threads for the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'author_block_count', [
			'type'              => 'integer',
			'description'       => __( 'Number of blocks by this author.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		] );

		register_meta( 'post', 'sections', [
			'type'         => 'array',
			'description'  => __( 'List of readme section names present for the plugin.', 'wporg-plugins' ),
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
			'description'  => __( 'Tagged SVN versions with metadata (tag, author, date).', 'wporg-plugins' ),
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
			'description'  => __( 'Upgrade notices keyed by version.', 'wporg-plugins' ),
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
			'description'  => __( 'Rating breakdown by star count.', 'wporg-plugins' ),
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
			'description'  => __( 'Icon images of the plugin.', 'wporg-plugins' ),
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
			'description'  => __( 'Banner images of the plugin.', 'wporg-plugins' ),
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
			'description'  => __( 'Plugin blueprint asset data keyed by filename.', 'wporg-plugins' ),
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
			'description'  => __( 'Block data provided by the plugin.', 'wporg-plugins' ),
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
			'description'  => __( 'Tagged SVN versions of the plugin.', 'wporg-plugins' ),
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
			'description'  => __( 'Block JSON file paths.', 'wporg-plugins' ),
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
			'description'  => __( 'Screenshot asset data keyed by filename.', 'wporg-plugins' ),
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
			'description'  => __( 'Screenshot captions keyed by screenshot number.', 'wporg-plugins' ),
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
			'get_callback' => fn( array $object ): array => Template::get_plugin_banner( $object['id'] ),
			'schema'       => [
				'description' => __( 'Plugin banner URLs.', 'wporg-plugins' ),
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
				'description' => __( 'Plugin icon URLs.', 'wporg-plugins' ),
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
				'description' => __( 'Plugin screenshot URLs with captions.', 'wporg-plugins' ),
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
				'description' => __( 'Raw post content.', 'wporg-plugins' ),
				'type'        => 'string',
				'context'     => [ 'view' ],
			],
		] );

		register_rest_field( 'plugin', 'raw_excerpt', [
			'get_callback' => fn( array $object ): string => get_post( $object['id'] )?->post_excerpt ?? '',
			'schema'       => [
				'description' => __( 'Raw post excerpt.', 'wporg-plugins' ),
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
				'description' => __( 'Display name of the contributor.', 'wporg-plugins' ),
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
				'description' => __( 'Avatar URL of the contributor.', 'wporg-plugins' ),
				'type'        => 'string',
				'context'     => [ 'view', 'embed' ],
			],
		] );

		register_rest_field( 'plugin_contributors', 'profile', [
			'get_callback' => fn( array $term ): string => 'https://profiles.wordpress.org/' . $term['slug'] . '/',
			'schema'       => [
				'description' => __( 'Profile URL of the contributor.', 'wporg-plugins' ),
				'type'        => 'string',
				'context'     => [ 'view', 'embed' ],
			],
		] );
	}
}
