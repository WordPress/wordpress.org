<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Rosetta_Translation_Editors_List_Table extends WP_List_Table {

	/**
	 * Holds the role of a translation editor.
	 *
	 * @var string
	 */
	public $user_role;

	/**
	 * Holds the meta key of the project access list.
	 *
	 * @var string
	 */
	public $project_access_meta_key;

	/**
	 * Whether the current user can promote users.
	 *
	 * @var bool
	 */
	public $user_can_promote;

	/**
	 * Holds the list of all projects.
	 *
	 * @var array
	 */
	public $projects;

	/**
	 * Constructor.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		global $wpdb;

		parent::__construct( array(
			'singular' => 'translation-editor',
			'plural'   => 'translation-editors',
			'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$this->user_role = $args['user_role'];
		$this->project_access_meta_key = $args['project_access_meta_key'];
		$this->projects = $args['projects'];
		$this->user_can_promote = current_user_can( 'promote_users' );
	}

	/**
	 * Prepare the list for display.
	 */
	public function prepare_items() {
		$search = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
		$per_page = 10;
		$paged = $this->get_pagenum();

		$args = array(
			'number' => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
			'role'   => $this->user_role,
			'search' => $search,
			'fields' => 'all_with_meta'
		);

		if ( '' !== $args['search'] ) {
			$args['search'] = '*' . $args['search'] . '*';
		}

		if ( isset( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = $_REQUEST['orderby'];
		}

		if ( isset( $_REQUEST['order'] ) ) {
			$args['order'] = $_REQUEST['order'];
		}

		$user_query = new WP_User_Query( $args );
		$this->items = $user_query->get_results();

		$this->set_pagination_args( array(
			'total_items' => $user_query->get_total(),
			'per_page'    => $per_page,
		) );
	}

	/**
	 * Output 'no users' message.
	 */
	public function no_items() {
		_e( 'No translation editors were found.', 'rosetta' );
	}

	/**
	 * Get a list of columns for the list table.
	 *
	 * @return array Array in which the key is the ID of the column,
	 *               and the value is the description.
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'username' => __( 'Username', 'rosetta' ),
			'name'     => __( 'Name', 'rosetta' ),
			'email'    => __( 'E-mail', 'rosetta' ),
			'projects' => __( 'Projects', 'rosetta' ),
		);
	}

	/**
	 * Get a list of sortable columns for the list table.
	 *
	 * @return array Array of sortable columns.
	 */
	protected function get_sortable_columns() {
		return array(
			'username' => 'login',
			'name'     => 'name',
			'email'    => 'email',
		);
	}

	/**
	 * Return a list of bulk actions available on this table.
	 *
	 * @return array Array of bulk actions.
	 */
	protected function get_bulk_actions() {
		return array(
			'remove' => _x( 'Remove', 'translation editor', 'rosetta' ),
		);
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @param WP_User $user The current user.
	 */
	public function single_row( $user ) {
		$user->filter = 'display';
		parent::single_row( $user );
	}

	/**
	 * Prints the checkbox column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_cb( $user ) {
		if ( $this->user_can_promote ) {
			?>
			<label class="screen-reader-text" for="cb-select-<?php echo $user->ID; ?>"><?php _e( 'Select translation editor', 'rosetta' ); ?></label>
			<input id="cb-select-<?php echo $user->ID; ?>" type="checkbox" name="translation-editors[]" value="<?php echo $user->ID; ?>">
			<?php
		}
	}

	/**
	 * Prints the username column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_username( $user ) {
		$avatar = get_avatar( $user->ID, 32 );

		if ( $this->user_can_promote ) {
			$page_url = menu_page_url( 'translation-editors', false );
			$edit_link = esc_url( add_query_arg( 'user_id', $user->ID, $page_url ) );
			$edit = "<strong><a href=\"$edit_link\">$user->user_login</a></strong>";

			$actions = array();
			$actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit', 'rosetta' ) . '</a>';
			$actions['remove'] = '<a href="' . wp_nonce_url( $page_url . "&amp;action=remove-translation-editor&amp;translation-editor=$user->ID", 'remove-translation-editor' ) . '">' . __( 'Remove', 'rosetta' ) . '</a>';
			$edit .= $this->row_actions( $actions );
		} else {
			$edit = "<strong>$user->user_login</strong>";
		}

		echo "$avatar $edit";
	}

	/**
	 * Prints the name column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_name( $user ) {
		echo "$user->first_name $user->last_name";
	}

	/**
	 * Prints the email column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_email( $user ) {
		echo "<a href='mailto:$user->user_email'>$user->user_email</a>";
	}

	/**
	 * Prints the projects column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_projects( $user ) {
		$project_access_list = $user->get( $this->project_access_meta_key );

		if ( empty( $project_access_list ) ) {
			_e( 'No projects', 'rosetta' );
			return;
		}

		if ( in_array( 'all', $project_access_list ) ) {
			_e( 'All projects', 'rosetta' );
			return;
		}

		$projects = array();
		foreach ( $project_access_list as $project_id ) {
			if ( $this->projects[ $project_id ] ) {
				$projects[] = esc_html( $this->projects[ $project_id ]->name );
			}
		}

		echo implode( '<br>', $projects );
	}
}
