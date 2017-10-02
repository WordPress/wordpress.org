<?php

use WordPressdotorg\Markdown\Editor;
use WordPressdotorg\Markdown\Importer;

class DevHub_REST_API extends Importer {
	/**
	 * Singleton instance.
	 *
	 * @var static
	 */
	protected static $instance;

	/**
	 * Get the singleton instance, or create if needed.
	 *
	 * @return static
	 */
	public static function instance() {
		if ( empty( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	protected function get_base() {
		return home_url( 'rest-api/' );
	}

	protected function get_manifest_url() {
		return 'https://raw.githubusercontent.com/WP-API/docs/master/bin/manifest.json';
	}

	public function get_post_type() {
		return 'rest-api-handbook';
	}

	public function init() {
		add_filter( 'cron_schedules', array( $this, 'filter_cron_schedules' ) );
		add_action( 'init', array( $this, 'register_cron_jobs' ) );
		add_action( 'devhub_restapi_import_manifest', array( $this, 'import_manifest' ) );
		add_action( 'devhub_restapi_import_all_markdown', array( $this, 'import_all_markdown' ) );

		$editor = new Editor( $this );
		$editor->init();
	}

	/**
	 * Filter cron schedules to add a 15 minute schedule, if there isn't one.
	 */
	public function filter_cron_schedules( $schedules ) {
		if ( empty( $schedules['15_minutes'] ) ) {
			$schedules['15_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => '15 minutes'
			);
		}
		return $schedules;
	}

	public function register_cron_jobs() {
		if ( ! wp_next_scheduled( 'devhub_restapi_import_manifest' ) ) {
			wp_schedule_event( time(), '15_minutes', 'devhub_restapi_import_manifest' );
		}
		if ( ! wp_next_scheduled( 'devhub_restapi_import_all_markdown' ) ) {
			wp_schedule_event( time(), '15_minutes', 'devhub_restapi_import_all_markdown' );
		}
	}
}

DevHub_REST_API::instance()->init();

