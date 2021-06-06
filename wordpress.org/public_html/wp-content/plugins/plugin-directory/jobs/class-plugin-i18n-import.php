<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Import plugin string changes to translate.wordpress.org.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_i18n_Import {

	const PHP = '/usr/local/bin/php';

	/**
	 * Queue the job
	 */
	public static function queue( $plugin_slug, $plugin_data ) {
		$when_to_run    = time() + 15 * MINUTE_IN_SECONDS;
		$next_scheduled = Manager::get_scheduled_time( "import_plugin_i18n:{$plugin_slug}", 'last' );

		// Update a scheduled event if it doesn't run in the next minute.
		if ( $next_scheduled && $next_scheduled > time() + 1 * MINUTE_IN_SECONDS ) {
			$next_scheduled_events = Manager::get_scheduled_events( "import_plugin_i18n:{$plugin_slug}", $next_scheduled );
			if ( $next_scheduled_events ) {
				$next_scheduled_event = array_shift( $next_scheduled_events );

				$next_scheduled_event['args'][0]['tags_touched'] = array_unique( array_merge(
					$next_scheduled_event['args'][0]['tags_touched'],
					$plugin_data['tags_touched']
				) );

				if ( $plugin_data['readme_touched'] ) {
					$next_scheduled_event['args'][0]['readme_touched'] = true;
				}

				if ( $plugin_data['code_touched'] ) {
					$next_scheduled_event['args'][0]['code_touched'] = true;
				}

				if ( $plugin_data['assets_touched'] ) {
					$next_scheduled_event['args'][0]['assets_touched'] = true;
				}

				$next_scheduled_event['args'][0]['revisions'] = array_unique( array_merge(
					$next_scheduled_event['args'][0]['revisions'],
					$plugin_data['revisions']
				) );

				$result = Manager::update_scheduled_event( "import_plugin_i18n:{$plugin_slug}", $next_scheduled, $next_scheduled_event );
				if ( $result ) {
					return;
				}
			}
		}

		wp_schedule_single_event(
			$when_to_run,
			"import_plugin_i18n:{$plugin_slug}",
			array(
				array_merge( array( 'plugin' => $plugin_slug ), $plugin_data ),
			)
		);

	}

	/**
	 * The cron trigger for the import job.
	 */
	public static function cron_trigger( $plugin_data ) {
		$plugin_slug = $plugin_data['plugin'];

		$plugin     = Plugin_Directory::get_plugin_post( $plugin_slug );
		$stable_tag = $plugin->stable_tag;

		$i18n_processes = [];
		if ( in_array( 'trunk', $plugin_data['tags_touched'] ) ) {
			if ( $plugin_data['code_touched'] ) {
				$i18n_processes[] = 'trunk|code';
			}
			if ( $plugin_data['readme_touched'] ) {
				$i18n_processes[] = 'trunk|readme';
			}
		}
		if ( in_array( $stable_tag, $plugin_data['tags_touched'] ) ) {
			if ( $plugin_data['code_touched'] ) {
				$i18n_processes[] = "{$stable_tag}|code";
			}
			if ( $plugin_data['readme_touched'] ) {
				$i18n_processes[] = "{$stable_tag}|readme";
			}
		}

		// Handle the case where a new stable tag is marked as latest
		// without any other changes.
		if ( [ 'trunk|readme' ] === $i18n_processes ) {
			$i18n_processes[] = "{$stable_tag}|code";
			$i18n_processes[] = "{$stable_tag}|readme";
		}

		self::process_i18n_for_plugin( $plugin_slug, $i18n_processes );
	}

	/**
	 * Processes i18n import tasks.
	 *
	 * @param string $plugin_slug
	 * @param array  $i18n_processes
	 */
	public static function process_i18n_for_plugin( $plugin_slug, $i18n_processes ) {
		foreach ( $i18n_processes as $process ) {
			list( $tag, $type ) = explode( '|', $process );

			$esc_plugin_slug = escapeshellarg( $plugin_slug );
			$esc_tag         = escapeshellarg( $tag );
			$esc_type        = escapeshellarg( $type );

			$cmd = self::PHP . ' ' . dirname( __DIR__ ) . "/bin/import-plugin-to-glotpress.php --plugin {$esc_plugin_slug} --tag {$esc_tag} --type {$esc_type}";
			exec( $cmd );
		}
	}
}
