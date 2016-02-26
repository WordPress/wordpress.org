<?php

/**
 * This list table class handles the output of data
 * in the Payment Network Dashboard. Use the set_view() method
 * to set a specific view/filter for the data.
 *
 * Note: Uses switch_to_blog() excessively.
 */
class Payment_Requests_List_Table extends WP_List_Table {

	/**
	 * Used by the parent class, returns an array of
	 * columns to display.
	 */
	public function get_columns() {
		return array(
			'payment' => 'Payment',
			'status' => 'Status',
			'category' => 'Category',
			'due' => 'Due',
			'amount' => 'Amount',
			'method' => 'Method',
			'attachments' => 'Attachments',
		);
	}

	/**
	 * Tells which columns are sortable. The array values
	 * are the ones passed on to the orderby request argument.
	 */
	public function get_sortable_columns() {
		return array(
			'category' => 'category',
			'due' => 'due',
			'status' => 'status',
			'method' => 'method',
		);
	}

	/**
	 * Outputs inline CSS to be used with the list table.
	 */
	public function print_inline_css() {
		?>
		<style>
		#wcp-list-table .search-box {
			margin-top: 12px;
		}

		#wcp-list-table .manage-column.column-payment {
			width: 30%;
		}

		#wcp-list-table .manage-column {
			width: 10%;
		}
		</style>
		<?php
	}

	/**
	 * Parses query arguments and queries the index table in the database.
	 */
	public function prepare_items() {
		global $wpdb;

		$sql = sprintf( "SELECT SQL_CALC_FOUND_ROWS blog_id, post_id FROM `%s` WHERE 1=1 ", Payment_Requests_Dashboard::get_table_name() );
		$view = Payment_Requests_Dashboard::get_current_tab();
		$where = '';
		$orderby = '';
		$limit = '';

		$per_page = 10;
		$orderby = 'due';
		$order = 'asc';

		if ( 'overdue' == $view ) {
			$where .= $wpdb->prepare( " AND `status` = 'wcb-pending-approval' AND `due` > 0 AND `due` <= %d ", time() );
		} elseif ( 'pending-approval' == $view ) {
			$where .= " AND `status` = 'wcb-pending-approval' ";
		} elseif ( 'approved' == $view ) {
			$where .= " AND `status` = 'wcb-approved' ";
		} elseif ( 'pending-payment' == $view ) {
			$where .= " AND `status` = 'wcb-pending-payment' ";
		} elseif ( 'paid' == $view ) {
			$where .= " AND `status` = 'wcb-paid' ";
			$orderby = 'updated';
			$order = 'desc';
		} elseif ( 'incomplete' == $view ) {
			$where .= " AND `status` = 'wcb-incomplete' ";
		} elseif ( 'cancelled-failed' == $view ) {
			$where .= " AND `status` IN ( 'wcb-failed', 'wcb-cancelled' ) ";
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$where .= $wpdb->prepare( " AND `keywords` LIKE %s ", '%' . $wpdb->esc_like( wp_unslash( $_REQUEST['s'] ) ) . '%' );
		}

		if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_values( $this->get_sortable_columns() ) ) )
			$orderby = $_REQUEST['orderby'];

		if ( ! empty( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) )
			$order = $_REQUEST['order'];

		$orderby = sprintf( " ORDER BY `%s` %s ", $orderby, $order );

		$paged = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;

		$limit .= sprintf( " LIMIT %d OFFSET %d ", $per_page, $per_page * ( $paged - 1 ) );

		$sql .= $where . $orderby . $limit;

		$this->items = $wpdb->get_results( $sql );

		$total_items = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => ceil( $total_items / $per_page ),
			'per_page' => $per_page,
		) );
		return;
	}

	/**
	 * Output a single row in the list table.
	 *
	 * Holy cow, switch to blog! Please note that all the
	 * column_* methods are being run in a switched context.
	 */
	public function single_row( $item ) {
		switch_to_blog( $item->blog_id );
		$request = get_post( $item->post_id );
		parent::single_row( $request );
		restore_current_blog();
	}

	/**
	 * Return the payment column contents.
	 *
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_payment( $request ) {
		$blog_id = get_current_blog_id();
		$title = empty( $request->post_title ) ? '(no title)' : $request->post_title;
		$edit_post_link = add_query_arg( array( 'post' => $request->ID, 'action' => 'edit' ), admin_url( 'post.php' ) );
		$actions = array(
			'view-all' => sprintf( '<a href="%s" target="_blank">View All</a>', esc_url( admin_url( 'edit.php?post_type=wcp_payment_request' ) ) ),
		);

		if ( $request->post_status == 'wcb-pending-approval' ) {
			$action_url = wp_nonce_url( add_query_arg( array(
				'wcb-approve' => sprintf( '%d-%d', $blog_id, $request->ID ),
			) ), sprintf( 'wcb-approve-%d-%d', $blog_id, $request->ID ) );

			$actions['wcb-approve'] = sprintf( '<a style="color: green;" onclick="return confirm(\'Approve this payment request?\');" href="%s">Approve</a>', esc_url( $action_url ) );

		} elseif ( $request->post_status == 'wcb-approved' ) {
			$action_url = wp_nonce_url( add_query_arg( array(
				'wcb-set-pending-payment' => sprintf( '%d-%d', $blog_id, $request->ID ),
			) ), sprintf( 'wcb-set-pending-payment-%d-%d', $blog_id, $request->ID ) );

			$actions['wcb-set-pending-payment'] = sprintf( '<a style="color: green;" onclick="return confirm(\'Set this request as pending payment?\');" href="%s">Set as Pending Payment</a>', esc_url( $action_url ) );
		}

		return sprintf( '<a href="%s" class="row-title" target="_blank">%s</a>%s',
			esc_url( $edit_post_link ),
			esc_html( $title ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_status( $request ) {
		$status = get_post_status_object( $request->post_status );
		return esc_html( $status->label );
	}

	/**
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_category( $request ) {
		require_once( WP_PLUGIN_DIR . '/wordcamp-payments/includes/payment-request.php' );
		$categories        = WordCamp_Budgets::get_payment_categories();
		$selected_category = get_post_meta( $request->ID, '_camppayments_payment_category', true );

		return isset( $categories[ $selected_category ] ) ? $categories[ $selected_category ] : '';
	}

	/**
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_amount( $request ) {
		$currency = get_post_meta( $request->ID, '_camppayments_currency', true );
		$amount = get_post_meta( $request->ID, '_camppayments_payment_amount', true );

		return wp_kses(
			\WordCamp\Budgets_Dashboard\format_amount( $amount, $currency ),
			array( 'br' => array() )
		);
	}

	/**
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_due( $request ) {
		$due = get_post_meta( $request->ID, '_camppayments_due_by', true );
		return $due ? date( 'Y-m-d', $due ) : '';
	}

	/**
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_method( $request ) {
		$method = get_post_meta( $request->ID, '_camppayments_payment_method', true );
		return esc_html( $method );
	}

	/**
	 * Note: runs in a switch_to_blog() context.
	 */
	public function column_attachments( $request ) {
		$attachments = get_children( array( 'post_parent' => $request->ID ) );
		$attachments = array_map( 'wp_get_attachment_url', wp_list_pluck( $attachments, 'ID' ) );

		$output = array();
		foreach ( $attachments as $attachment ) {
			$output[] = sprintf( '<a href="%s" target="_blank" class="dashicons dashicons-media-default" title="%s"></a>',
				esc_url( $attachment ), esc_attr( $attachment ) );
		}

		return implode( '', $output );
	}
}
