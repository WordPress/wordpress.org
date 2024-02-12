<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

/**
 * Remove no-longer-needed ZIP files.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class ZIP_Cleanup {

	/**
	 * The number of days post rejection/publish to keep a ZIP file.
	 *
	 * @var int
	 */
	const KEEP_DAYS = 90;

	/**
	 * The number of days post-approval to keep a ZIP file while waiting for publish.
	 *
	 * @var int
	 */
	const KEEP_DAYS_PAST_APPROVAL = 365;

	/**
	 * The cron trigger for the zip cleanup process.
	 */
	public static function cron_trigger() {

		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'application/zip',
			'posts_per_page' => -1,
			'date_query'     => [
				// Only query for attachments uploaded more than KEEP_DAYS ago
				[
					'column' => 'post_modified_gmt',
					'before' => self::KEEP_DAYS . ' days ago',
				]
			]
		] );

		foreach ( $attachments as $attachment ) {
			$plugin = get_post( $attachment->post_parent );

			// If not a plugin upload, or something drastically is wrong..
			if ( ! $attachment->post_parent || ! $plugin || 'plugin' !== $plugin->post_type ) {
				continue;
			}

			// If the plugin is still pending review, skip.
			if ( in_array( $plugin->post_status, [ 'draft', 'pending', 'new' ], true ) ) {
				continue;
			}

			/*
			 * Determine the 'latest' date that should be used for the purpose of removing ZIPs.
			 *
			 * The post_status is stored as `_{status}` upon transition.
			 * We want to compare to the Approval, Publish (approval -> import -> publish), and rejected dates.
			 * If a plugin is however closed between publish and now, the time to keep the initial ZIP should restart.
			 */
			$plugin_last_touched_date = 0;
			foreach ( [ 'plugin_closed_date', '_approved', '_publish', '_rejected' ] as $meta_field ) {
				$meta_value = get_post_meta( $plugin->ID, $meta_field, true );
				if ( ! $meta_value ) {
					continue;
				}

				$meta_value               = is_numeric( $meta_value ) ? (int) $meta_value : strtotime( $meta_value );
				$plugin_last_touched_date = max( $plugin_last_touched_date, $meta_value );
			}

			// Something is wrong. Either the plugin is not yet approved, or post_meta is broken.
			if ( ! $plugin_last_touched_date || $plugin_last_touched_date < 1660000000 /* random round date, 2022-08-08 */ ) {
				continue;
			}

			$days_to_keep    = ( 'approved' === $plugin->post_status ) ? self::KEEP_DAYS_PAST_APPROVAL : self::KEEP_DAYS;
			$should_keep_zip = ( $plugin_last_touched_date > ( time() - $days_to_keep * DAY_IN_SECONDS ) );

			if ( $should_keep_zip ) {
				continue;
			}

			// Cleanup ZIP-related metadata.
			delete_post_meta( $plugin->ID, '_submitted_zip_size' );
			delete_post_meta( $plugin->ID, '_submitted_zip_loc' );

			// Delete the file hash from the post.
			$file_hash = sha1_file( get_attached_file( $attachment ) );
			if ( $file_hash ) {
				delete_post_meta( $plugin->ID, 'uploaded_zip_hash', $file_hash );
			}

			// Include some log output for debugging.
			$filename = basename( wp_get_attachment_url( $attachment->ID ) );
			echo "Removing {$filename} from {$plugin->post_name} after {$days_to_keep} days\n";

			wp_delete_attachment( $attachment->ID, true );
		}
	}

}
