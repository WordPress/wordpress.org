<?php
/**
 * Meetup Groups.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Utilities;

/**
 * Class Meetup_Groups
 *
 * @package WordCamp\Reports\Report
 */
class Meetup_Groups extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Meetup Groups';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'meetup-groups';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'The number of meetup groups in the Chapter program on a given date and the number of groups that joined during a given time period.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		Retrieve data about groups in the Chapter program from the Meetup.com API. Only groups who joined the Chapter program before the specified end date will be included.
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'meetup';

	/**
	 * Shortcode tag for outputting the public report form.
	 *
	 * @var string
	 */
	public static $shortcode_tag = 'meetup_groups_report';

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = array(
		'name'          => '',
		'urlname'       => '',
		'city'          => '',
		'state'         => '',
		'country'       => '',
		'lat'           => 0,
		'lon'           => 0,
		'member_count'  => 0,
		'founded_date'  => 0,
		'pro_join_date' => 0,
	);

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

		$meetup = new Utilities\Meetup_Client();

		$data = $meetup->get_groups( array(
			'pro_join_date_max' => $this->end_date->getTimestamp() * 1000, // Meetup API uses milliseconds.
		) );

		if ( is_wp_error( $data ) ) {
			$this->error = $this->merge_errors( $this->error, $data );

			return array();
		}

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
		$joined_groups = array_filter( $data, function( $group ) {
			$join_date = new \DateTime();
			$join_date->setTimestamp( intval( $group['pro_join_date'] / 1000 ) ); // Meetup API uses milliseconds.

			if ( $join_date >= $this->start_date && $join_date <= $this->end_date ) {
				return true;
			}

			return false;
		} );

		$compiled_data = array(
			'total_groups'              => count( $data ),
			'total_groups_by_country'   => $this->count_groups_by_country( $data ),
			'total_members'             => $this->count_members( $data ),
			'total_members_by_country'  => $this->count_group_members_by_country( $data ),
			'joined_groups'             => count( $joined_groups ),
			'joined_groups_by_country'  => $this->count_groups_by_country( $joined_groups ),
			'joined_members'            => $this->count_members( $joined_groups ),
			'joined_members_by_country' => $this->count_group_members_by_country( $joined_groups ),
		);

		return $compiled_data;
	}

	/**
	 * From a list of groups, count how many total members there are.
	 *
	 * @param array $groups Meetup groups.
	 *
	 * @return int The number of total members.
	 */
	protected function count_members( $groups ) {
		return array_reduce( $groups, function( $carry, $item ) {
			$carry += absint( $item['member_count'] );

			return $carry;
		}, 0 );
	}

	/**
	 * From a list of groups, count how many there are in each country.
	 *
	 * @param array $groups Meetup groups.
	 *
	 * @return array An associative array of country keys and group count values, sorted high to low.
	 */
	protected function count_groups_by_country( $groups ) {
		$counts = array_reduce( $groups, function( $carry, $item ) {
			$country = $item['country'];

			if ( ! isset( $carry[ $country ] ) ) {
				$carry[ $country ] = 0;
			}

			$carry[ $country ] ++;

			return $carry;
		}, array() );

		arsort( $counts );

		return $counts;
	}

	/**
	 * From a list of groups, count how many total group members there are in each country.
	 *
	 * @param array $groups Meetup groups.
	 *
	 * @return array An associative array of country keys and group member count values, sorted high to low.
	 */
	protected function count_group_members_by_country( $groups ) {
		$counts = array_reduce( $groups, function( $carry, $item ) {
			$country = $item['country'];

			if ( ! isset( $carry[ $country ] ) ) {
				$carry[ $country ] = 0;
			}

			$carry[ $country ] += absint( $item['member_count'] );

			return $carry;
		}, array() );

		arsort( $counts );

		return $counts;
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

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include Reports\get_views_dir_path() . 'html/meetup-groups.php';
		}
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$refresh     = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Show results' === $action
		     && wp_verify_nonce( $nonce, 'run-report' )
		     && current_user_can( 'manage_network' )
		) {
			$options = array(
				'earliest_start' => new \DateTime( '2015-01-01' ), // Chapter program started in 2015.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}

		include Reports\get_views_dir_path() . 'report/meetup-groups.php';
	}

	/**
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	public static function export_to_file() {
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$refresh     = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( wp_verify_nonce( $nonce, 'run-report' ) && current_user_can( 'manage_network' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2015-01-01' ), // Chapter program started in 2015.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}

			$filename   = array( $report::$name );
			$filename[] = $report->start_date->format( 'Y-m-d' );
			$filename[] = $report->end_date->format( 'Y-m-d' );

			$headers = array( 'Name', 'URL', 'City', 'State', 'Country', 'Latitude', 'Longitude', 'Member Count', 'Date Founded', 'Date Joined' );

			$data = $report->get_data();

			array_walk( $data, function( &$group ) {
				$group['urlname']       = ( $group['urlname'] ) ? esc_url( 'https://www.meetup.com/' . $group['urlname'] . '/' ) : '';
				$group['founded_date']  = ( $group['founded_date'] ) ? date( 'Y-m-d', $group['founded_date'] / 1000 ) : '';
				$group['pro_join_date'] = ( $group['pro_join_date'] ) ? date( 'Y-m-d', $group['pro_join_date'] / 1000 ) : '';
			} );

			$exporter = new Utilities\Export_CSV( array(
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
		$action = filter_input( INPUT_GET, 'action' );

		$years    = self::year_array( absint( date( 'Y' ) ), 2015 );
		$quarters = self::quarter_array();
		$months   = self::month_array();

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
				'earliest_start' => new \DateTime( '2015-01-01' ), // Chapter program started in 2015.
			);

			$report = new self( $range['start_date'], $range['end_date'], $options );
		}

		include Reports\get_views_dir_path() . 'public/meetup-groups.php';
	}
}
