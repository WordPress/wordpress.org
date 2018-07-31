<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use DateTime;
use WP_Post, WP_Query;
use function WordCamp\Reports\{get_assets_url, get_assets_dir_path, get_views_dir_path};
use WordCamp\Reports\Utility\Date_Range;
use function WordCamp\Reports\Validation\{validate_date_range, validate_wordcamp_id, validate_wordcamp_status};
use WordCamp_Admin, WordCamp_Loader;
use WordCamp\Utilities\Export_CSV;

/**
 * Class WordCamp_Details
 *
 * A report class for exporting a spreadsheet of WordCamps.
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Details extends Base {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'WordCamp Details';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'wordcamp-details';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Details about WordCamps occurring within a specified date range.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Retrieve WordCamp posts that fit within the date range and other optional criteria.</li>
			<li>Extract the data for each post that match the fields requested.</li>
			<li>Walk all of the extracted data and format it for display.</li>
		</ol>
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'wordcamp';

	/**
	 * The date range that defines the scope of the report data.
	 *
	 * @var null|Date_Range
	 */
	public $range = null;

	/**
	 * The status to filter for in the report.
	 *
	 * @var string
	 */
	public $status = '';

	/**
	 * Whether to include data for WordCamps that don't have a date set.
	 *
	 * @var bool
	 */
	public $include_dateless = false;

	/**
	 * Whether to include counts of various post types for each WordCamp.
	 *
	 * @var bool
	 */
	public $include_counts = false;

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = [];

	/**
	 * Data fields that should only be visible in a private context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $private_data_fields = [];

	/**
	 * WordCamp_Details constructor.
	 *
	 * @param string $start_date       The start of the date range for the report.
	 * @param string $end_date         The end of the date range for the report.
	 * @param string $status           Optional. The status ID to filter for in the report.
	 * @param bool   $include_dateless Optional. True to include data for WordCamps that don't have a date set. Default false.
	 * @param bool   $include_counts   Optional. True to include counts of various post types for each WordCamp. Default false.
	 * @param array  $options          {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and the functions in WordCamp\Reports\Validation for additional parameters.
	 *
	 *     @type array $status_subset A list of valid status IDs.
	 *     @type array $fields        Not implemented yet.
	 * }
	 */
	public function __construct( $start_date, $end_date, $status = '', $include_dateless = false, $include_counts = false, array $options = [] ) {
		// Report-specific options.
		$options = wp_parse_args( $options, [
			'status_subset' => [],
			'fields'        => [],
		] );

		parent::__construct( $options );

		try {
			$this->range = validate_date_range( $start_date, $end_date, $options );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-date-error',
				$e->getMessage()
			);
		}

		if ( $status && 'any' !== $status ) {
			try {
				$this->status = validate_wordcamp_status( $status, $options );
			} catch ( Exception $e ) {
				$this->error->add(
					self::$slug . '-status-error',
					$e->getMessage()
				);
			}
		}

		$this->include_dateless = wp_validate_boolean( $include_dateless );
		$this->include_counts   = wp_validate_boolean( $include_counts );

		$public_data_field_keys = array_merge(
			[
				'Name',
				'Status',
			],
			WordCamp_Loader::get_public_meta_keys()
		);
		$this->public_data_fields = array_fill_keys( $public_data_field_keys, '' );

		$private_data_field_keys = array_merge(
			[
				'ID',
				'Tickets',
				'Speakers',
				'Sponsors',
				'Organizers',
			],
			array_diff( $this->get_meta_keys(), array_keys( $this->public_data_fields ) )
		);
		$this->private_data_fields = array_fill_keys( $private_data_field_keys, '' );

		try {
			$this->options['fields'] = $this->validate_fields_input( $this->options['fields'] );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-fields-error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Check the array of fields to include in the spreadsheet against the safelist of data fields.
	 *
	 * @param array $fields
	 *
	 * @return array The validated fields.
	 * @throws Exception
	 */
	protected function validate_fields_input( array $fields ) {
		$valid_fields = $this->get_data_fields_safelist();
		$fields       = array_unique( $fields );

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $valid_fields ) ) {
				throw new Exception( sprintf(
					'Invalid field: %s',
					esc_html( $field )
				) );
			}
		}

		return $fields;
	}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key_segments = [
			parent::get_cache_key(),
			$this->range->generate_cache_key_segment(),
		];

		if ( $this->status ) {
			$cache_key_segments[] = $this->status;
		}

		if ( $this->include_dateless ) {
			$cache_key_segments[] = '+dateless';
		}

		if ( $this->include_counts ) {
			$cache_key_segments[] = '+counts';
		}

		return implode( '_', $cache_key_segments );
	}

	/**
	 * Generate a cache expiration interval.
	 *
	 * @return int A time interval in seconds.
	 */
	protected function get_cache_expiration() {
		return $this->range->generate_cache_duration( parent::get_cache_expiration() );
	}

	/**
	 * Query and parse the data for the report.
	 *
	 * @return array
	 */
	public function get_data() {
		// Bail if there are errors.
		if ( ! empty( $this->error->get_error_messages() ) ) {
			return array();
		}

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		$data = [];

		$wordcamp_posts = $this->get_wordcamp_posts();

		foreach ( $wordcamp_posts as $post ) {
			$data[] = $this->fill_data_row( $post );
		}

		$data = $this->filter_data_fields( $data );

		// Reorder of the fields in each row.
		$field_order = array_fill_keys( self::get_field_order(), '' );
		array_walk( $data, function( &$row ) use ( $field_order ) {
			$row = array_intersect_key( array_replace( $field_order, $row ), $row );
		} );

		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Compile the report data into results.
	 *
	 * Currently unused.
	 *
	 * @param array $data The data to compile.
	 *
	 * @return array
	 */
	public function compile_report_data( array $data ) {
		return $data;
	}

	/**
	 * Get the full list of fields in the order they should appear in.
	 *
	 * @return array
	 */
	protected static function get_field_order() {
		/* @var WordCamp_Admin $wordcamp_admin */
		global $wordcamp_admin;

		return array_merge(
			[
				'ID',
				'Name',
			],
			array_keys( $wordcamp_admin->meta_keys( 'wordcamp' ) ),
			[
				'Status',
				'Tickets',
				'Speakers',
				'Sponsors',
				'Organizers',
			],
			array_keys( $wordcamp_admin->meta_keys( 'contributor' ) ),
			array_keys( $wordcamp_admin->meta_keys( 'organizer' ) ),
			array_keys( $wordcamp_admin->meta_keys( 'venue' ) ),
			[
				'_venue_coordinates',
				'_venue_city',
				'_venue_state',
				'_venue_country_code',
				'_venue_country_name',
				'_venue_zip',
			]
		);
	}

	/**
	 * Format the data for human-readable display.
	 *
	 * @param array $data The data to prepare.
	 *
	 * @return array
	 */
	protected function prepare_data_for_display( array $data ) {
		$all_statuses = WordCamp_Loader::get_post_statuses();

		array_walk( $data, function( &$row ) use ( $all_statuses ) {
			foreach ( $row as $key => $value ) {
				if ( ! in_array( $key, $this->options['fields'], true ) ) {
					unset( $row[ $key ] );
					continue;
				}

				switch ( $key ) {
					case 'Status':
						$row[ $key ] = $all_statuses[ $value ];
						break;
					case 'Tickets':
					case 'Speakers':
					case 'Sponsors':
					case 'Organizers':
						$row[ $key ] = number_format_i18n( $value );
						break;
					case 'Start Date (YYYY-mm-dd)':
					case 'End Date (YYYY-mm-dd)':
					case 'Contributor Day Date (YYYY-mm-dd)':
						$row[ $key ] = ( $value ) ? date( 'Y-m-d', $value ) : '';
						break;
					case 'Exhibition Space Available':
					case 'Contributor Day':
						$row[ $key ] = ( $value ) ? 'Yes' : 'No';
						break;
					case '_venue_coordinates':
						if ( is_array( $value ) ) {
							$row[ $key ] = implode( ', ', $value );
						}
						break;
				}
			}
		} );

		return $data;
	}

	/**
	 * Get all current WordCamp posts.
	 *
	 * @return array
	 */
	protected function get_wordcamp_posts() {
		$post_args = array(
			'post_type'           => WCPT_POST_TYPE_ID,
			'post_status'         => 'any',
			'posts_per_page'      => 9999,
			'nopaging'            => true,
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
			'orderby'             => 'meta_value_num title',
			'order'               => 'ASC',
			'meta_query'          => [
				[
					'key'      => 'Start Date (YYYY-mm-dd)',
					'value'    => array( $this->range->start->getTimestamp(), $this->range->end->getTimestamp() ),
					'compare'  => 'BETWEEN',
					'type'     => 'NUMERIC',
				],
			],
		);

		if ( $this->include_dateless ) {
			$post_args['meta_query'] = array_merge( $post_args['meta_query'], [
				'relation' => 'OR',
				[
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '=',
					'value'   => '',
				],
			] );

			// Don't include really old camps with no date or ones that didn't exist during the date range.
			$post_args['date_query'] = [
				[
					'before' => $this->range->end->format( 'Y-m-d' ),
					'after'  => $this->range->start->format( 'Y-m-d' ) . ' - 1 year',
				],
			];
		}

		if ( $this->options['public'] ) {
			$post_args['post_status'] = WordCamp_Loader::get_public_post_statuses();
		}

		if ( $this->status ) {
			$status_report = new WordCamp_Status(
				$this->range->start->format( 'Y-m-d' ),
				$this->range->end->format( 'Y-m-d' ),
				$this->status,
				$this->options
			);

			$post_ids = array_keys( $status_report->get_data() );

			if ( empty( $post_ids ) ) {
				return [];
			}

			$post_args['post__in'] = $post_ids;
		}

		return get_posts( $post_args );
	}

	/**
	 * Get the values of all the relevant post meta keys for a WordCamp post.
	 *
	 * @param WP_Post $wordcamp
	 *
	 * @return array
	 */
	protected function fill_data_row( WP_Post $wordcamp ) {
		$meta_keys   = $this->get_meta_keys();

		$row = [
			'ID'     => $wordcamp->ID,
			'Name'   => $wordcamp->post_title,
			'Status' => $wordcamp->post_status,
		];

		if ( $this->include_counts ) {
			$row = array_merge( $row, $this->get_counts( $wordcamp ) );
		}

		foreach ( $meta_keys as $key ) {
			$row[ $key ] = get_post_meta( $wordcamp->ID, $key, true ) ?: '';
		}

		return $row;
	}

	/**
	 * Get a list of all the relevant meta keys for WordCamp posts.
	 *
	 * @return array
	 */
	protected function get_meta_keys() {
		/* @var WordCamp_Admin $wordcamp_admin */
		global $wordcamp_admin;
		$meta_keys = array_merge( array_keys( $wordcamp_admin->meta_keys( 'all' ) ), [
			'_venue_coordinates',
			'_venue_city',
			'_venue_state',
			'_venue_country_code',
			'_venue_country_name',
			'_venue_zip',
		] );

		return $meta_keys;
	}

	/**
	 * Count the number of various post types for a WordCamp.
	 *
	 * @param WP_Post $wordcamp
	 *
	 * @return array
	 */
	protected function get_counts( WP_Post $wordcamp ) {
		$counts = [
			'Tickets'    => 0,
			'Speakers'   => 0,
			'Sponsors'   => 0,
			'Organizers' => 0,
		];

		try {
			$ids = validate_wordcamp_id( $wordcamp->ID, [ 'require_site' => true ] );
		} catch ( Exception $e ) {
			return $counts;
		}

		$get_count = function( $post_type ) {
			$posts = new WP_Query( [
				'posts_per_page' => 1, // Only need to fetch 1 to populate total number in found_posts.
				'post_type'      => $post_type,
				'post_status'    => 'publish',
			] );

			return absint( $posts->found_posts );
		};

		switch_to_blog( $ids['site_id'] );

		// Tickets
		$stats = get_option( 'camptix_stats' );
		if ( isset( $stats['sold'] ) && ! empty( $stats['sold'] ) ) {
			$counts['Tickets'] = absint( $stats['sold'] );
		}

		// Others
		$counts['Speakers']   = $get_count( 'wcb_speaker' );
		$counts['Sponsors']   = $get_count( 'wcb_sponsor' );
		$counts['Organizers'] = $get_count( 'wcb_organizer' );

		restore_current_blog();

		return $counts;
	}

	/**
	 * Register all assets used by this report.
	 *
	 * @return void
	 */
	protected static function register_assets() {
		wp_register_script(
			self::$slug,
			get_assets_url() . 'js/' . self::$slug . '.js',
			array(),
			filemtime( get_assets_dir_path() . 'js/' . self::$slug . '.js' ),
			true
		);

		wp_register_style(
			self::$slug,
			get_assets_url() . 'css/' . self::$slug . '.css',
			array(),
			filemtime( get_assets_dir_path() . 'css/' . self::$slug . '.css' ),
			'screen'
		);
	}

	/**
	 * Enqueue JS and CSS assets for this report's admin interface.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		self::register_assets();

		wp_enqueue_script( self::$slug );
		wp_enqueue_style( self::$slug );
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date       = filter_input( INPUT_POST, 'start-date' );
		$end_date         = filter_input( INPUT_POST, 'end-date' );
		$include_dateless = filter_input( INPUT_POST, 'include_dateless', FILTER_VALIDATE_BOOLEAN );
		$status           = filter_input( INPUT_POST, 'status' );
		$refresh          = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action           = filter_input( INPUT_POST, 'action' );
		$nonce            = filter_input( INPUT_POST, self::$slug . '-nonce' );
		$statuses         = WordCamp_Loader::get_post_statuses();

		$field_order      = array_fill_keys( self::get_field_order(), '' );
		$field_defaults   = array_replace( $field_order, [
			'ID'                      => 'checked',
			'Name'                    => 'checked disabled',
			'Start Date (YYYY-mm-dd)' => 'checked',
			'End Date (YYYY-mm-dd)'   => 'checked',
			'Location'                => 'checked',
			'URL'                     => 'checked',
		] );

		$shadow_report    = new self( '', '', '', false, false, [ 'public' => false ] );
		$available_fields = array_intersect_key( $field_defaults, $shadow_report->get_data_fields_safelist() );

		include get_views_dir_path() . 'report/wordcamp-details.php';
	}

	/**
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	public static function export_to_file() {
		$start_date       = filter_input( INPUT_POST, 'start-date' );
		$end_date         = filter_input( INPUT_POST, 'end-date' );
		$include_dateless = filter_input( INPUT_POST, 'include_dateless', FILTER_VALIDATE_BOOLEAN );
		$status           = filter_input( INPUT_POST, 'status' );
		$fields           = filter_input( INPUT_POST, 'fields', FILTER_SANITIZE_STRING, [ 'flags' => FILTER_REQUIRE_ARRAY ] );
		$refresh          = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action           = filter_input( INPUT_POST, 'action' );
		$nonce            = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( wp_verify_nonce( $nonce, 'run-report' ) && current_user_can( 'manage_network' ) ) {
			$include_counts = false;
			if ( ! empty( array_intersect( $fields, [ 'Tickets', 'Speakers', 'Sponsors', 'Organizers' ] ) ) ) {
				$include_counts = true;
			}

			// The "Name" field should always be included, but does not get submitted because the input is disabled,
			// so add it in here.
			$fields[] = 'Name';

			$options = array(
				'fields'         => $fields,
				'public'         => false,
				'earliest_start' => new DateTime( '2006-01-01' ), // No WordCamp posts before 2006.
			);

			if ( $status ) {
				$options['earliest_start'] = new DateTime( '2015-01-01' ); // No status log data before 2015.
			}

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $status, $include_dateless, $include_counts, $options );

			$filename = [ $report::$name ];
			$filename[] = $report->range->start->format( 'Y-m-d' );
			$filename[] = $report->range->end->format( 'Y-m-d' );
			if ( $report->status ) {
				$filename[] = $report->status;
			}
			if ( $report->include_dateless ) {
				$filename[] = 'include-dateless';
			}
			if ( $report->include_counts ) {
				$filename[] = 'include-counts';
			}

			$data = $report->prepare_data_for_display( $report->get_data() );

			$headers = ( ! empty( $data ) ) ? array_keys( $data[0] ) : [];

			$exporter = new Export_CSV( array(
				'filename' => $filename,
				'headers'  => $headers,
				'data'     => $data,
			) );

			if ( ! empty( $report->error->get_error_messages() ) ) {
				$exporter->error = $report->merge_errors( $report->error, $exporter->error );
			}

			$exporter->emit_file();
		} // End if().
	}
}
