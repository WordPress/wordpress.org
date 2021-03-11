<?php
use WordPressdotorg\Markdown\Editor;
use WordPressdotorg\Markdown\Importer;

class DevHub_Docs_Importer extends Importer {
	/**
	 * Singleton instance.
	 *
	 * @var static
	 */
	protected static $instances;

	/**
	 * The post type for the given docs importer. (Hypenated and without "handbook".)
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * The slug base.
	 *
	 * @var string
	 */
	protected $base;

	/**
	 * The manifest URL.
	 *
	 * E.g. https://raw.githubusercontent.com/WP-API/docs/master/bin/manifest.json
	 *
	 * @var string
	 */
	protected $manifest_url;

	/**
	 * The cron update interval schedule.
	 *
	 * @var string
	 */
	protected $cron_interval = '15_minutes';

	/**
	 * Get the singleton instance, or create if needed.
	 *
	 * @return static
	 */
	public static function instance() {
		$class = get_called_class();

		if ( empty( static::$instances[ $class ] ) ) {
			static::$instances[ $class ] = new $class;
		}

		return static::$instances[ $class ];
	}

	/**
	 * Returns the base URL for the handbook.
	 *
	 * @return string
	 */
	protected function get_base() {
		return home_url( "{$this->base}/" );
	}

	/**
	 * Returns the manifest URL.
	 *
	 * @return string
	 */
	protected function get_manifest_url() {
		return $this->manifest_url;
	}

	/**
	 * Returns the post type for the imported handbook.
	 *
	 * @return string
	 */
	public function get_post_type() {
		return "{$this->post_type}-handbook";
	}

	/**
	 * Initializes the object.
	 *
	 * @param string $post_type    The post type base. Hypenated and without "handbook".
	 * @param string $base         The slug for the post type.
	 * @param string $manifest_url The manifest URL.
	 */
	public function do_init( $post_type, $base, $manifest_url ) {
		$this->post_type    = $post_type;
		$this->base         = $base;
		$this->manifest_url = $manifest_url;

		add_filter( 'devhub_handbook_post_types',                    array( $this, 'amend_post_types' ) );
		add_filter( 'handbook_post_type_defaults',                   array( $this, 'override_post_type_slug' ), 10, 2 );
		add_filter( 'cron_schedules',                                array( $this, 'filter_cron_schedules' ) );
		add_action( 'init',                                          array( $this, 'register_cron_jobs' ) );
		add_action( "devhub_{$this->post_type}_import_manifest",     array( $this, 'import_manifest' ) );
		add_action( "devhub_{$this->post_type}_import_all_markdown", array( $this, 'import_all_markdown' ) );

		$editor = new Editor( $this );
		$editor->init();
	}

	/**
	 * Adds the post type to the list of handbook post types.
	 *
	 * @param array Array of post type slugs.
	 * @return array
	 */
	public function amend_post_types( $post_types ) {
		if ( ! in_array( $this->post_type, $post_types ) ) {
			$post_types[] = $this->post_type;
		}

		return $post_types;
	}

	/**
	 * Overrides the handbook post type slug.
	 *
	 * This is generally only necessary if the post type does not match the post
	 * type slug.
	 *
	 * @param array  $config Array of post type config items.
	 * @param string $slug   The post type slug.
	 * @return array
	 */
	public function override_post_type_slug( $config, $slug ) {
		// If filtering is for this post type and the base and the post type don't
		// match, then filter post type defaults.
		if ( $this->post_type === $config['rewrite']['slug'] && $this->post_type !== $this->base ) {
			$config['rewrite']['slug'] = $this->base;
		}

		return $config;
	}

	/**
	 * Filters cron schedules to add a 15 minute schedule, if there isn't one.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
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

	/**
	 * Registers cron jobs.
	 */
	public function register_cron_jobs() {
		// If configured cron interval does not exist, then fall back to default.
		if ( ! in_array( $this->cron_interval, array_keys( wp_get_schedules() ) ) ) {
			$this->cron_interval = '15_minutes';
		}

		if ( ! wp_next_scheduled( "devhub_{$this->post_type}_import_manifest" ) ) {
			wp_schedule_event( time(), $this->cron_interval, "devhub_{$this->post_type}_import_manifest" );
		}

		if ( ! wp_next_scheduled( "devhub_{$this->post_type}_import_all_markdown" ) ) {
			wp_schedule_event( time(), $this->cron_interval, "devhub_{$this->post_type}_import_all_markdown" );
		}
	}
}
