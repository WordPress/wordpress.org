<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use DateInterval;
use WP_Query;
use function WordCamp\Reports\{get_views_dir_path};
use WordCamp\Reports\Utility\Date_Range;
use function WordCamp\Reports\Validation\{validate_date_range, validate_wordcamp_status, validate_wordcamp_id};
use WordCamp_Loader;

/**
 * Class WordCamp_Counts
 *
 * Based on the bin script php/multiple-use/wc-post-types/count-speakers-sponsors-sessions.php?rev=2795
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Counts extends Base {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'WordCamp Counts';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'wordcamp-counts';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Total and unique counts of various post types for all WordCamps occurring within a given date range.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Retrieve a list of WordCamps that occur within the specified date range using the WordCamp Details report.</li>
			<li>Filter out WordCamps that don't have a value in their URL field (and thus don't have a website), and WordCamps that don't match the selected statuses, if applicable.</li>
			<li>
				For each remaining WordCamp, retrieve the following unique, cross-site identifiers:
				<ul>
					<li>The email address of each published Attendee post</li>
					<li>The WordPress.org user ID of each published Organizer post</li>
					<li>The post ID of each published Session post</li>
					<li>The email address of each published Speaker post</li>
					<li>The website domain of each published Sponsor post, stripped of its TLD</li>
				</ul>
			</li>
			<li>Generate a unique ID for any post that does not have the desired meta value.</li>
			<li>For each post type except Sessions, compile the posts from every WordCamp site into one array and then remove duplicates.</li>
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
	 * The statuses to filter for in the report.
	 *
	 * @var array
	 */
	public $statuses = [];

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = [
		'ID'                      => 0,
		'Name'                    => '',
		'URL'                     => '',
		'Start Date (YYYY-mm-dd)' => '',
		'Status'                  => '',
		'attendees'               => 0,
		'organizers'              => 0,
		'sessions'                => 0,
		'speakers'                => 0,
		'sponsors'                => 0,
	];

	/**
	 * WordCamp_Counts constructor.
	 *
	 * @param string $start_date  The start of the date range for the report.
	 * @param string $end_date    The end of the date range for the report.
	 * @param array  $statuses    Optional. Status IDs to filter for in the report.
	 * @param array  $options    {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and Date_Range::__construct for additional parameters.
	 * }
	 */
	public function __construct( $start_date, $end_date, array $statuses = [], array $options = [] ) {
		parent::__construct( $options );

		try {
			$this->range = validate_date_range( $start_date, $end_date, $options );
		} catch ( Exception $e ) {
			$this->error->add(
				self::$slug . '-date-error',
				$e->getMessage()
			);
		}

		foreach ( $statuses as $status ) {
			try {
				$this->statuses[] = validate_wordcamp_status( $status, $options );
			} catch ( Exception $e ) {
				$this->error->add(
					self::$slug . '-status-error',
					$e->getMessage()
				);

				break;
			}
		}

		sort( $this->statuses );
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

		if ( ! empty( $this->statuses ) ) {
			$cache_key_segments[] = implode( '-', $this->statuses );
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
			return [];
		}

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		// @todo Maybe find a way to run this without having to hack the memory limit.
		ini_set( 'memory_limit', '900M' );

		$details_options = [
			'public' => false,
		];
		$details_report = new WordCamp_Details( $this->range, [], false, $details_options );

		if ( ! empty( $details_report->error->get_error_messages() ) ) {
			$this->error = $this->merge_errors( $this->error, $details_report->error );

			return [];
		}

		$wordcamps = array_filter( $details_report->get_data(), function( $wordcamp ) {
			// Skip camps with no website URL.
			if ( ! $wordcamp['URL'] ) {
				return false;
			}

			if ( ! empty( $this->statuses ) && ! in_array( $wordcamp['Status'], $this->statuses ) ) {
				return false;
			}

			return true;
		} );

		$wordcamps = array_reduce( $wordcamps, function( $carry, $item ) {
			$keep = [
				'ID'                      => '',
				'Name'                    => '',
				'URL'                     => '',
				'Start Date (YYYY-mm-dd)' => '',
				'Status'                  => '',
			];

			$carry[ $item['ID'] ] = array_intersect_key( $item, $keep );

			return $carry;
		}, [] );

		$wordcamp_ids = array_keys( $wordcamps );

		$data = [];

		foreach ( $wordcamp_ids as $wordcamp_id ) {
			try {
				$valid = validate_wordcamp_id( $wordcamp_id );

				$data[ $wordcamp_id ] = array_merge(
					$wordcamps[ $wordcamp_id ],
					$this->get_data_for_site( $valid->site_id )
				);
			} catch ( Exception $e ) {
				$this->error->add(
					self::$slug . '-wordcamp-id-error',
					$e->getMessage()
				);

				break;
			}
		}

		$uniques = [
			'attendees'  => [],
			'organizers' => [],
			'speakers'   => [],
			'sponsors'   => [],
		];

		$totals = [
			'attendees'  => 0,
			'organizers' => 0,
			'sessions'   => 0,
			'speakers'   => 0,
			'sponsors'   => 0,
		];

		foreach ( $data as $id => &$event ) {
			foreach ( $totals as $key => $bucket ) {
				if ( isset( $uniques[ $key ] ) ) {
					$uniques[ $key ] = array_unique( array_merge( $uniques[ $key ], $event[ $key ] ) );
				}

				$event[ $key ] = count( $event[ $key ] );

				$totals[ $key ] += $event[ $key ];
			}
		}

		$data['totals'] = $totals;

		$data['uniques'] = array_map( function( $group ) {
			return count( $group );
		}, $uniques );

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
		return $data;
	}

	/**
	 * Format the data for human-readable display.
	 *
	 * @param array $data The data to prepare.
	 *
	 * @return array
	 */
	public function prepare_data_for_display( array $data ) {
		$all_statuses = WordCamp_Loader::get_post_statuses();

		array_walk( $data, function( &$row ) use ( $all_statuses ) {
			foreach ( $row as $key => $value ) {
				switch ( $key ) {
					case 'Status':
						$row[ $key ] = $all_statuses[ $value ];
						break;
					case 'Start Date (YYYY-mm-dd)':
						$row[ $key ] = ( $value ) ? date( 'Y-m-d', $value ) : '';
						break;
				}
			}
		} );

		return $data;
	}

	/**
	 * Retrieve all of the data for one site.
	 *
	 * @param int $site_id
	 *
	 * @return array
	 */
	protected function get_data_for_site( $site_id ) {
		$site_data = [
			'attendees'  => [],
			'organizers' => [],
			'sessions'   => [],
			'speakers'   => [],
			'sponsors'   => [],
		];

		switch_to_blog( $site_id );

		$attendees = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'tix_attendee',
			'post_status'    => 'publish',
		] );

		$site_data['attendees'] = wp_list_pluck( $attendees->posts, 'tix_email', 'ID' );

		$organizers = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_organizer',
			'post_status'    => 'publish',
		] );

		$site_data['organizers'] = wp_list_pluck( $organizers->posts, '_wcpt_user_id', 'ID' );

		$sessions = new WP_Query( [
			'posts_per_page' => - 1,
			'post_type'      => 'wcb_session',
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'   => '_wcpt_session_type',
					'value' => 'session',
				]
			],
		] );

		$site_data['sessions'] = wp_list_pluck( $sessions->posts, 'ID' );

		$speakers = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_speaker',
			'post_status'    => 'publish',
		] );

		$site_data['speakers'] = wp_list_pluck( $speakers->posts, '_wcb_speaker_email', 'ID' );

		$sponsors = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_sponsor',
			'post_status'    => 'publish',
		] );

		$site_data['sponsors'] = array_map( function( $url ) {
			$hostname = wp_parse_url( $url, PHP_URL_HOST );

			if ( ! $hostname ) {
				return '';
			}

			$trimmed = substr( $hostname, 0, strripos( $hostname, '.' ) ); // Remove the TLD.
			$trimmed = preg_replace( '/\.com?$/', '', $trimmed ); // Remove possible secondary .com or .co.
			$trimmed = preg_replace( '/^www\./', '', $trimmed ); // Remove possible www.

			return $trimmed;
		}, wp_list_pluck( $sponsors->posts, '_wcpt_sponsor_website' ) );

		restore_current_blog();

		foreach ( $site_data as $type => &$data ) {
			if ( 'sessions' === $type ) {
				continue;
			}

			// Convert blanks to unique values.
			array_walk( $data, function( &$value, $key ) use ( $site_id ) {
				if ( ! $value ) {
					$value = "{$site_id}_{$key}";
				}
			} );
		}

		return $site_data;
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$data = $this->prepare_data_for_display( $this->get_data() );
		$start_date = $this->range->start;
		$end_date   = $this->range->end;
		$statuses   = $this->statuses;

		$uniques = array_pop( $data );
		$totals  = array_pop( $data );

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include get_views_dir_path() . 'html/wordcamp-counts.php';
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
		$statuses   = filter_input( INPUT_POST, 'statuses', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) ?: [];
		$refresh    = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action     = filter_input( INPUT_POST, 'action' );
		$nonce      = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$all_statuses = WordCamp_Loader::get_post_statuses();

		$report = null;

		if ( 'Show results' === $action
		     && wp_verify_nonce( $nonce, 'run-report' )
		     && current_user_can( 'manage_network' )
		) {
			$options = array(
				'public' => false,
				'max_interval' => new DateInterval( 'P1Y1M' ),
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $statuses, $options );
		}

		include get_views_dir_path() . 'report/wordcamp-counts.php';
	}

	/**
	 * Enqueue JS and CSS assets for this report's admin interface.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		wp_enqueue_script( 'select2' );
		wp_enqueue_style( 'select2' );
	}
}
