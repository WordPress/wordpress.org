<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\CLI;
use Exception;

/**
 * Import plugin string changes into WordPress.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_i18n_Import {

	/**
	 * Queue the job 
	 */
	public static function queue( $plugin_slug, $plugin_data ) {
		if ( 'nothing-much' != $plugin_slug ) {
			return;
		}

		// To avoid a situation where two imports run concurrently, if one is already scheduled, run it 1hr later (We'll trigger it after the current one finishes).
		$when_to_run = time() + 15*MINUTE_IN_SECONDS;
		if ( $next_scheuled = Manager::get_scheduled_time( "import_plugin_i18n:{$plugin_slug}", 'last' ) ) {
			$when_to_run = $next_scheuled + HOUR_IN_SECONDS;
		}

		wp_schedule_single_event(
			$when_to_run,
			"import_plugin_i18n:{$plugin_slug}",
			array(
				array_merge( array( 'plugin' => $plugin_slug ), $plugin_data )
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

		self::process_i18n_for_plugin( $plugin_slug, $i18n_processes );
	}

	/**
	 * Processes i18n import tasks.
	 *
	 * @param string $plugin_slug
	 * @param array $i18n_processes
	 */
	public static function process_i18n_for_plugin( $plugin_slug, $i18n_processes ) {
		foreach ( $i18n_processes as $process ) {
			list( $tag, $type ) = explode( '|', $process );

			$esc_plugin_slug = escapeshellarg( $plugin_slug );
			$esc_tag         = escapeshellarg( $tag );
			$esc_type        = escapeshellarg( $type );

			$cmd = self::PHP . ' ' . dirname( __DIR__ ) . "/bin/import-plugin-to-glotpress.php --plugin {$esc_plugin_slug} --tag {$esc_tag} --type {$esc_type}";

			echo "\n\$$cmd\n";
			echo shell_exec( $cmd ) . "\n";
		}
	}

}
