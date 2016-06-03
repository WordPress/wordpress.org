<?php

namespace WordCamp\Budgets_Dashboard\Reimbursement_Requests;
defined( 'WPINC' ) or die();

class Reimbursement_Requests_List_Table extends \WP_List_Table {

	/**
	 * Define the table columns that will be rendered
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'request_title' => 'Request',
			'wordcamp_name' => 'WordCamp',
			'status'        => 'Status',
			'categories'    => 'Categories',
			'amount'        => 'Amount',
			'method'        => 'Method',
			'attachments'   => 'Attachments',
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
		$search     = '';

		if ( ! empty( $_REQUEST['s'] ) ) {
			$search = $wpdb->prepare(
				"AND `keywords` LIKE '%%%s%%'",
				$wpdb->esc_like( wp_unslash( $_REQUEST['s'] ) )
			);
		}

		$query = "
			SELECT *
			FROM $table_name
			WHERE
				status = %s
				{{search}}
			ORDER BY date_requested ASC
			LIMIT %d
			OFFSET %d
		";

		$query = $wpdb->prepare( $query, $status, $limit, $offset );
		$query = str_replace( '{{search}}', $search, $query );

		$this->items = $wpdb->get_results( $query );

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
	 * Output a single row in the list table.
	 *
	 * Holy cow, switch to blog! Please note that all the
	 * column_* methods are being run in a switched context.
	 *
	 * @param object $item
	 */
	public function single_row( $item ) {
		switch_to_blog( $item->blog_id );

		$request = get_post( $item->request_id );
		parent::single_row( $request );

		restore_current_blog();
	}

	/**
	 * Render the value for the Request column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param object $index_row
	 * 
	 * @return string
	 */
	protected function column_request_title( $post ) {
		$blog_id = get_current_blog_id();
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
		return $output;
	}

	/**
	 * Render the value for the WordCamp column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param \WP_Post $request
	 *
	 * @return string
	 */
	protected function column_wordcamp_name( $request ) {
		return esc_html( $request->post_title );
	}

	/**
	 * Render the value for the Status column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param \WP_Post $request
	 *
	 * @return string
	 */
	public function column_status( $request ) {
		$status = get_post_status_object( $request->post_status );

		return esc_html( $status->label );
	}

	/**
	 * Render the value for the Categories column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param \WP_Post $request
	 *
	 * @return string
	 */
	public function column_categories( $request ) {
		require_once( WP_PLUGIN_DIR . '/wordcamp-payments/includes/payment-request.php' );

		$categories        = \WordCamp_Budgets::get_payment_categories();
		$expenses            = get_post_meta( $request->ID, '_wcbrr_expenses', true );
		$selected_categories = array();

		if ( is_array( $expenses ) ) {
			foreach ( $expenses as $expense ) {
				if ( isset( $categories[ $expense['_wcbrr_category'] ] ) ) {
					$selected_categories[] = $categories[ $expense['_wcbrr_category'] ];
				}
			}
		}

		return implode( '<br />', array_unique( $selected_categories ) );
	}

	/**
	 * Render the value for the Amount column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param \WP_Post $request
	 *
	 * @return string
	 */
	protected function column_amount( $request ) {
		$currency = get_post_meta( $request->ID, '_wcbrr_currency', true );
		$expenses = get_post_meta( $request->ID, '_wcbrr_expenses', true );
		$amount   = 0;

		if ( is_array( $expenses ) ) {
			foreach ( $expenses as $expense ) {
				$amount += $expense['_wcbrr_amount'];
			}
		}

		return wp_kses(
			\WordCamp\Budgets_Dashboard\format_amount( $amount, $currency ),
			array( 'br' => array() )
		);
	}

	/**
	 * Render the value for the Method column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param \WP_Post $request
	 *
	 * @return string
	 */
	public function column_method( $request ) {
		$method = get_post_meta( $request->ID, '_wcbrr_payment_method', true );

		return esc_html( $method );
	}

	/**
	 * Render the value for the Attachments column
	 *
	 * Note: Runs in a switch_to_blog() context.
	 *
	 * @param \WP_Post $request
	 *
	 * @return string
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
