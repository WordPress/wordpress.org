<?php
/**
 * Implements class for generating meetup report that allows to filter with status.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use function WordCamp\Reports\Time\{year_array, quarter_array, month_array};
use function WordCamp\Reports\{get_views_dir_path};
use function WordCamp\Reports\Validation\{validate_date_range};
use WordPress_Community\Applications\Meetup_Application;

/**
 * Class Meetup_Status
 *
 * A report class for generating a snapshot of Meetup status activity during a specified date range
 */
class Meetup_Status extends Base_Status {

	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Meetup Status';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'meetup-status';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Meetup application status changes during a given time period';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
	Retrieve all Meetup posts which have status change in given time range.
";

	/**
	 * A container object to hold error messages.
	 *
	 * @var \WP_Error
	 */
	public $error = null;

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'meetup';

	/**
	 * Shortcode tag for outputting the public report from
	 *
	 * @var string
	 */
	public static $shortcode_tag = 'meetup_status_report';

	/**
	 * The date range that defines the scope of the report data.
	 *
	 * @var null|Date_Range
	 */
	public $range = null;

	/**
	 * The status to filter for in the report
	 *
	 * @var string
	 */
	public $status = 'any';

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = array(
		'name'          => '',
		'logs'          => array(),
		'latest_log'    => '',
		'latest_status' => '',
	);

	/**
	 * Meetup_Status constructor.
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @param string $status
	 * @param array $options
	 */
	public function __construct( $start_date, $end_date, $status = '', array $options = array() ) {

		parent::__construct( $options );

		try {
			$this->range = validate_date_range( $start_date, $end_date, $options );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-date-error',
				$e->getMessage()
			);
		}
		$this->status = $status;
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
	 * Retrieve log data for Meetup posts matching date range and status.
	 *
	 * @return array|mixed|null
	 */
	public function get_data() {
		if ( ! empty( $this->error->get_error_messages() ) ) {
			return array();
		}

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		$meetup_posts = $this->get_meetup_posts();
		$data         = array();

		// Ensure status labels can match status log messages.
		add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		foreach ( $meetup_posts as $meetup ) {
			$logs = $this->sort_logs( get_post_meta( $meetup->ID, '_status_change' ) );

			if ( empty( $logs ) ) {
				continue;
			}

			// Get logs within the time range and status
			$filtered_logs = array_filter( $logs, function ( $entry ) {
				return (
					$entry['timestamp'] >= $this->range->start->getTimestamp()
					&&
					$entry['timestamp'] <= $this->range->end->getTimestamp()
					&&
					(
						'any' === $this->status
						||
						$this->get_log_status_result( $entry, Meetup_Application::get_post_statuses() ) === $this->status
					)
				);
			} ) ;

			if ( empty( $filtered_logs ) ) {
				continue;
			}

			$latest_log = end( $logs );
			$data[ $meetup->ID ] = array(
				'name' => get_the_title( $meetup ),
				'logs' => $logs,
				'latest_log' => $latest_log,
				'latest_status' => $this->get_log_status_result( $latest_log, Meetup_Application::get_post_statuses() ),
			);
		}

		// Remove the temporary locale change.
		remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$data = $this->filter_data_fields( $data );
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Get all Meetup posts which have status changed between given time fram
	 *
	 * @return array
	 */
	protected function get_meetup_posts() {
		global $wpdb;
		$meetup_post_type = WCPT_MEETUP_SLUG;
		$meetup_post_objs = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT DISTINCT post_id
			FROM {$wpdb->prefix}postmeta
			WHERE 
				meta_key LIKE '_status_change_log_$meetup_post_type%'
			AND
				meta_value >= %d
			AND 
				meta_value <= %d
			",
				$this->range->start->getTimestamp(),
				$this->range->end->getTimestamp()
			)
		);
		$meetup_post_ids = wp_list_pluck( $meetup_post_objs, 'post_id' );
		$post_args = array(
			'post_status' => array_keys( \Meetup_Admin::get_post_statuses() ),
			'post_type' => WCPT_MEETUP_SLUG,
			'posts_per_page' => -1,
			'post__in' => $meetup_post_ids,
			'orderby' => 'date',
			'order' => 'ASC',
		);
		return get_posts( $post_args );
	}

	/**
	 * Return groups of data to be returned.
	 * @param array $data
	 *
	 * @return array
	 */
	public function compile_report_data( array $data ) {
		return array(
			'meetups' => $data,
		);
	}

	/**
	 * Determine whether to render the public report form.
	 *
	 * This shortcode is limited to use on pages.
	 *
	 * @return string HTML content to display shortcode.
	 */
	public static function handle_shortcode() {
		$html = '';

		if ( 'page' === get_post_type() ) {
			self::register_assets();

			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'wordcamp-status' );

			ob_start();
			self::render_public_page();
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Render the page for this report on the front end.
	 *
	 * @return void
	 */
	public static function render_public_page() {
		$params = self::parse_public_report_input();
		$years    = year_array( absint( date( 'Y' ) ), 2015 );
		$quarters = quarter_array();
		$months   = month_array();
		$statuses = \Meetup_Admin::get_post_statuses();

		$error = $params['error'];
		$report = null;
		$period = $params['period'];
		$year = $params['year'];
		$status = $params['status'];
		if ( ! empty( $params )  && isset( $params['range'] ) ) {
			$report = new self( $params['range']->start, $params['range']->end, $params['status'], $params['options'] );
		}

		include get_views_dir_path() . 'public/meetup-status.php';
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$status     = filter_input( INPUT_POST, 'status' );
		$refresh    = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action     = filter_input( INPUT_POST, 'action' );
		$nonce      = filter_input( INPUT_POST, self::$slug . '-nonce' );
		$fields     = filter_input( INPUT_POST, 'fields', FILTER_SANITIZE_STRING, [ 'flags' => FILTER_REQUIRE_ARRAY ] );
		$statuses   = Meetup_Application::get_post_statuses();

		$field_defaults = [
			'ID'                      => 'checked',
			'Name'                    => 'checked disabled',
			'Start Date (YYYY-mm-dd)' => 'checked',
			'End Date (YYYY-mm-dd)'   => 'checked',
			'Location'                => 'checked',
			'URL'                     => 'checked',
			'Created'                 => 'checked',
			'Status'                  => 'checked',
		];

		$report = null;

		if ( wp_verify_nonce( $nonce, 'run-report' )
		     && current_user_can( 'manage_network' )
		) {
			$options = array(
				'public' => false,
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			if ( 'Show results' === $action ) {
				// $report variable is used in meetup-status.php to render report.
				$report = new self( $start_date, $end_date, $status, $options );
			} elseif ( 'Export CSV' === $action ) {
				$status_report = new self( $start_date, $end_date, $status, $options );
				$meetup_ids = array_keys( $status_report->get_data() );
				$fields[] = 'Name';
				$options['fields'] = $fields;
				$detail_report = new Meetup_Details( null, $meetup_ids, $options );
				Meetup_Details::export_to_file_common( $detail_report );
				return;
			}
		}
		include get_views_dir_path() . 'report/meetup-status.php';
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$data       = $this->compile_report_data( $this->get_data() );
		$start_date = $this->range->start;
		$end_date   = $this->range->end;
		$status     = $this->status;

		$meetups  = $data['meetups'];
		$statuses = Meetup_Application::get_post_statuses();

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include get_views_dir_path() . 'html/meetup-status.php';
		}
	}

	/**
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	public static function export_to_file() {
		$action = filter_input( INPUT_POST, 'action' );
		$report = filter_input( INPUT_GET, 'report' );
		if ( $report !== self::$slug ) {
			return;
		}
		if ( 'Export CSV' !== $action ) {
			return;
		}

		self::render_admin_page();
	}
}