<?php
/**
 * List Table for the tasks in the Planning Checklist.
 *
 * @package WordCamp\Mentors
 */

namespace WordCamp\Mentors\Tasks;
defined( 'WPINC' ) || die();

use WordCamp\Mentors;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class List_Table.
 *
 * This class is used both to render the markup for the list table on the
 * Planning Checklist page, and to generate the JS template for rendering
 * each individual task row.
 *
 * When instantiating the class for the purpose of generating the JS template,
 * the class should be passed an args array with a `js` key set to `true`.
 *
 * @package WordCamp\Mentors\Tasks
 */
class List_Table extends \WP_List_Table {
	/**
	 * Switch the context between page load and JS template
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	protected $js = false;

	/**
	 * List_Table constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Class args.
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'js' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$this->js = $args['js'];

		parent::__construct( $args );
	}

	/**
	 * Add controls above and below the list table
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Location of the extra tablenav.
	 */
	public function extra_tablenav( $which = 'top' ) {
		if ( 'top' === $which ) : ?>
		<div class="<?php echo ( is_rtl() ) ? 'alignright' : 'alignleft'; ?> actions">
			<form id="tasks-filter">
				<?php $this->task_category_dropdown(); ?>
				<?php $this->status_dropdown(); ?>
				<?php submit_button( __( 'Filter', 'wordcamporg' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php elseif ( 'bottom' === $which ) : ?>
		<div class="<?php echo ( is_rtl() ) ? 'alignleft' : 'alignright'; ?> actions">
			<?php if ( current_user_can( Mentors\MENTOR_CAP ) ) : ?>
				<form id="tasks-reset" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Mentors\PREFIX ); ?>-tasks-reset" />
					<?php wp_nonce_field( Mentors\PREFIX . '-tasks-reset', Mentors\PREFIX . '-tasks-reset-nonce' ); ?>
					<?php submit_button( __( 'Reset Task Data', 'wordcamporg' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php endif;
	}

	/**
	 * Dropdown for task categories
	 *
	 * @since 1.0.0
	 */
	protected function task_category_dropdown() {
		$task_categories = get_terms( array(
			'taxonomy'   => Mentors\PREFIX . '_task_category',
			'hide_empty' => false,
		) );
		$task_category_data = get_task_category_data();

		$pref = get_user_setting( Mentors\PREFIX . '-' . Mentors\PREFIX . '_task_category', 'any' );
		?>
		<label for="filter-by-task-category" class="screen-reader-text"><?php esc_html_e( 'Filter by task category', 'wordcamporg' ); ?></label>
		<select id="filter-by-task-category" data-attribute="<?php echo esc_attr( Mentors\PREFIX ); ?>_task_category">
			<option value="any" <?php selected( 'any', $pref ); ?>>
				<?php esc_html_e( 'All task categories', 'wordcamporg' ); ?>
			</option>
			<?php foreach ( $task_categories as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $cat->term_id, $pref ); ?>>
					<?php echo esc_html( $task_category_data[ $cat->slug ] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Dropdown for task statuses
	 *
	 * @since 1.0.0
	 */
	protected function status_dropdown() {
		$task_statuses = get_task_statuses();
		$pref = get_user_setting( Mentors\PREFIX . '-status', Mentors\PREFIX . '_task_pending' );
		?>
		<label for="filter-by-task-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'wordcamporg' ); ?></label>
		<select id="filter-by-task-status" data-attribute="status">
			<option value="any" <?php selected( 'any', $pref ); ?>>
				<?php esc_html_e( 'All statuses', 'wordcamporg' ); ?>
			</option>
			<?php foreach ( $task_statuses as $status ) : ?>
				<option value="<?php echo esc_attr( $status->name ); ?>" <?php selected( $status->name, $pref ); ?>>
					<?php echo esc_html( $status->label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Get a list of CSS classes for the WP_List_Table table tag.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', $this->_args['plural'] );
	}

	/**
	 * Prepare the table items
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		if ( $this->js ) {
			// For the JS template, only one row is needed.
			$this->items = array(
				(object) array(
					'ID' => 'data.id',
				),
			);
		}
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 1.0.0
	 */
	public function no_items() {}

	/**
	 * Specify the column names and order
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array();

		$columns['task']          = esc_html__( 'Task', 'wordcamporg' );
		$columns['task_category'] = get_taxonomy( Mentors\PREFIX . '_task_category' )->labels->singular_name;
		$columns['status']        = esc_html__( 'Status', 'wordcamporg' );
		$columns['modified']      = esc_html__( 'Modified', 'wordcamporg' );

		return $columns;
	}

	/**
	 * Render the Task column.
	 *
	 * @since 1.0.0
	 */
	public function column_task() {
		if ( $this->js ) : ?>
			{{ data.title.rendered }}
		<?php endif;
	}

	/**
	 * Render the Task Category column
	 *
	 * @since 1.0.0
	 */
	public function column_task_category() {
		if ( $this->js ) : ?>
			<ul>
				<# if ( data.task_category.length ) { #>
					<# _.each( data.task_category, function( category ) { #>
						<li class="category-{{ category.get( 'slug' ) }}">{{ category.get( 'name' ) }}</li>
					<# }); #>
				<# } else { #>
					<li class="category-none"><?php esc_html_e( 'No category' , 'wordcamporg' ) ?></li>
				<# } #>
			</ul>
		<?php endif;
	}

	/**
	 * Render the Status column
	 *
	 * @since 1.0.0
	 */
	public function column_status() {
		if ( $this->js ) : ?>
			<select>
				<# if ( 'object' !== typeof data.stati[ data.status ] ) { #>
					<option value="{{ data.status }}" selected disabled>
						{{ data.status }}
					</option>
				<# } #>
				<# _.each( data.stati, function( status, slug ) {
					var selected = ( slug === data.status ) ? 'selected' : '';
					#>
					<option value="{{ slug }}" {{ selected }}>
						{{ status.label }}
					</option>
				<# }); #>
			</select>
		<?php endif;
	}

	/**
	 * Render the Modified column
	 *
	 * @since 1.0.0
	 */
	public function column_modified() {
		if ( $this->js ) : ?>
			<# if ( data.modified.raw !== data.date ) { #>
				{{ data.modified.relative }}
				<# if ( data.lastModifier ) { #>
					<br />
					<?php
					printf(
						/* translators: Attribution to a user, e.g. by wordcampadmin */
						wp_kses( __( 'by <strong>%s</strong>', 'wordcamporg' ), array( 'strong' => true ) ),
						'{{ data.lastModifier }}'
					);
					?>
				<# } #>
			<# } #>
		<?php endif;
	}
}
