<?php
/**
 * Implement base class for Status Reports
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use function WordCamp\Reports\{get_assets_url, get_assets_dir_path};
use function WordCamp\Reports\Time\{convert_time_period_to_date_range};
use Exception;
use DateTime;
use WP_Error;

/**
 * Class Base_Status
 * Base class for status reports (egs Meetup, WordCamps)
 *
 * @package WordCamp\Reports\Report
 */
abstract class Base_Status extends Base {

	/**
	 * Base_Status constructor.
	 *
	 * @param array $options
	 */
	public function __construct( array $options = array() ) {
		parent::__construct( $options );
	}

	/**
	 * Register all assets used by this report.
	 *
	 * @return void
	 */
	protected static function register_assets() {
		wp_register_script(
			'wordcamp-status',
			get_assets_url() . 'js/wordcamp-status.js',
			array( 'jquery', 'select2' ),
			filemtime( get_assets_dir_path() . 'js/wordcamp-status.js' ),
			true
		);

		wp_register_style(
			'wordcamp-status',
			get_assets_url() . 'css/wordcamp-status.css',
			array( 'select2' ),
			filemtime( get_assets_dir_path() . 'css/wordcamp-status.css' ),
			'screen'
		);

		Base_Details::enqueue_admin_assets();
	}

	/**
	 * Helper function to sort _status_change logs
	 *
	 * @param $logs
	 * @return array
	 */
	public function sort_logs( $logs ) {
		if ( ! empty( $logs ) ) {
			usort( $logs, function ( $a, $b ) {
				if ( $a['timestamp'] === $b['timestamp'] ) {
					return 0;
				}
				return ( $a['timestamp'] > $b['timestamp'] ) ? 1 : -1;
			} );
		}
		return $logs;
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
	 * @param array $status_list List of status_id -> status_name mapping

	 *
	 * @return string
	 */
	protected function get_log_status_result( $log_entry, $status_list ) {
		if ( isset( $log_entry['message'] ) ) {
			$pieces = explode( ' &rarr; ', $log_entry['message'] );

			if ( isset( $pieces[1] ) ) {
				return $this->get_status_id_from_name( $pieces[1], $status_list );
			}
		}

		return '';
	}


	/**
	 * Given the ID of a WordCamp status, determine the ID string.
	 *
	 * @param string $status_name A WordCamp status name.
	 * @param array $status_list List of status_id -> status_name mapping
	 *
	 * @return string
	 */
	protected function get_status_id_from_name( $status_name, $status_list ) {
		$statuses = array_flip( $status_list );

		if ( isset( $statuses[ $status_name ] ) ) {
			return $statuses[ $status_name ];
		}

		return '';
	}

	/**
	 * Enqueue JS and CSS assets for this report's admin interface.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		self::register_assets();
		wp_enqueue_style( WordCamp_Details::$slug );
		wp_enqueue_script( 'wordcamp-status' );
		wp_enqueue_style( 'wordcamp-status' );
	}

	/**
	 * Parse input params for public report.
	 *
	 * @return array|null
	 */
	public static function parse_public_report_input() {

		$action = filter_input( INPUT_GET, 'action' );

		// Apparently 'year' is a reserved URL parameter on the front end, so we prepend 'report-'.
		$year   = filter_input( INPUT_GET, 'report-year', FILTER_VALIDATE_INT );
		$period = filter_input( INPUT_GET, 'period' );
		$status = filter_input( INPUT_GET, 'status' );

		if ( ! $year ) {
			$year = absint( date( 'Y' ) );
		}

		if ( ! $period ) {
			$period = absint( date( 'm' ) );
		}

		$report  = null;
		$error   = null;;
		$options = [];
		$range    = null;
		if ( 'Show results' === $action ) {
			try {
				$range = convert_time_period_to_date_range( $year, $period );
			} catch ( Exception $e ) {
				$error = array(
					'error' => new WP_Error( 'time-period-error', $e->getMessage() ),
				);
			}

			$options = array(
				'earliest_start' => new DateTime( '2015-01-01' ), // No status log data before 2015.
			);
		}

		return array(
			'range'   => $range,
			'status'  => $status,
			'options' => $options,
			'period'  => $period,
			'year'    => $year,
			'error'   => $error,
		);
	}


}