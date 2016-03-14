<?php

namespace WordCamp\Budgets_Dashboard\Reimbursement_Requests;
defined( 'WPINC' ) or die();

class Reimbursement_Requests_List_Table extends \WP_List_Table {

	/**
	 * Define the table columns that will be rendered
	 */
	public function get_columns() {
		$columns = array(
			'request_title' => 'Request',
			'wordcamp_name' => 'WordCamp',
			'amount'        => 'Amount',
		);

		return $columns;
	}

	/**
	 * Parses query arguments and queries the index table in the database.
	 */
	public function prepare_items() {
		global $wpdb;

		/*
		 * Manually build the column headers
		 *
		 * See https://codex.wordpress.org/Class_Reference/WP_List_Table#Using_within_Meta_Boxes
		 *
		 * The alternative to this would be instantiating this object during `load-$hook-suffix`, and setting it
		 * to a global variable so it could be accessed later by render_submenu_page(). This is hacky, but that's
		 * worse.
		 */
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array(),
			$this->get_primary_column_name()
		);

		$table_name = get_index_table_name();
		$status     = get_current_section();
		$paged      = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$limit      = 30;
		$offset     = $limit * ( $paged - 1 );

		$this->items = $wpdb->get_results( $wpdb->prepare( "
			SELECT *
			FROM $table_name
			WHERE status = %s
			ORDER BY date_requested ASC
			LIMIT %d
			OFFSET %d",
			$status,
			$limit,
			$offset
		) );

		// A second query is faster than using SQL_CALC_FOUND_ROWS during the first query
		$total_items = $wpdb->get_var( $wpdb->prepare( "
			SELECT count(blog_id)
			FROM $table_name
			WHERE status = %s",
			$status
		) );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => ceil( $total_items / $limit ),
			'per_page'    => $limit,
		) );
	}

	/**
	 * Render the value for the Invoice column
	 *
	 * @param object $index_row
	 */
	protected function column_request_title( $index_row ) {
		$blog_id = $index_row->blog_id;
		switch_to_blog( $blog_id );
		$post = get_post( $index_row->request_id );
		$title = get_the_title( $post );
		$title = empty( $title ) ? '(no title)' : $title;
		$edit_post_link = add_query_arg( array( 'post' => $post->ID, 'action' => 'edit' ), admin_url( 'post.php' ) );
		$actions = array(
			'view-all' => sprintf( '<a href="%s" target="_blank">View All</a>', esc_url( admin_url( 'edit.php?post_type=wcb_reimbursement' ) ) ),
		);

		if ( $post->post_status == 'wcb-pending-approval' ) {
			$action_url = wp_nonce_url( add_query_arg( array(
				'wcb-approve' => sprintf( '%d-%d', $blog_id, $post->ID ),
			) ), sprintf( 'wcb-approve-%d-%d', $blog_id, $post->ID ) );

			$actions['wcb-approve'] = sprintf( '<a style="color: green;" onclick="return confirm(\'Approve this reimbursement request?\');" href="%s">Approve</a>', esc_url( $action_url ) );

		} elseif ( $post->post_status == 'wcb-approved' ) {
			$action_url = wp_nonce_url( add_query_arg( array(
				'wcb-set-pending-payment' => sprintf( '%d-%d', $blog_id, $post->ID ),
			) ), sprintf( 'wcb-set-pending-payment-%d-%d', $blog_id, $post->ID ) );

			$actions['wcb-set-pending-payment'] = sprintf( '<a style="color: green;" onclick="return confirm(\'Set this request as pending payment?\');" href="%s">Set as Pending Payment</a>', esc_url( $action_url ) );
		}

		ob_start();
		?>

		<a href="<?php echo esc_url( $edit_post_link ); ?>" class="row-title" target="_blank">
			<?php echo esc_html( $title ); ?>
			<?php echo $this->row_actions( $actions ); ?>
		</a>

		<?php

		$output = ob_get_clean();
		restore_current_blog();
		return $output;
	}

	/**
	 * Render the value for the Due Date column
	 *
	 * @param object $index_row
	 */
	protected function column_amount( $index_row ) {
		return wp_kses(
			\WordCamp\Budgets_Dashboard\format_amount( $index_row->amount, $index_row->currency ),
			array( 'br' => array() )
		);
	}

	/**
	 * Render the value for columns that don't have a explicit handler
	 *
	 * @param object $index_row
	 * @param string $column_name
	 */
	protected function column_default( $index_row, $column_name ) {
		echo esc_html( $index_row->$column_name );
	}
}
