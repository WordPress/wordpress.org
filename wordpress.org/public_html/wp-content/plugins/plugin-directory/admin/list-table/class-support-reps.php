<?php
namespace WordPressdotorg\Plugin_Directory\Admin\List_Table;

use WordPressdotorg\Plugin_Directory\Tools;

_get_list_table( 'WP_List_Table' );

/**
 * Comments list table for comments meta box.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\List_Table
 */
class Support_Reps extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( array(
			'singular' => 'support_rep',
			'plural'   => 'support_reps',
			'screen'   => isset( $args['screen'] ) ? $args['screen'] : 'plugin',
		) );
	}

	/**
	 * Check the current user's permissions.
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'plugin_remove_support_rep' );
	}

	/**
	 * Prepare the users list for display.
	 *
	 * @access public
	 *
	 * @global string $role
	 * @global string $usersearch
	 */
	public function prepare_items() {
		$plugin_slug = get_post()->post_name;
		if ( ! $plugin_slug ) {
			return;
		}

		$this->items = array_map( function ( $user ) {
			return get_user_by( 'slug', $user );
		}, Tools::get_plugin_support_reps( $plugin_slug ) );
	}

	/**
	 * Output 'no users' message.
	 *
	 * @access public
	 */
	public function no_items() {
		_e( 'No support reps found.', 'wporg-plugins' );
	}

	/**
	 *
	 * @return array
	 */
	protected function get_table_classes() {
		$classes   = parent::get_table_classes();
		$classes[] = 'wp-list-table';

		unset( $classes[ array_search( 'striped', $classes ) ] );

		return $classes;
	}

	/**
	 *
	 * @return array
	 */
	protected function get_column_info() {
		return array(
			array(
				'avatar'   => __( 'Avatar', 'wporg-plugins' ),
				'username' => __( 'Username', 'wporg-plugins' ),
			),
			array(),
			'username',
		);
	}

	/**
	 *
	 */
	public function display() {
		?>
		<table class="<?php echo implode( ' ', $this->get_table_classes() ); ?>">
			<colgroup>
				<col width="40px" />
				<col />
			</colgroup>
			<tbody id="the-support-rep-list" data-wp-lists="list:support-rep">
				<?php $this->display_rows_or_placeholder(); ?>
				<?php $this->display_add_new_row(); ?>
			</tbody>
		</table>
	<?php
	}

	/**
	 * Generate the list table rows.
	 *
	 * @access public
	 */
	public function display_rows() {
		foreach ( $this->items as $user_object ) {
			echo "\n\t" . $this->single_row( $user_object );
		}
	}

	/**
	 * Display the "Add new" row.
	 */
	public function display_add_new_row() {
		?>
		<tr id="add-support-rep" class="add-support-rep wp-hidden-children">
			<td colspan="2">
				<button type="button" id="add-support-rep-toggle" class="button-link"><?php _e( '+ Add New Support Rep', 'wporg-plugins' ); ?></button>
				<p class="wp-hidden-child">
					<?php wp_nonce_field( 'add-support-rep', '_ajax_nonce', false ); ?>
					<span id="support-rep-error" class="notice notice-alt notice-error" style="display:none;"></span>
					<label>
						<input type="text" name="add_support_rep" class="form-required" value="" aria-required="true" placeholder="<?php esc_attr_e( 'WordPress.org username', 'wporg-plugins' ); ?>">
						<span class="screen-reader-text"><?php _e( 'Add a new support rep', 'wporg-plugins' ); ?></span>
					</label>
					<input type="button" id="add-support-rep-submit" class="button" data-wp-lists="add:the-support-rep-list:add-support-rep::post_id=<?php echo get_post()->ID; ?>" value="<?php _e( 'Add Support Rep', 'wporg-plugins' ); ?>">
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate HTML for a single row on the users.php admin panel.
	 *
	 * @access public
	 *
	 * @param object $user_object The current user object.
	 * @return string Output for a single row.
	 */
	public function single_row( $user_object ) {
		if ( ! ( $user_object instanceof \WP_User ) ) {
			$user_object = get_userdata( (int) $user_object );
		}
		$user_object->filter                = 'display';
		list( $columns, $hidden, $primary ) = $this->get_column_info();

		// Set up the hover actions for this support rep.
		$actions = array();

		// Check if the support rep for this row is removable.
		$post_id = get_post()->ID;
		if ( current_user_can( 'plugin_remove_support_rep', $post_id ) && $user_object->ID != get_current_user_id() ) {
			$actions['delete'] = "<a class='submitremove' data-wp-lists='delete:the-support-rep-list:support-rep-{$user_object->ID}:faafaa:post_id={$post_id}' href='" . wp_nonce_url( 'users.php?action=remove&amp;support-rep=' . $user_object->ID, "remove-support-rep-{$user_object->ID}" ) . "'>" . __( 'Remove', 'wporg-plugins' ) . '</a>';
		}

		/**
		 * Filter the action links displayed under each support rep in the Support Reps list table.
		 *
		 * @param array    $actions     An array of action links to be displayed.
		 * @param \WP_User $user_object WP_User object for the currently-listed support rep.
		 */
		$actions = apply_filters( 'support_rep_row_actions', $actions, $user_object );

		$row = "<tr id='support-rep-$user_object->ID'>";

		foreach ( $columns as $column_name => $column_display_name ) {
			$data    = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';
			$classes = "$column_name column-$column_name";

			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}
			if ( in_array( $column_name, $hidden ) ) {
				$classes .= ' hidden';
			}

			$row .= "<td class='$classes' $data>";
			switch ( $column_name ) {
				case 'avatar':
					$row .= get_avatar( $user_object->ID, 32 );
					break;

				case 'username':
					$row .= sprintf(
						'<strong><a href="%s">%s</a></strong><br />&lt;%s&gt;',
						esc_url( '//profiles.wordpress.org/' . $user_object->user_nicename ),
						$user_object->user_login,
						$user_object->user_email
					);
					break;

				default:
					/**
					 * Filter the display output of custom columns in the Users list table.
					 *
					 * @param string $output      Custom column output. Default empty.
					 * @param string $column_name Column name.
					 * @param int    $user_id     ID of the currently-listed user.
					 */
					$row .= apply_filters( 'manage_support_reps_custom_column', '', $column_name, $user_object->ID );
			}
			if ( $primary === $column_name ) {
				$row .= $this->row_actions( $actions );
			}
			$row .= '</td>';
		}
		$row .= '</tr>';

		return $row;
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @access protected
	 *
	 * @return string Name of the default primary column, in this case, 'username'.
	 */
	protected function get_default_primary_column_name() {
		return 'username';
	}
}
