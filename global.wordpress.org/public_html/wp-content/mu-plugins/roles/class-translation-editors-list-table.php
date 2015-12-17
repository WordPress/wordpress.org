<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Rosetta_Translation_Editors_List_Table extends WP_List_Table {

	/**
	 * Holds the roles for translation editors.
	 *
	 * @var arrays
	 */
	public $user_roles;

	/**
	 * Holds the instance of the Rosetta_Roles class.
	 *
	 * @var Rosetta_Roles
	 */
	public $rosetta_roles;

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
	 * Holds the list of a project tree.
	 *
	 * @var array
	 */
	public $project_tree;

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

		$this->user_roles       = $args['user_roles'];
		$this->projects         = $args['projects'];
		$this->project_tree     = $args['project_tree'];
		$this->rosetta_roles    = $args['rosetta_roles'];
		$this->user_can_promote = current_user_can( Rosetta_Roles::MANAGE_TRANSLATION_EDITORS_CAP );
	}

	/**
	 * Prepare the list for display.
	 */
	public function prepare_items() {
		$search =   isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
		$per_page = $this->get_items_per_page( 'translation_editors_per_page', 10 );
		$paged =    $this->get_pagenum();

		$role__in = $this->user_roles;
		if ( isset( $_REQUEST['role'] ) ) {
			$role__in = $_REQUEST['role'];
		}

		$args = array(
			'number'   => $per_page,
			'offset'   => ( $paged - 1 ) * $per_page,
			'role__in' => $role__in,
			'search'   => $search,
			'fields'   => 'all_with_meta'
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
	 * Provides a list of roles and user count for that role for easy
	 * filtering of the table.
	 *
	 * @return array An array of HTML links, one for each view.
	 */
	protected function get_views() {
		$class = '';
		$view_links = array();

		$users_of_blog = count_users();

		$count_translation_editors = isset( $users_of_blog['avail_roles'][ Rosetta_Roles::TRANSLATION_EDITOR_ROLE ] ) ? $users_of_blog['avail_roles'][ Rosetta_Roles::TRANSLATION_EDITOR_ROLE ] : 0 ;
		$count_general_translation_editors = isset( $users_of_blog['avail_roles'][ Rosetta_Roles::GENERAL_TRANSLATION_EDITOR_ROLE ] ) ? $users_of_blog['avail_roles'][ Rosetta_Roles::GENERAL_TRANSLATION_EDITOR_ROLE ] : 0 ;
		$total_translation_editors = $count_translation_editors + $count_general_translation_editors;

		$all_inner_html = sprintf(
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_translation_editors,
				'translation editors'
			),
			number_format_i18n( $total_translation_editors )
		);

		if ( ! isset( $_REQUEST['role'] ) ) {
			$class = 'current';
		}

		$view_links['all'] = $this->get_view_link( array(), $all_inner_html, $class );

		if ( $count_translation_editors ) {
			$class = '';
			$translation_editors_inner_html = sprintf(
				_n(
					'Translation Editor <span class="count">(%s)</span>',
					'Translation Editor <span class="count">(%s)</span>',
					$count_translation_editors
				),
				number_format_i18n( $count_translation_editors )
			);

			if ( isset( $_REQUEST['role'] ) && Rosetta_Roles::TRANSLATION_EDITOR_ROLE === $_REQUEST['role'] ) {
				$class = 'current';
			}

			$view_links[ Rosetta_Roles::TRANSLATION_EDITOR_ROLE ] = $this->get_view_link( array( 'role' => Rosetta_Roles::TRANSLATION_EDITOR_ROLE ), $translation_editors_inner_html, $class );
		}

		if ( $count_translation_editors ) {
			$class = '';
			$general_translation_editors_inner_html = sprintf(
				_n(
					'General Translation Editor <span class="count">(%s)</span>',
					'General Translation Editor <span class="count">(%s)</span>',
					$count_general_translation_editors
				),
				number_format_i18n( $count_general_translation_editors )
			);

			if ( isset( $_REQUEST['role'] ) && Rosetta_Roles::GENERAL_TRANSLATION_EDITOR_ROLE === $_REQUEST['role'] ) {
				$class = 'current';
			}

			$view_links[ Rosetta_Roles::GENERAL_TRANSLATION_EDITOR_ROLE ] = $this->get_view_link( array( 'role' => Rosetta_Roles::GENERAL_TRANSLATION_EDITOR_ROLE ), $general_translation_editors_inner_html, $class );
		}

		return $view_links;
	}

	/**
	 * Helper to create view links with params.
	 *
	 * @param array  $args  URL parameters for the link.
	 * @param string $label Link text.
	 * @param string $class Optional. Class attribute. Default empty string.
	 * @return string The formatted link string.
	 */
	protected function get_view_link( $args, $label, $class = '' ) {
		$page_url = menu_page_url( 'translation-editors', false );
		$url = add_query_arg( $args, $page_url );

		$class_html = '';
		if ( ! empty( $class ) ) {
			 $class_html = sprintf(
				' class="%s"',
				esc_attr( $class )
			);
		}

		return sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$label
		);
	}

	/**
	 * Return a list of bulk actions available on this table.
	 *
	 * @return array Array of bulk actions.
	 */
	protected function get_bulk_actions() {
		return array(
			'remove-translation-editors' => _x( 'Remove', 'translation editor', 'rosetta' ),
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
		echo "<a href='" . esc_url( "mailto:$user->user_email" ) . "'>$user->user_email</a>";
	}

	/**
	 * Prints the projects column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_projects( $user ) {
		$project_access_list = $this->rosetta_roles->get_users_projects( $user->ID );

		if ( empty( $project_access_list ) ) {
			_e( 'No projects', 'rosetta' );
			return;
		}

		if ( in_array( 'all', $project_access_list, true ) ) {
			_e( 'All projects', 'rosetta' );
			return;
		}

		$projects = array();
		foreach ( $project_access_list as $project_id ) {
			if ( $this->projects[ $project_id ] ) {
				$parent = $this->get_parent_project( $this->project_tree, $project_id );
				if ( $parent->id != $project_id ) {
					$name = sprintf(
						/* translators: 1: Parent project name, 2: Child project name */
						__( '%1$s &rarr;  %2$s', 'rosetta' ),
						esc_html( $parent->name ),
						esc_html( $this->projects[ $project_id ]->name )
					);
				} else {
					$name = esc_html( $this->projects[ $project_id ]->name );
				}
				$projects[] = $name;
			}
		}

		echo implode( '<br>', $projects );
	}

	/**
	 * Returns the parent project for a sub project.
	 *
	 * @param array $tree The project tree.
	 * @param int $child_id The project tree.
	 * @return object The parent project.
	 */
	private function get_parent_project( $tree, $child_id ) {
		$parent = null;
		foreach ( $tree as $project ) {
			if ( $project->id == $child_id ) {
				$parent = $project;
				break;
			}

			if ( isset( $project->sub_projects ) ) {
				$parent = $this->get_parent_project( $project->sub_projects, $child_id );
				if ( $parent ) {
					$parent = $project;
					break;
				}
			}
		}

		return $parent;
	}
}
