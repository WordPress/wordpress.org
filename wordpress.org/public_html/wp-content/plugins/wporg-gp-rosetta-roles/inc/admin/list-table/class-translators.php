<?php

namespace WordPressdotorg\GlotPress\Rosetta_Roles\Admin\List_Table;

use WordPressdotorg\GlotPress\Rosetta_Roles\Admin\Translators as Translators_Page;

use WP_List_Table;
use WP_User_Query;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Translators extends WP_List_Table {

	/**
	 * Whether the current user can promote users.
	 *
	 * @var bool
	 */
	public $user_can_promote;

	/**
	 * Constructor.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( array(
			'singular' => 'translator',
			'plural'   => 'translators',
			'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$this->user_can_promote = current_user_can( 'promote_users' );
	}

	/**
	 * Prepare the list for display.
	 */
	public function prepare_items() {
		global $wpdb;

		$search   = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
		$per_page = $this->get_items_per_page( 'translators_per_page', 10 );
		$paged    = $this->get_pagenum();

		$role__in = [];
		if ( isset( $_REQUEST['role'] ) ) {
			$role__in = $_REQUEST['role'];
		}

		$args = array(
			'number'   => $per_page,
			'offset'   => ( $paged - 1 ) * $per_page,
			'role__in' => $role__in,
			'search'   => $search,
			'fields'   => 'all_with_meta',
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

		$translators = $wpdb->get_col(
			"SELECT DISTINCT(user_id) FROM {$wpdb->wporg_translation_editors}"
		);

		$args['blog_id'] = 0;
		$args['include'] = $translators;

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
		_e( 'No translators were found.', 'wporg-translate' );
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
			'username' => __( 'Username', 'wporg-translate' ),
			'name'     => __( 'Name', 'wporg-translate' ),
			'email'    => __( 'E-mail', 'wporg-translate' ),
			'locales'  => __( 'Locales', 'wporg-translate' ),
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
			'remove-translators' => _x( 'Remove', 'translator', 'wporg-translate' ),
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
			<label class="screen-reader-text" for="cb-select-<?php echo $user->ID; ?>"><?php _e( 'Select translator', 'wporg-translate' ); ?></label>
			<input id="cb-select-<?php echo $user->ID; ?>" type="checkbox" name="translators[]" value="<?php echo $user->ID; ?>">
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
			$page_url = menu_page_url( Translators_Page::PAGE_SLUG, false );
			$edit_link = esc_url( add_query_arg( 'user_id', $user->ID, $page_url ) );
			$edit = "<strong><a href=\"$edit_link\">$user->user_login</a></strong>";

			$actions = array();
			$actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit', 'wporg-translate' ) . '</a>';
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
	 * Prints the locales column.
	 *
	 * @param WP_User $user The current user.
	 */
	public function column_locales( $user ) {
		global $wpdb;

		$locales = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT(locale) FROM {$wpdb->wporg_translation_editors} WHERE user_id = %d"
		, $user->ID ) );

		echo implode( ', ', $locales );
	}
}
