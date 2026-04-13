<?php
namespace WordPressdotorg\Post_Translation;

/**
 * Handles scheduling GlotPress imports when posts are saved.
 */
class Admin {
	public static function init() {
		add_action( 'save_post', [ __CLASS__, 'on_save_post' ], 10, 2 );
		add_action( 'post_translation_import', [ __CLASS__, 'handle_import' ], 10, 3 );
	}

	/**
	 * When a published post with translation enabled is saved, schedule an import.
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$project = get_translation_project( $post );
		if ( ! $project ) {
			return;
		}

		$source_blog_id = get_current_blog_id();
		$args           = [ $post_id, $source_blog_id, $project ];

		if ( wp_next_scheduled( 'post_translation_import', $args ) ) {
			return;
		}

		// Schedule the import to run on translate.w.org where GlotPress is loaded.
		if ( defined( 'WPORG_TRANSLATE_BLOGID' ) ) {
			switch_to_blog( WPORG_TRANSLATE_BLOGID );
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'post_translation_import', $args );
			restore_current_blog();
		} else {
			// Local/dev: run on the same site.
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'post_translation_import', $args );
		}
	}

	/**
	 * Handle the scheduled import cron event.
	 *
	 * Runs on translate.w.org. Fetches fresh post content from the source site
	 * and imports strings into the GlotPress project.
	 */
	public static function handle_import( $post_id, $source_blog_id, $project ) {
		require_once __DIR__ . '/class-importer.php';

		$importer = new Importer( $project );

		// Fetch the post content from the source site.
		switch_to_blog( $source_blog_id );

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			restore_current_blog();
			return;
		}

		$strings   = Post_Parser::post_to_strings( $post );
		$reference = get_permalink( $post );

		restore_current_blog();

		if ( empty( $strings ) ) {
			return;
		}

		$importer->import( $strings, $reference );
	}
}
