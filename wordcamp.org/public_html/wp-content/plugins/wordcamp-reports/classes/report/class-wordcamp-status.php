<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use WordCamp\Reports;
use function WordCamp\Reports\Validation\validate_wordcamp_status;
use WordCamp_Loader;

/**
 * Class WordCamp_Status
 *
 * A report class for generating a snapshot of WordCamp status activity during a specified date range.
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Status extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'WordCamp Status';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'wordcamp-status';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'WordCamp application status changes during a given time period.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Retrieve all WordCamp posts that either don't have an event date yet or the event date isn't more than three months prior to the specified date range.</li>
			<li>Parse the status log for each WordCamp and filter out log entries that aren't within the date range.</li>
			<li>Filter out WordCamps that don't have any log entries within the date range and have an inactive status (rejected, cancelled, scheduled, or closed).</li>
		</ol>
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'wordcamp';

	/**
	 * Shortcode tag for outputting the public report form.
	 *
	 * @var string
	 */
	public static $shortcode_tag = 'wordcamp_status_report';

	/**
	 * The status to filter for in the report.
	 *
	 * @var string
	 */
	public $status = '';

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
	 * WordCamp_Status constructor.
	 *
	 * @param string $start_date The start of the date range for the report.
	 * @param string $end_date   The end of the date range for the report.
	 * @param string $status     Optional. The status ID to filter for in the report.
	 * @param array  $options    {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and Date_Range::__construct for additional parameters.
	 *
	 *     @type array $status_subset A list of valid status IDs.
	 * }
	 */
	public function __construct( $start_date, $end_date, $status = '', array $options = array() ) {
		// Report-specific options.
		$options = wp_parse_args( $options, array(
			'status_subset' => [],
		) );

		parent::__construct( $start_date, $end_date, $options );

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
	}

	/**
	 * Filter: Set the locale to en_US.
	 *
	 * Some translated strings in the wcpt plugin are used here for comparison and matching. To ensure
	 * that the matching happens correctly, we need need to prevent these strings from being converted
	 * to a different locale.
	 *
	 * @return string
	 */
	public function set_locale_to_en_US() {
		return 'en_US';
	}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = parent::get_cache_key();

		if ( $this->status ) {
			$cache_key .= '_' . $this->status;
		}

		return $cache_key;
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

		// Ensure status labels can match status log messages.
		add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$wordcamp_posts = $this->get_wordcamp_posts();
		$statuses       = WordCamp_Loader::get_post_statuses();
		$data           = array();

		foreach ( $wordcamp_posts as $wordcamp ) {
			$logs = $this->get_wordcamp_status_logs( $wordcamp );

			// Trim log entries occurring after the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $entry['timestamp'] > $this->end_date->getTimestamp() ) {
					return false;
				}

				return true;
			} );

			// Skip if there is no log activity before the end of the date range.
			if ( empty( $logs ) ) {
				continue;
			}

			$latest_log    = end( $logs );
			$latest_status = $this->get_log_status_result( $latest_log );
			reset( $logs );

			// Trim log entries occurring before the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $entry['timestamp'] < $this->start_date->getTimestamp() ) {
					return false;
				}

				return true;
			} );

			// Skip if there is no log activity in the date range and the camp has an inactive status.
			if ( empty( $logs ) && ( in_array( $latest_status, self::get_inactive_statuses(), true ) || ! $latest_status ) ) {
				continue;
			}

			// Skip if there is no log entry with a resulting status that matches the status filter.
			if ( $this->status && $latest_status !== $this->status ) {
				$filtered = array_filter( $logs, function( $entry ) use ( $statuses ) {
					return preg_match( '/' . preg_quote( $statuses[ $this->status ], '/' ) . '$/', $entry['message'] );
				} );

				if ( empty( $filtered ) ) {
					continue;
				}
			}

			if ( $site_id = get_wordcamp_site_id( $wordcamp ) ) {
				$name = get_wordcamp_name( $site_id );
			} else {
				$name = get_the_title( $wordcamp );
			}

			$data[ $wordcamp->ID ] = array(
				'name'          => $name,
				'logs'          => $logs,
				'latest_log'    => $latest_log,
				'latest_status' => $latest_status,
			);
		}

		// Remove the temporary locale change.
		remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$data = $this->filter_data_fields( $data );
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Compile the report data into results.
	 *
	 * @param array $data The data to compile.
	 *
	 * @return array
	 */
	public function compile_report_data( array $data ) {
		$compiled_data = array();

		$compiled_data['active_camps'] = array_filter( $data, function( $wordcamp ) {
			if ( ! empty( $wordcamp['logs'] ) ) {
				return true;
			}

			return false;
		} );

		$compiled_data['inactive_camps'] = array_filter( $data, function( $wordcamp ) {
			if ( empty( $wordcamp['logs'] ) ) {
				return true;
			}

			return false;
		} );

		return $compiled_data;
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
			'orderby'             => 'date',
			'order'               => 'ASC',
			'meta_query'          => array(
				'relation' => 'OR',
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '=',
					'value'   => '',
				),
				array(
					// Don't include WordCamps that happened more than 3 months before the start date.
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '>=',
					'value'   => strtotime( '-3 months', $this->start_date->getTimestamp() ),
					'type'    => 'NUMERIC',
				),
			),
		);

		return get_posts( $post_args );
	}

	/**
	 * Retrieve the log of status changes for a particular WordCamp.
	 *
	 * @param \WP_Post $wordcamp A WordCamp post.
	 *
	 * @return array
	 */
	protected function get_wordcamp_status_logs( \WP_Post $wordcamp ) {
		$log_entries = get_post_meta( $wordcamp->ID, '_status_change' );

		if ( ! empty( $log_entries ) ) {
			// Sort log entries in chronological order.
			usort( $log_entries, function( $a, $b ) {
				if ( $a['timestamp'] === $b['timestamp'] ) {
					return 0;
				}

				return ( $a['timestamp'] > $b['timestamp'] ) ? 1 : -1;
			} );

			return $log_entries;
		}

		return array();
	}

	/**
	 * Determine the ending status of a particular status change event.
	 *
	 * E.g. for this event:
	 *
	 *     Needs Vetting → More Info Requested
	 *
	 * The ending status would be "More Info Requested".
	 *
	 * @param array $log_entry A status change log entry.
	 *
	 * @return string
	 */
	protected function get_log_status_result( $log_entry ) {
		if ( isset( $log_entry['message'] ) ) {
			$pieces = explode( ' &rarr; ', $log_entry['message'] );

			if ( isset( $pieces[1] ) ) {
				return $this->get_status_id_from_name( $pieces[1] );
			}
		}

		return '';
	}

	/**
	 * Given the ID of a WordCamp status, determine the ID string.
	 *
	 * @param string $status_name A WordCamp status name.
	 *
	 * @return string
	 */
	protected function get_status_id_from_name( $status_name ) {
		$statuses = array_flip( WordCamp_Loader::get_post_statuses() );

		if ( isset( $statuses[ $status_name ] ) ) {
			return $statuses[ $status_name ];
		}

		return '';
	}

	/**
	 * A list of status IDs for statuses that indicate a camp is not active.
	 *
	 * @return array
	 */
	protected static function get_inactive_statuses() {
		return array(
			'wcpt-rejected',
			'wcpt-cancelled',
			'wcpt-scheduled',
			'wcpt-closed',
		);
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$data       = $this->compile_report_data( $this->get_data() );
		$start_date = $this->start_date;
		$end_date   = $this->end_date;
		$status     = $this->status;

		$active_camps   = $data['active_camps'];
		$inactive_camps = $data['inactive_camps'];
		$statuses       = WordCamp_Loader::get_post_statuses();

		if ( ! empty( $this->error->get_error_messages() ) ) {
			?>
			<div class="notice notice-error">
				<?php foreach ( $this->error->get_error_messages() as $message ) : ?>
					<?php echo wpautop( wp_kses_post( $message ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php
		} else {
			include Reports\get_views_dir_path() . 'html/wordcamp-status.php';
		}
	}

	/**
	 * Register all assets used by this report.
	 *
	 * @return void
	 */
	protected static function register_assets() {
		wp_register_script(
			self::$slug,
			Reports\get_assets_url() . 'js/' . self::$slug . '.js',
			array( 'jquery' ),
			Reports\JS_VERSION,
			true
		);

		wp_register_style(
			self::$slug,
			Reports\get_assets_url() . 'css/' . self::$slug . '.css',
			array(),
			Reports\CSS_VERSION,
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
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$status     = filter_input( INPUT_POST, 'status' );
		$refresh    = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action     = filter_input( INPUT_POST, 'action' );
		$nonce      = filter_input( INPUT_POST, self::$slug . '-nonce' );
		$statuses   = WordCamp_Loader::get_post_statuses();

		$report = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2015-01-01' ), // No status log data before 2015.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $status, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}

		include Reports\get_views_dir_path() . 'report/wordcamp-status.php';
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

			wp_enqueue_script( self::$slug );

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
		// Apparently 'year' is a reserved URL parameter on the front end, so we prepend 'report-'.
		$year   = filter_input( INPUT_GET, 'report-year', FILTER_VALIDATE_INT );
		$period = filter_input( INPUT_GET, 'period' );
		$status = filter_input( INPUT_GET, 'status' );
		$action = filter_input( INPUT_GET, 'action' );

		$years    = self::year_array( absint( date( 'Y' ) ), 2015 );
		$months   = self::month_array();
		$statuses = WordCamp_Loader::get_post_statuses();

		if ( ! $year ) {
			$year = absint( date( 'Y' ) );
		}

		if ( ! $period ) {
			$period = absint( date( 'm' ) );
		}

		$report = null;

		if ( 'Show results' === $action ) {
			$range = self::convert_time_period_to_date_range( $year, $period );

			$options = array(
				'earliest_start' => new \DateTime( '2015-01-01' ), // No status log data before 2015.
			);

			$report = new self( $range['start_date'], $range['end_date'], $status, $options );
		}

		include Reports\get_views_dir_path() . 'public/wordcamp-status.php';
	}
}
