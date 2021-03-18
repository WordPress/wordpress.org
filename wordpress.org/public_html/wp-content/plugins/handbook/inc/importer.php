<?php
/**
 * Class providing handbook import functionality.
 *
 * @package handbook
 */

use WordPressdotorg\Markdown\Editor;
use WordPressdotorg\Markdown\Importer;

class WPorg_Handbook_Importer extends Importer {

	/**
	 * Singleton instance.
	 *
	 * @var static
	 */
	protected static $instances;

	/**
	 * Memoized user ID of default import post author.
	 *
	 * @var int
	 */
	protected static $import_user_id;

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
	 * @return WPorg_Handbook_Importer
	 */
	public static function instance( $post_type ) {
		return $instances[ $post_type ] ?? null;
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
	 * @param bool $omit_suffix Return only the base of the post type name (i.e.
	 *                          without the '-handbook' suffix). Default false.
	 * @return string
	 */
	public function get_post_type( $omit_suffix = false ) {
		return $omit_suffix ? str_replace( '-handbook', '', $this->handbook->post_type ) : $this->handbook->post_type;
	}

	/**
	 * Initializes the object.
	 *
* REDO THIS WHOLE DESCRIPTION
	 * @param string $manifest_url The manifest URL.
	 * @param array  $args         {
	 *     Optional. An array of configuration options.
	 *
	 *     @type bool   $github_edit Override edit links to link to GitHub source? Default true.
	 *     @type string $post_type   The post type base. Hypenated and without 'handbook'
	 *                               (unless it is 'handbook'). Default 'handbook'.
	 *     @type string $slug        The slug for the post type. Default is post type.
	 * }
	 */
	public function __construct( $handbook ) {
		if ( ! is_a( $handbook, 'WPorg_Handbook' ) ) {
			return new WP_Error( 'not_a_handbook', __( 'Handbook importer initialized with an invalid handbook.', 'wporg' ) );
		}

		// Get previously instantiated version.
		if ( $instance = self::instance( $handbook->post_type ) ) {
			return $instance;
		}

		$this->handbook = $handbook;

		$config = $this->handbook->get_config();
		$post_type_base = $this->get_post_type( true );

		self::$instances[ $this->get_post_type() ] = $this;

		$this->base         = $config['slug'];
		$this->manifest_url = $config['manifest'];

		add_filter( 'cron_schedules',                                 [ $this, 'filter_cron_schedules' ] );
		add_action( 'init',                                           [ $this, 'register_cron_jobs' ] );
		add_action( "handbook_{$post_type_base}_import_manifest",     [ $this, 'import_manifest' ] );
		add_action( "handbook_{$post_type_base}_import_all_markdown", [ $this, 'import_all_markdown' ] );
		add_filter( 'display_post_states',                            [ $this, 'display_post_states' ], 10, 2 );
		add_filter( 'wporg_markdown_post_data_pre_insert',            [ $this, 'assign_post_author' ] );

		$editor = new Editor( $this );
		$editor->init();
	}

	/**
	 * Returns the handbook's cron interval schedule.
	 *
	 * @param string $as_string Return the interval as a label? True if a cron
	 *                          interval should be returned as string or false
	 *                          to return array of interval's data. Default true.
	 * @return string|array
	 */
	public function get_cron_interval( $as_string = true ) {
		$cron_intervals = wp_get_schedules();
		$default = 'hourly';

		$cron_interval = $this->handbook->get_config()['cron_interval'] ?: $default;

		$cron_label = empty( $cron_intervals[ $cron_interval ] ) ? $default : $cron_interval;

		return $as_string ? $cron_label : ( $cron_intervals[ $cron_label ] ?? [] );
	}

	/**
	 * Adds 'Not Imported' post state indicator for handbook pages that aren't
	 * the result of an import.
	 *
	 * @param string[] $post_states An array of post display states.
	 * @param WP_Post  $post        The current post object.
	 * @return string[]
	 */
	public function display_post_states( $post_states, $post ) {
		$post_type = get_post_type( $post );

		if ( self::is_handbook_imported( $post_type ) && ! get_post_meta( $post->ID, $this->manifest_entry_meta_key, true ) ) {
			$post_states[] = __( 'Not Imported', 'wporg' );
		}

		return $post_states;
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
				'display'  => 'every 15 minutes'
			);
		}

		return $schedules;
	}

	/**
	 * Registers cron jobs.
	 */
	public function register_cron_jobs() {
		$cron_interval = $this->get_cron_interval();

		$post_type_base = $this->get_post_type( true );

		if ( ! wp_next_scheduled( "handbook_{$post_type_base}_import_manifest" ) ) {
			wp_schedule_event( time(), $cron_interval, "handbook_{$post_type_base}_import_manifest" );
		}

		if ( ! wp_next_scheduled( "handbook_{$post_type_base}_import_all_markdown" ) ) {
			wp_schedule_event( time(), $cron_interval, "handbook_{$post_type_base}_import_all_markdown" );
		}
	}

	/**
	 * Adds user as the default post author for imported posts that don't have
	 * a post author already assigned.
	 *
	 * By default, assigns all imported posts to the 'wordpressdotorg' user if
	 * it exists. Use {@see 'handbooks_import_default_author_slug'} to filter
	 * the default imported post author. Otherwise, no post author is assigned.
	 *
	 * @param array $post_data Post data.
	 * @return array
	 */
	public static function assign_post_author( $post_data ) {
		// Bail early if the post already has a post author specified.
		if ( isset( $post_data['post_author'] ) ) {
			return $post_data;
		}

		// Get the default import author if not already memoized.
		if ( ! self::$import_user_id ) {
			/**
			 * Filters the slug of the user to be assigned as the post
			 * author for all imported posts.
			 *
			 * @param string $slug Slug of user to assign as imported post author.
			 *                     Use empty string or false to prevent a post author
			 *                     from being assigned. Default 'wordpressdotorg'.
			 * @return string
			 */
			$default_user_slug = apply_filters( 'handbooks_import_default_author_slug', 'wordpressdotorg' );

			if ( $default_user_slug ) {
				$user = get_user_by( 'slug', $default_user_slug );
				if ( $user ) {
					self::$import_user_id = $user->ID;
				}
			}
		}

		// Add the default import author if it was found.
		if ( self::$import_user_id ) {
			$post_data['post_author'] = self::$import_user_id;
		}

		return $post_data;
	}

	/**
	 * Indicates if the specified handbook is imported.
	 *
	 * @param string $post_type Handbook post type.
	 * @return bool True is handbook is imported, else false.
	 */
	public static function is_handbook_imported( $post_type ) {
		$config = WPorg_Handbook_Init::get_handbooks_config( $post_type );

		return ! empty( $config['manifest'] );
	}

}
