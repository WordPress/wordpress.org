<?php
/**
 * Implements class for exporting meetup event details in CSV.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();
use function WordCamp\Reports\{get_views_dir_path};
use Meetup_Admin;

/**
 * Class Meetup_Details
 *
 * A report class for exporting a spreadsheet of Meetups.
 *
 * @package WordCamp\Reports\Report
 */
class Meetup_Details extends Base_Details {

	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Meetup Details';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'meetup-details';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Create a spreadsheet of details about Meetups that match optional criteria.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Fetch all meetup posts.</li>
			<li>Extract the data for each post that match the fields requested.</li>
			<li>Walk through all of the extracted data and format it for display.</li>
		</ol>
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'meetup';

	/**
	 * Meetup_Details constructor.
	 *
	 * @param Date_Range $date_range       Optional. A date range that Meetup events must fall within.
	 * @param array      $meetup_ids     Optional. A list of Meetup post IDs to include in the results.
	 * @param array      $options          {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and the functions in WordCamp\Reports\Validation for additional parameters.
	 *
	 *     @type array $status_subset A list of valid status IDs.
	 *     @type array $fields        Not implemented yet.
	 * }
	 */
	public function __construct( Date_Range $date_range = null, $meetup_ids = null, array $options = [] ) {
		// Report-specific options.
		$options = wp_parse_args( $options, [
			'fields' => [],
		] );

		parent::__construct( $date_range, $meetup_ids, $options );
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
			Meetup_Admin::get_public_meta_keys()
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
				'Primary organizer WordPress.org username',
				'Co-Organizers usernames (seperated by comma)',
				'Number of past meetups',
				'Last meetup RSVP count',
			],
			array_keys( Meetup_Admin::meta_keys( 'organizer' ) ),
			array_keys( $this->get_public_data_fields() )
		);
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$field_defaults = [
			'ID' => 'checked',
			'Name' => 'checked disabled',
			'Created' => 'checked',
			'Status' => 'checked',
		];

		include get_views_dir_path() . 'report/meetup-details.php';
	}

	/**
	 * Get Meetup posts that fit the report criteria.
	 *
	 * @return array An array of WP_Post objects.
	 */
	public function get_event_posts() {
		$post_args = array(
			'post_type'           => WCPT_MEETUP_SLUG,
			'post_status'         => 'any',
			'posts_per_page'      => -1,
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

		if ( ! is_null( $this->event_ids ) ) {
			if ( empty( $this->event_ids ) ) {
				return [];
			}
			$post_args['post__in'] = $this->event_ids;
		}

		if ( $this->options['public'] ) {
			$post_args['post_status'] = \Meetup_Loader::get_public_post_statuses();
		}

		return get_posts( $post_args );
	}

	/**
	 * Get a list of all the relevant meta keys for Meetup posts.
	 *
	 * @return array
	 */
	public function get_meta_keys() {
	  return array_keys( \Meetup_Admin::meta_keys( 'all' ) );
	}

	/**
	 * Get the full list of fields in the order they should appear in.
	 *
	 * @return array
	 */
	static public function get_field_order() {
		return array_merge(
			array('ID', 'name' ),
			array_keys( Meetup_Admin::meta_keys( 'information') ),
			array(
				'Status',
			),
			array_keys( Meetup_Admin::meta_keys( 'organizer' ) )
		);
	}

	/**
	 * Create an object of this class with relevant requirements passed to constructor
	 *
	 * @param string $context Can be 'public' or 'private'
	 *
	 * @return Base_Details
	 */
	static public function create_shadow_report_obj( $context ) {
		return new self( null, [], ['public' => 'public' === $context ] );
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
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	public static function export_to_file( ) {

		$fields           = filter_input( INPUT_POST, 'fields', FILTER_SANITIZE_STRING, [ 'flags' => FILTER_REQUIRE_ARRAY ] );
		$action           = filter_input( INPUT_POST, 'action' );
		$nonce            = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'run-report' ) && current_user_can( 'manage_network' ) ) {
			return;
		}

		$error = null;
		$range = null;

		// The "Name" field should always be included, but does not get submitted because the input is disabled,
		// so add it in here.
		$fields[] = 'Name';

		$options = array(
			'fields' => $fields,
			'public' => false,
		);
		$report = new self( $range, null, $options );

		self::export_to_file_common( $report );
	}

	/**
	 * Format the data for human-readable display.
	 *
	 * @param array $data The data to prepare.
	 *
	 * @return array
	 */
	public function prepare_data_for_display( array $data ) {
		$all_statuses = Meetup_Admin::get_post_statuses();
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
					case 'Meetup Co-organizer names':
						if ( is_array( $value ) ) {
							$org_names = wp_list_pluck( $value, 'name' );
							$row[ $key ] = implode( ', ', $org_names );
						}
						break;
				}
			}
		} );

		return $data;
	}

}