<?php
namespace WordPressdotorg\Post_Translation;

/**
 * Handles scheduling GlotPress imports when posts are saved.
 */
class Admin {
	public static function init() {
		add_action( 'save_post', [ __CLASS__, 'on_save_post' ], 10, 2 );
		add_action( 'post_translation_import', [ __CLASS__, 'handle_import' ], 10, 4 );
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
		$permalink      = get_permalink( $post );
		$args           = [ $post_id, $source_blog_id, $project, $permalink ];

		// On WordPress.org multisite, schedule the import on translate.w.org.
		// The duplicate check has to run on whichever blog owns the event.
		$is_remote = defined( 'WPORG_TRANSLATE_BLOGID' ) && is_multisite();

		if ( $is_remote ) {
			switch_to_blog( WPORG_TRANSLATE_BLOGID );
		}

		if ( ! wp_next_scheduled( 'post_translation_import', $args ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'post_translation_import', $args );
		}

		if ( $is_remote ) {
			restore_current_blog();
		}
	}

	/**
	 * Handle the scheduled import cron event.
	 *
	 * On WordPress.org, this runs on translate.w.org and switches to the
	 * source blog to fetch post content. On single-site, it runs directly.
	 */
	public static function handle_import( $post_id, $source_blog_id, $project, $permalink = '' ) {
		require_once __DIR__ . '/class-importer.php';

		$needs_switch = is_multisite() && get_current_blog_id() !== $source_blog_id;

		if ( $needs_switch ) {
			switch_to_blog( $source_blog_id );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			if ( $needs_switch ) {
				restore_current_blog();
			}
			return;
		}

		$strings = Post_Parser::post_to_strings( $post );

		// Capture the source site's name while switched; the import runs back on
		// the current (translate) blog, where get_bloginfo() would be wrong.
		$site_name = get_bloginfo( 'name' );

		// Use the permalink captured at save time if available.
		if ( ! $permalink ) {
			$permalink = get_permalink( $post );
		}

		if ( $needs_switch ) {
			restore_current_blog();
		}

		if ( empty( $strings ) ) {
			return;
		}

		$importer = new Importer( $project, $site_name );
		$importer->import( $strings, $permalink );
	}
}
