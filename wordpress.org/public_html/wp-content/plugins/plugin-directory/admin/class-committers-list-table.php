<?php
namespace WordPressdotorg\Plugin_Directory\Admin;
use WordPressdotorg\Plugin_Directory\Tools;

_get_list_table( 'WP_List_Table' );

/**
 * Comments list table for comments meta box.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Committers_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( array(
			'singular' => 'committer',
			'plural'   => 'committers',
			'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
		) );
	}

	/**
	 * Check the current user's permissions.
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_committers' );
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
		$plugin_slug         = get_post()->post_name;
		$existing_committers = Tools::get_plugin_committers( $plugin_slug );
		$this->items         = array_map( function ( $user ) {
			return new \WP_User( $user );
		}, $existing_committers );
	}

	/**
	 * Output 'no users' message.
	 *
	 * @access public
	 */
	public function no_items() {
		_e( 'No committers found.', 'wporg-plugins' );
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
			<tbody id="the-committer-list" data-wp-lists="list:committer">
				<?php $this->display_rows_or_placeholder(); ?>
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
		?>
		<tr id="add-committer" class="add-committer wp-hidden-children">
			<td colspan="2">
				<button type="button" id="add-committer-toggle" class="button-link"><?php _e( '+ Add New Committer', 'wporg-plugins' ); ?></button>
				<p class="wp-hidden-child">
					<?php wp_nonce_field( 'add-committer', '_ajax_nonce', false ); ?>
					<span id="committer-error" class="notice notice-alt notice-error" style="display:none;"></span>
					<input type="text" name="add_committer" class="form-required" value="" aria-required="true">
					<input type="button" id="add-committer-submit" class="button" data-wp-lists="add:the-committer-list:add-committer::post_id=<?php echo get_post()->ID; ?>" value="<?php _e( 'Add Committer', 'wporg-plugins' ); ?>">
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
		$user_object->filter = 'display';
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		// Set up the hover actions for this committer.
		$actions = array();

		// Check if the committer for this row is removable.
		if ( current_user_can( 'list_users' ) ) {
			$post_id = get_post()->ID;
			$actions['delete'] = "<a class='submitremove' data-wp-lists='delete:the-committer-list:committer-{$user_object->ID}:faafaa:post_id={$post_id}' href='" . wp_nonce_url( 'users.php?action=remove&amp;committer=' . $user_object->ID, "remove-committer-{$user_object->ID}" ) . "'>" . __( 'Remove', 'wporg-plugins' ) . "</a>";
		}

		/**
		 * Filter the action links displayed under each committer in the Committers list table.
		 *
		 * @param array    $actions     An array of action links to be displayed.
		 * @param \WP_User $user_object WP_User object for the currently-listed committer.
		 */
		$actions = apply_filters( 'committer_row_actions', $actions, $user_object );

		$row = "<tr id='committer-$user_object->ID'>";

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
					$row .= "<strong>$user_object->user_login</strong><br />";
					break;

				default:
					/**
					 * Filter the display output of custom columns in the Users list table.
					 *
					 * @param string $output      Custom column output. Default empty.
					 * @param string $column_name Column name.
					 * @param int    $user_id     ID of the currently-listed user.
					 */
					$row .= apply_filters( 'manage_committers_custom_column', '', $column_name, $user_object->ID );
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
