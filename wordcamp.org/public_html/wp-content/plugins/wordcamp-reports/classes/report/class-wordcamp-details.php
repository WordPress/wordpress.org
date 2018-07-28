<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use DateTime;
use WP_Post;
use WordCamp\Reports;
use WordCamp\Reports\Report\WordCamp_Status;
use WordCamp\Reports\Utility\Date_Range;
use function WordCamp\Reports\Validation\{validate_date_range, validate_wordcamp_status};
use function WordCamp\Reports\Time\modify_cache_expiration_for_date_range;
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
			<li>Extract the post meta values for each post that match the fields requested.</li>
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
	 * The fields to include in the report output.
	 *
	 * @var array
	 */
	public $fields = [];

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
	 * @param string $start_date The start of the date range for the report.
	 * @param string $end_date   The end of the date range for the report.
	 * @param string $status     Optional. The status ID to filter for in the report.
	 * @param array  $fields     Not implemented yet.
	 * @param array  $options    {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and the functions in WordCamp\Reports\Validation for additional parameters.
	 *
	 *     @type bool $include_dateless True to include WordCamps that don't have a date set. Default false.
	 * }
	 */
	public function __construct( $start_date, $end_date, $status = '', array $fields = [], array $options = [] ) {
		// Report-specific options.
		$options = wp_parse_args( $options, array(
			'include_dateless' => false,
		) );

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

		try {
			$this->fields = $this->validate_fields_input( $fields );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-fields-error',
				$e->getMessage()
			);
		}

		$this->public_data_fields = array_fill_keys( array_merge(
			[
				'ID',
				'Name',
				'Status',
			],
			WordCamp_Loader::get_public_meta_keys()
		), '' );

		$this->private_data_fields = array_fill_keys( array_diff(
			$this->get_meta_keys(),
			array_keys( $this->public_data_fields )
		), '' );
	}

	/**
	 * TODO
	 *
	 * @param array $fields
	 */
	protected function validate_fields_input( array $fields ) {}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = parent::get_cache_key() . '_' . $this->range->start->getTimestamp() . '-' . $this->range->end->getTimestamp();

		if ( $this->status ) {
			$cache_key .= '_' . $this->status;
		}

		return $cache_key;
	}

	/**
	 * Generate a cache expiration interval.
	 *
	 * @return int A time interval in seconds.
	 */
	protected function get_cache_expiration() {
		$original_expiration = parent::get_cache_expiration();

		try {
			$expiration = modify_cache_expiration_for_date_range(
				$original_expiration,
				$this->range
			);
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-cache-error',
				$e->getMessage()
			);

			return $original_expiration;
		}

		return $expiration;
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
			$data[] = $this->extract_wordcamp_fields( $post );
		}

		$data = $this->filter_data_fields( $data );
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
				switch ( $key ) {
					case 'Status':
						$row[ $key ] = $all_statuses[ $value ];
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

		if ( $this->options['include_dateless'] ) {
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
	protected function extract_wordcamp_fields( WP_Post $wordcamp ) {
		$meta_keys = $this->get_meta_keys();

		$row = [
			'ID'     => $wordcamp->ID,
			'Name'   => $wordcamp->post_title,
			'Status' => $wordcamp->post_status,
		];

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

		include Reports\get_views_dir_path() . 'report/wordcamp-details.php';
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
		$refresh          = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action           = filter_input( INPUT_POST, 'action' );
		$nonce            = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( wp_verify_nonce( $nonce, 'run-report' ) && current_user_can( 'manage_network' ) ) {
			$options = array(
				'public'           => false,
				'include_dateless' => $include_dateless,
				'earliest_start'   => new DateTime( '2006-01-01' ), // No WordCamp posts before 2006.
			);

			if ( $status ) {
				$options['earliest_start'] = new DateTime( '2015-01-01' ); // No status log data before 2015.
			}

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $status, [], $options );

			$filename = [ $report::$name ];
			$filename[] = $report->range->start->format( 'Y-m-d' );
			$filename[] = $report->range->end->format( 'Y-m-d' );
			if ( $report->status ) {
				$filename[] = $report->status;
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
