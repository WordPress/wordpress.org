<?php
/**
 * Implements base class for event reports which allows to export CSV.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();
use Exception;
use WordCamp\Reports\Utility\Date_Range;
use function WordCamp\Reports\{get_assets_url, get_assets_dir_path, get_views_dir_path};
use function WordCamp\Reports\Validation\{validate_date_range};
use WordCamp\Utilities\Export_CSV;

/**
 * Class Base_Details
 * Base class of details report type
 *
 * @package WordCamp\Reports\Report
 */
abstract class Base_Details extends Base {

	/**
	 * A list of Event post IDs.
	 *
	 * @var array
	 */
	public $event_ids = null;

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
	 * A date range that WordCamp events must fall within.
	 *
	 * @var null|Date_Range
	 */
	public $range = null;

	/**
	 * Base_Details constructor.
	 *
	 * @param Date_Range $date_range       Optional. A date range that WordCamp events must fall within.
	 * @param array      $event_ids     Optional. A list of Event post IDs to include in the results.
	 * @param array      $options          {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and the functions in WordCamp\Reports\Validation for additional parameters.
	 *
	 *     @type array $status_subset A list of valid status IDs.
	 *     @type array $fields        Not implemented yet.
	 * }
	 */
	public function __construct( $date_range = null, $event_ids = null, $options = [] ) {

		$options = wp_parse_args( $options, [
			'fields' => [],
		] );

		parent::__construct( $options );

		if ( ! is_null( $event_ids ) ) {
			$this->event_ids = $event_ids;
		}

		if ( $date_range instanceof Date_Range ) {
			$this->range = $date_range;
		}

		$this->public_data_fields = array_fill_keys( $this->get_public_data_fields(), '' );

		$this->private_data_fields = array_fill_keys( $this->get_private_data_fields(), '' );

		try {
			$this->options['fields'] = $this->validate_fields_input( $this->options['fields'] );
		} catch ( Exception $e ) {
			$this->error->add(
				$this->slug . '-fields-error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Return fields that can be displayed in public context.
	 *
	 * @return array
	 */
	abstract public function get_public_data_fields();

	/**
	 * Return fields that can be viewed in private context.
	 *
	 * @return array
	 */
	abstract public function get_private_data_fields();

	/**
	 * Check the array of fields to include in the spreadsheet against the safelist of data fields.
	 *
	 * @param array $fields
	 *
	 * @return array The validated fields.
	 * @throws Exception
	 */
	protected function validate_fields_input( array $fields ) {
		$valid_fields = $this->get_data_fields_safelist();
		$fields       = array_unique( $fields );

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $valid_fields ) ) {
				throw new Exception( sprintf(
					'Invalid field: %s',
					esc_html( $field )
				) );
			}
		}

		return $fields;
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

		$data = [];

		$event_posts = $this->get_event_posts();

		foreach ( $event_posts as $post ) {
			$data[] = $this->fill_data_row( $post );
		}

		$this->filter_data_fields( $data );

		// Reorder of the fields in each row.
		$field_order = array_fill_keys( $this->get_field_order(), '' );
		array_walk( $data, function( &$row ) use ( $field_order ) {
			$row = array_intersect_key( array_replace( $field_order, $row ), $row );
		} );

		return $data;
	}

	/**
	 * Get the values of all the relevant post meta keys for a Event post.
	 *
	 * @param \WP_Post $event
	 *
	 * @return array
	 */
	public function fill_data_row( $event ) {
		$meta_keys   = $this->get_meta_keys();

		$row = [
			'ID'      => $event->ID,
			'Name'    => $event->post_title,
			'Created' => get_the_date( 'Y-m-d', $event->ID ),
			'Status'  => $event->post_status,
		];

		foreach ( $meta_keys as $key ) {
			$row[ $key ] = get_post_meta( $event->ID, $key, true ) ? : '';
		}

		return $row;
	}

	/**
	 * Return array of WP_Post, for events that should be present in report.
	 *
	 * @return array
	 */
	abstract public function get_event_posts();

	/**
	 * Get the full list of fields in the order they should appear in.
	 *
	 * @return array
	 */
	abstract static public function get_field_order();

	/**
	 * Get a list of all the relevant meta keys for Event posts.
	 *
	 * @return array
	 */
	abstract public function get_meta_keys();

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
	 * Render HTML form inputs for the fields that are available for inclusion in the spreadsheet.
	 *
	 * @param string $context        'public' or 'private'. Default 'public'.
	 * @param array  $field_defaults Optional. An associative array where the keys are field keys and the values
	 *                               are extra attributes for those field inputs. Examples: checked or required.
	 */
	abstract static public function render_available_fields( $context = 'public', array $field_defaults = [] );

	/**
	 * @param $shadow_report WordCamp_Details|Meetup_Details
	 * @param string $context
	 * @param array $field_defaults
	 */
	public static function render_available_fields_in_report( $shadow_report, $context = 'public', array $field_defaults = [] ) {

		$field_order      = array_fill_keys( $shadow_report->get_field_order(), '' );
		$field_defaults   = array_replace( $field_order, $field_defaults );

		$available_fields = array_intersect_key( $field_defaults, $shadow_report->get_data_fields_safelist() );
		?>
		<fieldset class="fields-container">
			<legend class="fields-label">Available Fields</legend>

			<?php foreach ( $available_fields as $field_name => $extra_props ) : ?>
				<div class="field-checkbox">
					<input
						type="checkbox"
						id="fields-<?php echo esc_attr( $field_name ); ?>"
						name="fields[]"
						value="<?php echo esc_attr( $field_name ); ?>"
						<?php if ( $extra_props && is_string( $extra_props ) ) echo esc_html( $extra_props ); ?>
					/>
					<label for="fields-<?php echo esc_attr( $field_name ); ?>">
						<?php echo esc_attr( $field_name ); ?>
					</label>
				</div>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Create an object of child class with relevant requirements passed to constructor
	 *
	 * @param string $context Can be 'public' or 'private'
	 *
	 * @return Base_Details
	 */
	abstract static public function create_shadow_report_obj( $context );

	/**
	 * Register all assets used by this report.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_script(
			'wordcamp-details',
			get_assets_url() . 'js/' . 'wordcamp-details' . '.js',
			array(),
			filemtime( get_assets_dir_path() . 'js/' . 'wordcamp-details' . '.js' ),
			true
		);

		wp_register_style(
			'wordcamp-details',
			get_assets_url() . 'css/' . 'wordcamp-details' . '.css',
			array(),
			filemtime( get_assets_dir_path() . 'css/' . 'wordcamp-details' . '.css' ),
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

		wp_enqueue_script( 'wordcamp-details' );
		wp_enqueue_style( 'wordcamp-details' );
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	abstract public static function render_admin_page();

	/**
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	abstract public static function export_to_file();

	/**
	 * Format the data for human-readable display.
	 *
	 * @param array $data The data to prepare.
	 *
	 * @return array
	 */
	abstract public function prepare_data_for_display( array $data );

	/**
	 * Export the report data to a file.
	 *
	 * @param $report Meetup_Details|WordCamp_Details
	 *
	 * @return void
	 */
	public static function export_to_file_common( $report ) {
		$filename = [ $report::$name ];
		if ( $report->range instanceof Date_Range ) {
			$filename[] = $report->range->start->format( 'Y-m-d' );
			$filename[] = $report->range->end->format( 'Y-m-d' );
		}
		if ( isset( $report->include_counts ) && $report->include_counts ) {
			$filename[] = 'include-counts';
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
	}
}