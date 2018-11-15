<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use DateTime;
use WP_Post, WP_Query;
use function WordCamp\Reports\{get_views_dir_path};
use WordCamp\Reports\Utility\Date_Range;
use function WordCamp\Reports\Validation\{validate_date_range, validate_wordcamp_id};
use WordCamp_Admin, WordCamp_Loader;

/**
 * Class WordCamp_Details
 *
 * A report class for exporting a spreadsheet of WordCamps.
 *
 * Note that this report does not use caching because it is only used in WP Admin and has a large number of
 * optional parameters.
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Details extends Base_Details {
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
	public static $description = 'Create a spreadsheet of details about WordCamps that match optional criteria.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Retrieve WordCamp posts that fit within the criteria.</li>
			<li>Extract the data for each post that match the fields requested.</li>
			<li>Walk through all of the extracted data and format it for display.</li>
		</ol>
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'wordcamp';

	/**
	 * Whether to include counts of various post types for each WordCamp.
	 *
	 * @var bool
	 */
	public $include_counts = false;

	/**
	 * WordCamp_Details constructor.
	 *
	 * @param Date_Range $date_range       Optional. A date range that WordCamp events must fall within.
	 * @param array      $wordcamp_ids     Optional. A list of WordCamp post IDs to include in the results.
	 * @param bool       $include_counts   Optional. True to include counts of various post types for each WordCamp.
	 *                                     Default false.
	 * @param array      $options          {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and the functions in WordCamp\Reports\Validation for additional parameters.
	 *
	 *     @type array $status_subset A list of valid status IDs.
	 *     @type array $fields        Not implemented yet.
	 * }
	 */
	public function __construct( Date_Range $date_range = null, array $wordcamp_ids = null, $include_counts = false, array $options = [] ) {
		// Report-specific options.

		parent::__construct( $date_range, $wordcamp_ids, $options );
		if ( ! is_null( $wordcamp_ids ) ) {
			$this->event_ids = [];
			$this->validate_wordcamp_ids( $wordcamp_ids );
		}

		$this->include_counts = wp_validate_boolean( $include_counts );
	}

	/**
	 * Return fields that can be displayed in public context.
	 *
	 * @return array
	 */
	public function get_public_data_fields() {
		return array_merge(
			[
				'Name',
			],
			WordCamp_Loader::get_public_meta_keys()
		);
	}

	/**
	 * Return fields that can be viewed in private context.
	 *
	 * @return array
	 */
	public function get_private_data_fields() {
		return array_merge(
			[
				'ID',
				'Created',
				'Status',
				'Tickets',
				'Speakers',
				'Sponsors',
				'Organizers',
			],
			array_diff( $this->get_meta_keys(), array_keys( $this->get_public_data_fields() ) )
		);
	}

	/**
	 * Validate WordCamp ids and filter those without a site.
	 *
	 * @param $wordcamp_ids
	 */
	public function validate_wordcamp_ids( $wordcamp_ids ) {
		if ( ! empty( $wordcamp_ids ) ) {
			foreach ( $wordcamp_ids as $wordcamp_id ) {
				try {
					$this->event_ids[] = validate_wordcamp_id( $wordcamp_id, [ 'require_site' => false ] )->post_id;
				} catch ( Exception $e ) {
					$this->error->add(
						self::$slug . '-wordcamp-id-error',
						$e->getMessage()
					);

					break;
				}
			}
		}
	}

	/**
	 * Get the full list of fields in the order they should appear in.
	 *
	 * @return array
	 */
	public static function get_field_order() {
		if ( ! is_callable( array( 'WordCamp_Admin', 'meta_keys' ) ) ) {
			require_once( WP_PLUGIN_DIR . '/wcpt/wcpt-wordcamp/wordcamp-admin.php' );
		}

		return array_merge(
			[
				'ID',
				'Name',
			],
			array_keys( WordCamp_Admin::meta_keys( 'wordcamp' ) ),
			[
				'Created',
				'Status',
				'Tickets',
				'Speakers',
				'Sponsors',
				'Organizers',
			],
			array_keys( WordCamp_Admin::meta_keys( 'contributor' ) ),
			array_keys( WordCamp_Admin::meta_keys( 'organizer' ) ),
			array_keys( WordCamp_Admin::meta_keys( 'venue' ) ),
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
	public function prepare_data_for_display( array $data ) {
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
	 * Get WordCamp posts that fit the report criteria.
	 *
	 * @return array An array of WP_Post objects.
	 */
	public function get_event_posts() {
		$post_args = array(
			'post_type'           => WCPT_POST_TYPE_ID,
			'post_status'         => 'any',
			'posts_per_page'      => 9999,
			'nopaging'            => true,
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
			'orderby'             => 'id',
			'order'               => 'ASC',
		);

		if ( $this->range instanceof Date_Range ) {
			// This replaces the default meta query.
			$post_args['meta_query'] = [
				[
					'key'      => 'Start Date (YYYY-mm-dd)',
					'value'    => array( $this->range->start->getTimestamp(), $this->range->end->getTimestamp() ),
					'compare'  => 'BETWEEN',
					'type'     => 'NUMERIC',
				],
			];
			$post_args['orderby'] = 'meta_value_num title';
		}

		if ( ! empty( $this->event_ids ) ) {
			$post_args['post__in'] = $this->event_ids;
		}

		if ( $this->options['public'] ) {
			$post_args['post_status'] = WordCamp_Loader::get_public_post_statuses();
		}

		return get_posts( $post_args );
	}

	/**
	 * Get a list of all the relevant meta keys for WordCamp posts.
	 *
	 * @return array
	 */
	public function get_meta_keys() {
		if ( ! is_callable( array( 'WordCamp_Admin', 'meta_keys' ) ) ) {
			require_once( WP_PLUGIN_DIR . '/wcpt/wcpt-wordcamp/wordcamp-admin.php' );
		}

		$meta_keys = array_merge( array_keys( WordCamp_Admin::meta_keys( 'all' ) ), [
			'_venue_coordinates',
			'_venue_city',
			'_venue_state',
			'_venue_country_code',
			'_venue_country_name',
			'_venue_zip',
		] );

		return $meta_keys;
	}

	public function fill_data_row( $event ) {
		$row = parent::fill_data_row( $event );

		if ( $this->include_counts ) {
			$row = array_merge( $row, $this->get_counts( $event ) );
		}

		return $row;
	}

	/**
	 * Create an object of this class with relevant requirements passed to constructor
	 *
	 * @param string $context Can be 'public' or 'private'
	 *
	 * @return Base_Details
	 */
	static public function create_shadow_report_obj( $context ) {
		return new self( null, null, false, ['public' => 'public' === $context ] );
	}

	/**
	 * Render list of fields that can be present in exported CSV.
	 *
	 * @param string $context
	 * @param array $field_defaults
	 */
	static public function render_available_fields( $context = 'public', array $field_defaults = [] ) {
		$shadow_report = self::create_shadow_report_obj( $context );
		self::render_available_fields_in_report( $shadow_report, $context, $field_defaults );
	}

	/**
	 * Count the number of various post types for a WordCamp.
	 *
	 * If the WordCamp doesn't have a site yet, the counts will all be zero.
	 *
	 * @param WP_Post $wordcamp
	 *
	 * @return array
	 */
	public function get_counts( WP_Post $wordcamp ) {
		$counts = [
			'Tickets'    => 0,
			'Speakers'   => 0,
			'Sponsors'   => 0,
			'Organizers' => 0,
		];

		try {
			$id = validate_wordcamp_id( $wordcamp->ID, [ 'require_site' => false ] );
		} catch ( Exception $e ) {
			return $counts;
		}

		if ( ! $id->site_id ) {
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

		switch_to_blog( $id->site_id );

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
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$field_defaults = [
			'ID'                      => 'checked',
			'Name'                    => 'checked disabled',
			'Start Date (YYYY-mm-dd)' => 'checked',
			'End Date (YYYY-mm-dd)'   => 'checked',
			'Location'                => 'checked',
			'URL'                     => 'checked',
		];

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
		$fields           = filter_input( INPUT_POST, 'fields', FILTER_SANITIZE_STRING, [ 'flags' => FILTER_REQUIRE_ARRAY ] );
		$action           = filter_input( INPUT_POST, 'action' );
		$nonce            = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'run-report' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$error = null;
		$range = null;

		if ( $start_date || $end_date ) {
			try {
				$range = validate_date_range( $start_date, $end_date, [
					'allow_future_start' => true,
					'earliest_start'     => new DateTime( '2006-01-01' ), // No WordCamp posts before 2006.,
				] );
			} catch ( Exception $e ) {
				$error = new WP_Error(
					self::$slug . '-date-range-error',
					$e->getMessage()
				);
			}
		}

		$include_counts = false;
		if ( ! empty( array_intersect( $fields, [ 'Tickets', 'Speakers', 'Sponsors', 'Organizers' ] ) ) ) {
			$include_counts = true;
		}

		// The "Name" field should always be included, but does not get submitted because the input is disabled,
		// so add it in here.
		$fields[] = 'Name';

		$options = array(
			'fields' => $fields,
			'public' => false,
		);


		$report = new self( $range, null, $include_counts, $options );

		self::export_to_file_common( $report );
	}

}
