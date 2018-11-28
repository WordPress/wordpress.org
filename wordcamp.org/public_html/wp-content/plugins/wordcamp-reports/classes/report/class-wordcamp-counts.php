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
use WordCamp\Utilities\Genderize_Client;

/**
 * Class WordCamp_Counts
 *
 * Based on the bin script php/multiple-use/wc-post-types/count-speakers-sponsors-sessions.php?rev=2795
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Counts extends Base {
	/**
	 * The lowest acceptable probability when determining gender.
	 *
	 * @var float
	 */
	const GENDER_PROBABILITY_THRESHOLD = 0.9;

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
			<li>If gender breakdowns are included, also retrieve lists of first names for attendees, organizers, and speakers for each WordCamp. Submit these to the Genderize.io API to estimate the gender of each name and get approximate gender breakdowns of each post type.</li>
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
	 * Whether to include a gender breakdown in relevant counts.
	 *
	 * @var bool
	 */
	public $include_gender = false;

	/**
	 * Genderize.io client.
	 *
	 * @var Genderize_Client Utility to estimate genders from names.
	 */
	protected $genderize = null;

	/**
	 * An array of data from the WordCamp Details report.
	 *
	 * @var array|null
	 */
	protected $wordcamps = null;

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = [
		'wordcamp_id' => 0,
		'site_id'     => 0,
		'post_id'     => 0,
		'type'        => '',
		'gender'      => '',
	];

	/**
	 * Data fields that should only be visible in a private context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $private_data_fields = [
		'identifier'  => '',
	];

	/**
	 * WordCamp_Counts constructor.
	 *
	 * @param string $start_date     The start of the date range for the report.
	 * @param string $end_date       The end of the date range for the report.
	 * @param array  $statuses       Optional. Status IDs to filter for in the report.
	 * @param bool   $include_gender Optional. True to include gender breakdowns of relevant counts.
	 * @param array  $options        {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and Date_Range::__construct for additional parameters.
	 * }
	 */
	public function __construct( $start_date, $end_date, array $statuses = [], $include_gender = false, array $options = [] ) {
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

		$this->include_gender = wp_validate_boolean( $include_gender );

		$this->genderize = new Genderize_Client();
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

		if ( $this->include_gender ) {
			$cache_key_segments[] = '+gender';
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

		// @todo Maybe find a way to run this without having to hack the ini.
		ini_set( 'memory_limit', '900M' );
		ini_set( 'max_execution_time', 300 );

		$wordcamps = $this->get_wordcamps();

		$wordcamp_ids = array_keys( $wordcamps );

		$data = [];

		foreach ( $wordcamp_ids as $wordcamp_id ) {
			try {
				$valid = validate_wordcamp_id( $wordcamp_id );

				$data = array_merge( $data, $this->get_data_for_site( $valid->site_id, $valid->post_id ) );
			} catch ( Exception $e ) {
				$this->error->add(
					self::$slug . '-wordcamp-id-error',
					$e->getMessage()
				);
			}

			if ( ! empty( $this->error->get_error_messages() ) ) {
				break;
			}
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
		$wordcamps = $this->prepare_data_for_display( $this->get_wordcamps() );

		$compiled_data = [
			'wordcamps' => [],
			'totals'    => [
				'attendee'  => 0,
				'organizer' => 0,
				'session'   => 0,
				'speaker'   => 0,
				'sponsor'   => 0,
			],
			'uniques'   => [
				'attendee'  => [],
				'organizer' => [],
				'speaker'   => [],
				'sponsor'   => [],
			],
		];

		$wordcamp_template = [
			'totals' => [
				'attendee'  => 0,
				'organizer' => 0,
				'session'   => 0,
				'speaker'   => 0,
				'sponsor'   => 0,
			],
		];

		if ( $this->include_gender ) {
			$gender_template = [
				'female'  => 0,
				'male'    => 0,
				'unknown' => 0,
			];

			$wordcamp_template['genders'] = $compiled_data['genders'] = [
				'attendee'  => $gender_template,
				'organizer' => $gender_template,
				'speaker'   => $gender_template,
			];
		}

		foreach ( $data as $item ) {
			$wordcamp_id = $item['wordcamp_id'];

			if ( ! isset( $compiled_data['wordcamps'][ $wordcamp_id ] ) ) {
				$compiled_data['wordcamps'][ $wordcamp_id ] = array_merge(
					[
						'info' => $wordcamps[ $wordcamp_id ],
					],
					$wordcamp_template
				);
			}

			$type       = $item['type'];
			$identifier = $item['identifier'];

			$compiled_data['wordcamps'][ $wordcamp_id ]['totals'][ $type ] ++;
			$compiled_data['totals'][ $type ] ++;
			if ( isset( $compiled_data['uniques'][ $type ] ) ) {
				$compiled_data['uniques'][ $type ][] = $identifier;
			}

			if ( $this->include_gender && isset( $wordcamp_template['genders'][ $type ] ) ) {
				$gender = $item['gender'];

				$compiled_data['wordcamps'][ $wordcamp_id ]['genders'][ $type ][ $gender ] ++;
				$compiled_data['genders'][ $type ][ $gender ] ++;
			}
		}

		$compiled_data['uniques'] = array_map( function( $group ) {
			$group = array_unique( $group );

			return count( $group );
		}, $compiled_data['uniques'] );

		return $compiled_data;
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
					case 'Status' :
						$row[ $key ] = $all_statuses[ $value ];
						break;
					case 'Start Date (YYYY-mm-dd)' :
						$row[ $key ] = ( $value ) ? date( 'Y-m-d', $value ) : '';
						break;
				}
			}
		} );

		return $data;
	}

	/**
	 * Get data from a WordCamp Details report and filter it.
	 *
	 * @return array|null
	 */
	protected function get_wordcamps() {
		if ( is_array( $this->wordcamps ) ) {
			return $this->wordcamps;
		}

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

		$this->wordcamps = array_reduce( $wordcamps, function( $carry, $item ) {
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

		return $this->wordcamps;
	}

	/**
	 * Retrieve all of the data for one site.
	 *
	 * @param int $site_id
	 * @param int $wordcamp_id
	 *
	 * @return array
	 */
	protected function get_data_for_site( $site_id, $wordcamp_id ) {
		$site_data = [];

		switch_to_blog( $site_id );

		$attendees = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'tix_attendee',
			'post_status'    => 'publish',
		] );

		foreach ( $attendees->posts as $attendee ) {
			$data = [
				'wordcamp_id' => $wordcamp_id,
				'site_id'     => $site_id,
				'post_id'     => $attendee->ID,
				'type'        => 'attendee',
				'identifier'  => $attendee->tix_email,
			];

			if ( $this->include_gender ) {
				$data['first_name'] = explode( ' ', $attendee->tix_first_name )[0];
			}

			$site_data[] = $data;

			clean_post_cache( $attendee );
		}

		$organizers = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_organizer',
			'post_status'    => 'publish',
		] );

		foreach ( $organizers->posts as $organizer ) {
			$data = [
				'wordcamp_id' => $wordcamp_id,
				'site_id'     => $site_id,
				'post_id'     => $organizer->ID,
				'type'        => 'organizer',
				'identifier'  => $organizer->_wcpt_user_id,
			];

			if ( $this->include_gender ) {
				$data['first_name'] = explode( ' ', $organizer->post_title )[0];
			}

			$site_data[] = $data;

			clean_post_cache( $organizer );
		}

		$sessions = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_session',
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'   => '_wcpt_session_type',
					'value' => 'session', // Other session types are usually things like "Lunch".
				]
			],
		] );

		foreach ( $sessions->posts as $session ) {
			$data = [
				'wordcamp_id' => $wordcamp_id,
				'site_id'     => $site_id,
				'post_id'     => $session->ID,
				'type'        => 'session',
				'identifier'  => '',
			];

			if ( $this->include_gender ) {
				$data['first_name'] = '';
			}

			$site_data[] = $data;

			clean_post_cache( $session );
		}

		$speakers = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_speaker',
			'post_status'    => 'publish',
		] );

		foreach ( $speakers->posts as $speaker ) {
			$data = [
				'wordcamp_id' => $wordcamp_id,
				'site_id'     => $site_id,
				'post_id'     => $speaker->ID,
				'type'        => 'speaker',
				'identifier'  => $speaker->_wcb_speaker_email,
			];

			if ( $this->include_gender ) {
				$data['first_name'] = explode( ' ', $speaker->post_title )[0];
			}

			$site_data[] = $data;

			clean_post_cache( $speaker );
		}

		$sponsors = new WP_Query( [
			'posts_per_page' => -1,
			'post_type'      => 'wcb_sponsor',
			'post_status'    => 'publish',
		] );

		foreach ( $sponsors->posts as $sponsor ) {
			$data = [
				'wordcamp_id' => $wordcamp_id,
				'site_id'     => $site_id,
				'post_id'     => $sponsor->ID,
				'type'        => 'sponsor',
				'identifier'  => $this->get_sponsor_identifier( $sponsor->_wcpt_sponsor_website ),
			];

			if ( $this->include_gender ) {
				$data['first_name'] = '';
			}

			$site_data[] = $data;

			clean_post_cache( $sponsor );
		}

		restore_current_blog();

		// Convert blanks to unique values.
		array_walk( $site_data, function( &$value ) {
			if ( 'session' === $value['type'] ) {
				return;
			}

			if ( empty( $value['identifier'] ) ) {
				$value['identifier'] = "{$value['site_id']}_{$value['post_id']}";
			}
		} );

		if ( $this->include_gender ) {
			$names = array_filter( wp_list_pluck( $site_data, 'first_name' ) );

			// The get_locale() function doesn't work inside switch_to_blog because it returns early.
			$wp_locale = get_site_option( 'WPLANG', 'en_US' );

			$gender_data = $this->genderize->get_gender_data( $names, $wp_locale );

			if ( ! empty( $this->genderize->error->get_error_messages() ) ) {
				$this->merge_errors( $this->error, $this->genderize->error );

				return [];
			}

			array_walk( $site_data, function( &$value ) use ( $gender_data ) {
				$name = strtolower( $value['first_name'] );
				$data = $gender_data[ $name ];

				if ( ! $data['gender'] || $data['probability'] < self::GENDER_PROBABILITY_THRESHOLD ) {
					$value['gender'] = 'unknown';
				} else {
					$value['gender'] = $data['gender'];
				}

				unset( $value['first_name'] );
			} );
		}

		return $site_data;
	}

	/**
	 * Reduce a sponsor URL to a simple domain name with no TLD.
	 *
	 * @param string $sponsor_url
	 *
	 * @return string
	 */
	protected function get_sponsor_identifier( $sponsor_url ) {
		$hostname = wp_parse_url( $sponsor_url, PHP_URL_HOST );

		if ( ! $hostname ) {
			return '';
		}

		$trimmed = substr( $hostname, 0, strripos( $hostname, '.' ) ); // Remove the TLD.
		$trimmed = preg_replace( '/\.com?$/', '', $trimmed ); // Remove possible secondary .com or .co.
		$trimmed = preg_replace( '/^www\./', '', $trimmed ); // Remove possible www.

		return $trimmed;
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
		$statuses   = $this->statuses;

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
		$start_date     = filter_input( INPUT_POST, 'start-date' );
		$end_date       = filter_input( INPUT_POST, 'end-date' );
		$statuses       = filter_input( INPUT_POST, 'statuses', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) ?: [];
		$include_gender = filter_input( INPUT_POST, 'include-gender', FILTER_VALIDATE_BOOLEAN );
		$refresh        = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action         = filter_input( INPUT_POST, 'action' );
		$nonce          = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$all_statuses = WordCamp_Loader::get_post_statuses();

		$report = null;

		if ( 'Show results' === $action
		     && wp_verify_nonce( $nonce, 'run-report' )
		     && current_user_can( 'manage_network' )
		) {
			$options = array(
				'public'       => false,
				'max_interval' => new DateInterval( 'P1Y1M' ),
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $statuses, $include_gender, $options );
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
