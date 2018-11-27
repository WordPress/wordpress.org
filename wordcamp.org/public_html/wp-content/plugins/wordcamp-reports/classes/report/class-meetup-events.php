<?php
/**
 * Meetup Groups.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use DateTime, DateInterval;
use WP_Error;
use function WordCamp\Reports\get_views_dir_path;
use function WordCamp\Reports\Validation\validate_date_range;
use function WordCamp\Reports\Time\{year_array, quarter_array, month_array, convert_time_period_to_date_range};
use WordCamp\Utilities\{Meetup_Client, Export_CSV};

/**
 * Class Meetup_Events
 *
 * @package WordCamp\Reports\Report
 */
class Meetup_Events extends Base {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Meetup Events';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'meetup-events';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Details on meetup events during a given time period.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = '
		Retrieve data about events in the Chapter program from the Meetup.com API.
	';

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'meetup';

	/**
	 * Shortcode tag for outputting the public report form.
	 *
	 * @todo
	 *
	 * @var string
	 */
	//public static $shortcode_tag = 'meetup_events_report';

	/**
	 * The date range that defines the scope of the report data.
	 *
	 * @var null|Date_Range
	 */
	public $range = null;

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = [
		'id'           => '',
		'event_url'    => '',
		'name'         => '',
		'description'  => '',
		'time'         => 0,
		'group'        => '',
		'city'         => '',
		'l10n_country' => '',
		'latitude'     => 0,
		'longitude'    => 0,
	];

	/**
	 * Meetup_Events constructor.
	 *
	 * @param string $start_date       The start of the date range for the report.
	 * @param string $end_date         The end of the date range for the report.
	 * @param array  $options          {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and the functions in WordCamp\Reports\Validation for additional parameters.
	 * }
	 */
	public function __construct( $start_date, $end_date, array $options = [] ) {
		parent::__construct( $options );

		try {
			$this->range = validate_date_range( $start_date, $end_date, $options );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-date-error',
				$e->getMessage()
			);
		}
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

		$meetup = new Meetup_Client();
		$groups = $meetup->get_groups();

		if ( is_wp_error( $groups ) ) {
			$this->error->add( $groups->get_error_code(), $groups->get_error_message() );
			return array();
		}

		$group_ids = wp_list_pluck( $groups, 'id' );
		$groups    = array_combine( $group_ids, $groups );

		$events = $meetup->get_events( $group_ids, array(
			'status' => 'upcoming,past',
			'time'   => sprintf(
				'%d,%d',
				$this->range->start->getTimestamp() * 1000,
				$this->range->end->getTimestamp() * 1000
			),
		) );

		$data = [];

		$relevant_keys = array_fill_keys( [ 'id', 'event_url', 'name', 'description', 'time', 'group', 'city', 'l10n_country', 'latitude', 'longitude' ], '' );

		foreach ( $events as $event ) {
			$group_id = $event['group']['id'];
			$event    = wp_parse_args( $event, $relevant_keys );

			$event['description']  = isset( $event['description'] ) ? trim( $event['description'] ) : '';
			$event['time']         = absint( $event['time'] ) / 1000; // Convert to seconds.
			$event['group']        = isset( $event['group']['name'] ) ? $event['group']['name'] : $groups[ $group_id ]['name'];
			$event['city']         = isset( $event['venue']['city'] ) ? $event['venue']['city'] : $groups[ $group_id ]['city'];
			$event['l10n_country'] = isset( $event['venue']['localized_country_name'] ) ? $event['venue']['localized_country_name'] : $groups[ $group_id ]['country'];
			$event['latitude']     = ! empty( $event['venue']['lat'] ) ? $event['venue']['lat'] : $groups[ $group_id ]['lat'];
			$event['longitude']    = ! empty( $event['venue']['lon'] ) ? $event['venue']['lon'] : $groups[ $group_id ]['lon'];

			$data[] = array_intersect_key( $event, $relevant_keys );
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
		$compiled_data = [
			'total_events'              => count( $data ),
			'total_events_by_country'   => [],
			'total_events_by_group'     => [],
			'monthly_events'            => [],
			'monthly_events_by_country' => [],
			'monthly_events_by_group'   => [],
		];

		try {
			$compiled_data['monthly_events'] = $this->count_events_by_month( $data );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-compilation-error',
				$e->getMessage()
			);

			return $compiled_data;
		}

		try {
			$events_by_country = $this->sort_events_by_field( 'l10n_country', $data );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-compilation-error',
				$e->getMessage()
			);

			return $compiled_data;
		}

		uasort( $events_by_country, function( $a, $b ) {
			$count_a = count( $a );
			$count_b = count( $b );

			if ( $count_a === $count_b ) {
				return 0;
			}

			return ( $count_a < $count_b ) ? 1 : -1;
		} );

		foreach ( $events_by_country as $country => $events ) {
			$compiled_data['total_events_by_country'][ $country ] = count( $events );

			try {
				$compiled_data['monthly_events_by_country'][ $country ] = $this->count_events_by_month( $events );
			} catch ( Exception $e ) {
				$this->error->add(
					self::$slug . '-compilation-error',
					$e->getMessage()
				);

				return $compiled_data;
			}
		}

		$compiled_data['countries_with_events'] = count( $compiled_data['total_events_by_country'] );

		try {
			$events_by_group = $this->sort_events_by_field( 'group', $data );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-compilation-error',
				$e->getMessage()
			);

			return $compiled_data;
		}

		uasort( $events_by_group, function( $a, $b ) {
			$count_a = count( $a );
			$count_b = count( $b );

			if ( $count_a === $count_b ) {
				return 0;
			}

			return ( $count_a < $count_b ) ? 1 : -1;
		} );

		foreach ( $events_by_group as $group => $events ) {
			$compiled_data['total_events_by_group'][ $group ] = count( $events );

			try {
				$compiled_data['monthly_events_by_group'][ $group ] = $this->count_events_by_month( $events );
			} catch ( Exception $e ) {
				$this->error->add(
					self::$slug . '-compilation-error',
					$e->getMessage()
				);

				return $compiled_data;
			}
		}

		$compiled_data['groups_with_events'] = count( $compiled_data['total_events_by_group'] );

		$meetup       = new Meetup_Client();
		$total_groups = absint( $meetup->get_result_count( 'pro/wordpress/groups' ) );

		$compiled_data['groups_with_no_events'] = $total_groups - $compiled_data['groups_with_events'];

		return $compiled_data;
	}

	/**
	 * Sort the events by the given field.
	 *
	 * @param string $field
	 * @param array  $data
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function sort_events_by_field( $field, array $data ) {
		if ( ! array_key_exists( $field, $data[0] ) ) {
			throw new Exception( sprintf(
				'Cannot sort events by %s.',
				esc_html( $field )
			) );
		}

		return array_reduce( $data, function( $carry, $item ) use ( $field ) {
			$group = $item[ $field ];

			if ( ! isset( $carry[ $group ] ) ) {
				$carry[ $group ] = [];
			}

			$carry[ $group ][] = $item;

			return $carry;
		}, [] );
	}

	/**
	 * Count how many events were in each month in the date range.
	 *
	 * @param array $events
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function count_events_by_month( array $events ) {
		$month_iterator = new DateTime( $this->range->start->format( 'Y-m' ) . '-01' );
		$end_month      = new DateTime( $this->range->end->format( 'Y-m' ) . '-01' );
		$interval       = new DateInterval( 'P1M' );
		$months         = [];

		while ( $month_iterator <= $end_month ) {
			$months[ $month_iterator->format( 'M Y' ) ] = 0;
			$month_iterator->add( $interval );
		}

		if ( count( $months ) < 2 ) {
			return [];
		}

		foreach ( $events as $event ) {
			$event_date = new DateTime();
			$event_date->setTimestamp( $event['time'] );

			$event_month = $event_date->format( 'M Y' );

			$months[ $event_month ] ++;
		}

		return $months;
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

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include get_views_dir_path() . 'html/meetup-events.php';
		}
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$refresh    = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action     = filter_input( INPUT_POST, 'action' );
		$nonce      = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Show results' === $action
			&& wp_verify_nonce( $nonce, 'run-report' )
			&& current_user_can( 'manage_network' )
		) {
			$options = array(
				'earliest_start' => new DateTime( '2015-01-01' ), // Chapter program started in 2015.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $options );
		}

		include get_views_dir_path() . 'report/meetup-events.php';
	}

	/**
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	public static function export_to_file() {
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$refresh    = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action     = filter_input( INPUT_POST, 'action' );
		$nonce      = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'run-report' ) || ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export this data.', 'wordcamporg' ) );
		}

		$options = array(
			'earliest_start' => new DateTime( '2015-01-01' ), // Chapter program started in 2015.
		);

		if ( $refresh ) {
			$options['flush_cache'] = true;
		}

		$report = new self( $start_date, $end_date, $options );

		$filename   = array( $report::$name );
		$filename[] = $report->range->start->format( 'Y-m-d' );
		$filename[] = $report->range->end->format( 'Y-m-d' );

		$headers = [ 'Event ID', 'Event URL', 'Event Name', 'Description', 'Date', 'Group Name', 'City', 'Country (localized)', 'Latitude', 'Longitude' ];

		$data = $report->get_data();

		array_walk( $data, function( &$event ) {
			$date = new DateTime();
			$date->setTimestamp( $event['time'] );
			$event['time'] = $date->format( 'Y-m-d' );
		} );

		$exporter = new Export_CSV( array(
			'filename' => $filename,
			'headers'  => $headers,
			'data'     => $data,
		) );

		if ( ! empty( $report->error->get_error_messages() ) ) {
			$exporter->error = $report->merge_errors( $report->error, $exporter->error );
		}

		$exporter->emit_file();
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

		$years    = year_array( absint( date( 'Y' ) ), 2015 );
		$quarters = quarter_array();
		$months   = month_array();

		if ( ! $year ) {
			$year = absint( date( 'Y' ) );
		}

		if ( ! $period ) {
			$period = absint( date( 'm' ) );
		}

		$report = null;

		if ( 'Show results' === $action ) {
			$error = null;

			try {
				$range = convert_time_period_to_date_range( $year, $period );
			} catch ( Exception $e ) {
				$error = new WP_Error(
					self::$slug . '-time-period-error',
					$e->getMessage()
				);
			}

			$options = array(
				'earliest_start' => new DateTime( '2015-01-01' ), // Chapter program started in 2015.
			);

			$report = new self( $range->start, $range->end, $options );

			if ( ! is_null( $error ) ) {
				$report->merge_errors( $error, $report->error );
			}
		}

		include get_views_dir_path() . 'public/meetup-events.php';
	}
}
