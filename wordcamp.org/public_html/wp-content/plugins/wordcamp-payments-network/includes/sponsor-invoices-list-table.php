<?php

namespace WordCamp\Budgets_Dashboard\Sponsor_Invoices;
defined( 'WPINC' ) or die();

class Sponsor_Invoices_List_Table extends \WP_List_Table {

	/**
	 * Define the table columns that will be rendered
	 */
	public function get_columns() {
		$columns = array(
			'invoice_title' => 'Invoice',
			'wordcamp_name' => 'WordCamp',
			'sponsor_name'  => 'Sponsor',
			'description'   => 'Description',
			'due_date'      => 'Due Date',
			'amount'        => 'Amount',
		);

		if ( 'submitted' === get_current_section() ) {
			$columns['approve_invoice'] = 'Approve';
		}

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
		$status     = 'wcbsi_' . get_current_section();
		$paged      = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$limit      = 30;
		$offset     = $limit * ( $paged - 1 );

		$this->items = $wpdb->get_results( $wpdb->prepare( "
			SELECT *
			FROM $table_name
			WHERE status = %s
			ORDER BY due_date ASC
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
	protected function column_invoice_title( $index_row ) {
		$edit_url = get_admin_url(
			$index_row->blog_id,
			sprintf( 'post.php?post=%s&action=edit', $index_row->invoice_id )
		);

		ob_start();
		?>

		<a href="<?php echo esc_url( $edit_url ); ?>">
			<?php echo esc_html( $index_row->invoice_title ); ?>
		</a>

		<?php

		return ob_get_clean();
	}

	/**
	 * Render the value for the Description column
	 *
	 * @param object $index_row
	 */
	protected function column_description( $index_row ) {
		return esc_html( substr( $index_row->description, 0, 75 ) );
	}

	/**
	 * Render the value for the Due Date column
	 *
	 * @param object $index_row
	 */
	protected function column_due_date( $index_row ) {
		return esc_html( date( 'Y-m-d', $index_row->due_date ) );
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
	 * Render the value for the Approve column
	 *
	 * @param object $index_row
	 */
	protected function column_approve_invoice( $index_row ) {
		$nonce = wp_create_nonce( "wcbdsi-approve-invoice-{$index_row->blog_id}-{$index_row->invoice_id}" );

		?>

		<button
			class="wcbdsi-approve-invoice button-secondary"
			data-site-id="<?php    echo esc_attr( $index_row->blog_id    ); ?>"
			data-invoice-id="<?php echo esc_attr( $index_row->invoice_id ); ?>"
			data-nonce="<?php      echo esc_attr( $nonce                 ); ?>"
		>
			Approve
		</button>

		<div class="wcbdsi-inline-notice hidden"><div> <?php // Populated dynamically ?>

		<?php
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
