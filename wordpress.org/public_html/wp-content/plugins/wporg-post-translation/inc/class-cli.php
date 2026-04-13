<?php
namespace WordPressdotorg\Post_Translation;

use WP_CLI;

/**
 * WP-CLI commands for managing post translations.
 */
class CLI {
	/**
	 * Register the WP-CLI commands.
	 */
	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'post-translation import', [ __CLASS__, 'import' ] );
		}
	}

	/**
	 * Import translatable strings from posts into GlotPress.
	 *
	 * Finds all published posts with translation enabled and imports
	 * their strings into the corresponding GlotPress project.
	 *
	 * ## OPTIONS
	 *
	 * [--post_id=<id>]
	 * : Import a specific post by ID instead of all translatable posts.
	 *
	 * [--post_type=<type>]
	 * : Limit to a specific post type. Default: any.
	 *
	 * [--dry-run]
	 * : Show what would be imported without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import all translatable posts on the current site.
	 *     $ wp post-translation import
	 *
	 *     # Import a specific post.
	 *     $ wp post-translation import --post_id=42
	 *
	 *     # Dry run for pages only.
	 *     $ wp post-translation import --post_type=page --dry-run
	 *
	 *     # Import for a specific site on multisite.
	 *     $ wp post-translation import --url=developer.wordpress.org
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function import( $args, $assoc_args ) {
		require_once __DIR__ . '/class-importer.php';

		$dry_run = isset( $assoc_args['dry-run'] );

		$posts = self::get_translatable_posts( $assoc_args );

		if ( empty( $posts ) ) {
			WP_CLI::warning( 'No translatable posts found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d translatable post(s).', count( $posts ) ) );

		$total_strings = 0;

		foreach ( $posts as $post ) {
			$project = get_translation_project( $post );
			if ( ! $project ) {
				WP_CLI::warning( sprintf( 'Skipping post %d (%s) - no project.', $post->ID, $post->post_title ) );
				continue;
			}

			$strings   = Post_Parser::post_to_strings( $post );
			$permalink = get_permalink( $post );

			if ( empty( $strings ) ) {
				WP_CLI::log( sprintf( '  Post %d (%s): no strings found.', $post->ID, $post->post_title ) );
				continue;
			}

			$total_strings += count( $strings );

			if ( $dry_run ) {
				WP_CLI::log( sprintf(
					'  Post %d (%s): %d strings -> %s',
					$post->ID,
					$post->post_title,
					count( $strings ),
					$project
				) );
				continue;
			}

			$importer = new Importer( $project );
			$result   = $importer->import( $strings, $permalink );

			if ( false === $result ) {
				WP_CLI::warning( sprintf( '  Post %d (%s): import failed.', $post->ID, $post->post_title ) );
				continue;
			}

			list( $added, $existing, $fuzzied, $obsoleted, $error ) = $result;

			WP_CLI::log( sprintf(
				'  Post %d (%s): %d strings -> %s (%d added, %d updated, %d fuzzied, %d obsoleted%s)',
				$post->ID,
				$post->post_title,
				count( $strings ),
				$project,
				$added,
				$existing,
				$fuzzied,
				$obsoleted,
				$error ? ", {$error} errors" : ''
			) );
		}

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run complete. %d strings across %d posts would be imported.', $total_strings, count( $posts ) ) );
		} else {
			WP_CLI::success( sprintf( 'Import complete. Processed %d posts.', count( $posts ) ) );
		}
	}

	/**
	 * Get all published posts with translation enabled.
	 *
	 * @param array $assoc_args CLI arguments for filtering.
	 * @return \WP_Post[] Array of posts.
	 */
	protected static function get_translatable_posts( $assoc_args ) {
		// Single post by ID.
		if ( ! empty( $assoc_args['post_id'] ) ) {
			$post = get_post( (int) $assoc_args['post_id'] );

			if ( ! $post || 'publish' !== $post->post_status ) {
				return [];
			}

			if ( ! is_translation_enabled( $post ) ) {
				WP_CLI::warning( sprintf( 'Post %d does not have translation enabled.', $post->ID ) );
				return [];
			}

			return [ $post ];
		}

		// All translatable posts.
		$query_args = [
			'post_type'      => ! empty( $assoc_args['post_type'] ) ? $assoc_args['post_type'] : 'any',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => META_KEY_ENABLED,
			'meta_value'     => '1',
		];

		return get_posts( $query_args );
	}
}
