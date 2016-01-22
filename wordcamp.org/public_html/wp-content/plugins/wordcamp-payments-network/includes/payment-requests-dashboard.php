<?php

class Payment_Requests_Dashboard {
	public static $list_table;
	public static $db_version = 6;

	/**
	 * Runs during plugins_loaded, doh.
	 */
	public static function plugins_loaded() {
		$current_site = get_current_site();

		// Schedule the aggregate event only on the main blog in the network.
		if ( get_current_blog_id() == $current_site->blog_id && ! wp_next_scheduled( 'wordcamp_payments_aggregate' ) )
			wp_schedule_event( time(), 'hourly', 'wordcamp_payments_aggregate' );

		add_action( 'wordcamp_payments_aggregate', array( __CLASS__, 'aggregate' ) );
		add_action( 'admin_enqueue_scripts',  array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_menu' ) );
		add_action( 'init', array( __CLASS__, 'upgrade' ) );
		add_action( 'init', array( __CLASS__, 'process_export_request' ) );

		// Diff-based updates to the index.
		add_action( 'save_post', array( __CLASS__, 'save_post' ) );
		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );

		if ( ! empty( $_GET['wcp-debug-network'] ) && current_user_can( 'manage_network' ) )
			add_action( 'admin_init', function() { do_action( 'wordcamp_payments_aggregate' ); }, 99 );
	}

	/**
	 * Returns the name of the custom table.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->get_blog_prefix(0) . 'wordcamp_payments_index';
	}

	/**
	 * Upgrade routine, makes sure that our schema is up to date.
	 */
	public static function upgrade() {
		global $wpdb;

		// Don't attempt to perform upgrades outside of the dashboard.
		if ( ! is_admin() )
			return;

		$current_version = get_site_option( 'wcp_network_db_version', 0 );
		if ( version_compare( $current_version, self::$db_version, '>=' ) )
			return;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";
		$sql = sprintf( "CREATE TABLE %s (
			id int(11) unsigned NOT NULL auto_increment,
			blog_id int(11) unsigned NOT NULL default '0',
			post_id int(11) unsigned NOT NULL default '0',
			created int(11) unsigned NOT NULL default '0',
			paid int(11) unsigned NOT NULL default '0',
			category varchar(255) NOT NULL default '',
			method varchar(255) NOT NULL default '',
			due int(11) unsigned NOT NULL default '0',
			status varchar(255) NOT NULL default '',
			keywords text NOT NULL default '',
			PRIMARY KEY  (id),
			KEY blog_post_id (blog_id, post_id),
			KEY due (due),
			KEY status (status)
		) %s;", self::get_table_name(), $charset_collate );

		dbDelta( $sql );

		update_site_option( 'wcp_network_db_version', self::$db_version );
	}

	/**
	 * Runs on a cron job, reads data from all sites in the network
	 * and builds an index table for future queries.
	 */
	public static function aggregate() {
		global $wpdb;

		// Register the custom payment statuses so that we can filter posts to include only them, in order to exclude trashed posts
		require_once( WP_PLUGIN_DIR . '/wordcamp-payments/includes/payment-request.php' );
		WCP_Payment_Request::register_post_statuses();

		// Truncate existing table.
		$wpdb->query( sprintf( "TRUNCATE TABLE %s;", self::get_table_name() ) );

		$blogs = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM `{$wpdb->blogs}` WHERE site_id = %d ORDER BY last_updated DESC LIMIT %d;", $wpdb->siteid, 1000 ) );
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );

			$paged = 1;
			while ( $requests = get_posts( array(
				'paged' => $paged++,
				'post_status' => array( 'paid', 'unpaid', 'incomplete' ),
				'post_type' => 'wcp_payment_request',
				'posts_per_page' => 20,
			) ) ) {
				foreach ( $requests as $request ) {
					$wpdb->insert( self::get_table_name(), self::prepare_for_index( $request ) );
				}
			}

			restore_current_blog();
		}
	}

	/**
	 * Given a $request (could be a post_id) create an array that can
	 * be used with $wpdb->update() or $wpdb->insert() to add or update
	 * an index entry.
	 */
	public static function prepare_for_index( $request ) {
		$request = get_post( $request );
		$categories = WCP_Payment_Request::get_payment_categories();

		// All things search.
		$keywords = array( $request->post_title );

		$category_slug = get_post_meta( $request->ID, '_camppayments_payment_category', true );
		if ( ! empty( $categories[ $category_slug ] ) )
			$keywords[] = $categories[ $category_slug ];

		$payment_method = get_post_meta( $request->ID, '_camppayments_payment_method', true );
		if ( ! empty( $payment_method ) )
			$keywords[] = $payment_method;

		return array(
			'blog_id' => get_current_blog_id(),
			'post_id' => $request->ID,
			'created' => get_post_time( 'U', true, $request->ID ),
				// todo Sometimes this is empty. Core normally catches this (r8636), but misses in our case because we don't use drafts. #1350-meta might have the side-effect of solving this.
			'paid'    => absint( get_post_meta( $request->ID, '_camppayments_date_vendor_paid', true ) ),
			'due' => absint( get_post_meta( $request->ID, '_camppayments_due_by', true ) ),
			'status' => $request->post_status,
			'method' => $payment_method,
			'category' => $category_slug,
			'keywords' => json_encode( $keywords ),
		);
	}

	/**
	 * Runs during save_post, make sure our index is up to date.
	 */
	public static function save_post( $post_id ) {
		global $wpdb;

		$request = get_post( $post_id );
		if ( 'wcp_payment_request' != $request->post_type )
			return;

		$table_name = self::get_table_name();
		$entry_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE `blog_id` = %d AND `post_id` = %d LIMIT 1;", get_current_blog_id(), $request->ID ) );

		// Insert or update this record.
		if ( empty( $entry_id ) ) {
			$wpdb->insert( $table_name, self::prepare_for_index( $request ) );
		} else {
			$wpdb->update( $table_name, self::prepare_for_index( $request ), array( 'id' => $entry_id ) );
		}
	}

	/**
	 * Delete an index query when a request post has been deleted.
	 */
	public static function delete_post( $post_id ) {
		global $wpdb;

		$request = get_post( $post_id );
		if ( 'wcp_payment_request' != $request->post_type )
			return;

		$table_name = self::get_table_name();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE `blog_id` = %d AND `post_id` = %d LIMIT 1;", get_current_blog_id(), $request->ID ) );
	}

	/**
	 * Create a network admin menu item entry.
	 */
	public static function network_admin_menu() {
		$dashboard = add_submenu_page(
			'wordcamp-budgets-dashboard',
			'WordCamp Payments Requests',
			'Payments Requests',
			'manage_network',
			'wcp-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);

		add_action( 'load-' . $dashboard, array( __CLASS__, 'pre_render_dashboard' ) );
	}

	/**
	 * Enqueue scripts and stylesheets
	 *
	 * @param string $hook
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'index_page_wcp-dashboard' == $hook && 'export' == self::get_current_tab() ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_style( 'wp-datepicker-skins' );
		}
	}

	/**
	 * Renders the Dashboard - Payments screen.
	 */
	public static function render_dashboard() {
		?>

		<div class="wrap">
			<h1>Payment Requests</h1>

			<?php settings_errors(); ?>

			<h3 class="nav-tab-wrapper"><?php self::render_dashboard_tabs(); ?></h3>

			<?php
				if ( 'export' == self::get_current_tab() ) {
					self::render_export_tab();
				} else {
					self::render_table_tabs();
				}
			?>

		</div> <!-- /wrap -->

		<?php
	}

	/**
	 * Render the table tabs, like Overview, Pending, etc
	 */
	protected static function render_table_tabs() {
		?>

		<?php self::$list_table->print_inline_css(); ?>

		<div id="wcp-list-table">
			<?php self::$list_table->prepare_items(); ?>

			<form id="posts-filter" action="" method="get">
				<input type="hidden" name="page" value="wcp-dashboard" />
				<input type="hidden" name="wcp-section" value="<?php echo esc_attr( self::get_current_tab() ); ?>" />
				<?php self::$list_table->search_box( __( 'Search Payments', 'wordcamporg' ), 'wcp' ); ?>
				<?php self::$list_table->display(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Process export requests
	 */
	public static function process_export_request() {
		if ( empty( $_POST['submit'] ) || 'export' != self::get_current_tab() ) {
			return;
		}

		if ( ! current_user_can( 'manage_network' ) || ! check_admin_referer( 'export', 'wcpn_request_export' ) ) {
			return;
		}

		$start_date = strtotime( $_POST['wcpn_export_start_date'] . ' 00:00:00' );
		$end_date   = strtotime( $_POST['wcpn_export_end_date']   . ' 23:59:59' );
		$filename   = sanitize_file_name( sprintf( 'wordcamp-payments-%s-to-%s.csv', date( 'Y-m-d', $start_date ), date( 'Y-m-d', $end_date ) ) );

		$report = self::generate_payment_report( $_POST['wcpn_date_type'], $start_date, $end_date );

		if ( is_wp_error( $report ) ) {
			add_settings_error( 'wcp-dashboard', $report->get_error_code(), $report->get_error_message() );
		} else {
			header( 'Content-Type: text/csv' );
			header( sprintf( 'Content-Disposition: attachment; filename="%s"', $filename ) );
			header( 'Cache-control: private' );
			header( 'Pragma: private' );
			header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );

			echo $report;
			die();
		}
	}

	/*
	 * Generate and return the raw payment report contents
	 *
	 * @param string $date_type 'paid' | 'created'
	 * @param int $start_date
	 * @param int $end_date
	 *
	 * @return string | WP_Error
	 */
	protected static function generate_payment_report( $date_type, $start_date, $end_date ) {
		global $wpdb;

		if ( ! in_array( $date_type, array( 'paid', 'created' ), true ) ) {
			return new WP_Error( 'wcpn_bad_date_type', 'Invalid date type.' );
		}

		if ( ! is_int( $start_date ) || ! is_int( $end_date ) ) {
			return new WP_Error( 'wcpn_bad_dates', 'Invalid start or end date.' );
		}

		$column_headings = array(
			'WordCamp', 'ID', 'Title', 'Status', 'Date Vendor was Paid', 'Creation Date', 'Due Date', 'Amount',
			'Currency', 'Category', 'Payment Method','Vendor Name', 'Vendor Contact Person', 'Vendor Country',
			'Check Payable To', 'URL', 'Supporting Documentation Notes',
		);

		$table_name = self::get_table_name();

		$request_indexes = $wpdb->get_results( $wpdb->prepare( "
			SELECT *
			FROM   `{$table_name}`
			WHERE  `{$date_type}` BETWEEN %d AND %d",
			$start_date,
			$end_date
		) );

		ob_start();
		$report = fopen( 'php://output', 'w' );

		fputcsv( $report, $column_headings );

		foreach( $request_indexes as $index ) {
			fputcsv( $report, self::get_report_row( $index ) );
		}

		fclose( $report );
		return ob_get_clean();
	}

	/**
	 * Gather all the request details needed for a row in the export file
	 *
	 * @param stdClass $index
	 *
	 * @return array
	 */
	protected static function get_report_row( $index ) {
		switch_to_blog( $index->blog_id );

		$request          = get_post( $index->post_id );
		$currency         = get_post_meta( $index->post_id, '_camppayments_currency',         true );
		$category         = get_post_meta( $index->post_id, '_camppayments_payment_category', true );
		$date_vendor_paid = get_post_meta( $index->post_id, '_camppayments_date_vendor_paid', true );

		if ( $date_vendor_paid ) {
			$date_vendor_paid = date( 'Y-m-d', $date_vendor_paid );
		}

		if ( 'null-select-one' === $currency ) {
			$currency = '';
		}

		if ( 'null' === $category ) {
			$category = '';
		}

		$row = array(
			get_wordcamp_name(),
			sprintf( '%d-%d', $index->blog_id, $index->post_id ),
			$request->post_title,
			$index->status,
			$date_vendor_paid,
			date( 'Y-m-d', $index->created ),
			date( 'Y-m-d', $index->due ),
			get_post_meta( $index->post_id, '_camppayments_payment_amount', true ),
			$currency,
			$category,
			get_post_meta( $index->post_id, '_camppayments_payment_method', true ),
			get_post_meta( $index->post_id, '_camppayments_vendor_name', true ),
			get_post_meta( $index->post_id, '_camppayments_vendor_contact_person', true ),
			get_post_meta( $index->post_id, '_camppayments_vendor_country', true ),
			WCP_Encryption::maybe_decrypt( get_post_meta( $index->post_id, '_camppayments_payable_to', true ) ),
			get_edit_post_link( $index->post_id ),
			get_post_meta( $index->post_id, '_camppayments_file_notes', true ),
		);

		restore_current_blog();

		return $row;
	}

	/**
	 * Render the Export tab
	 */
	protected static function render_export_tab() {
		$today      = date( 'Y-m-d' );
		$last_month = date( 'Y-m-d', strtotime( 'now - 1 month' ) );
		?>

		<script>
			/**
			 * Fallback to the jQueryUI datepicker if the browser doesn't support <input type="date">
			 */
			jQuery( document ).ready( function( $ ) {
				var browserTest = document.createElement( 'input' );
				browserTest.setAttribute( 'type', 'date' );

				if ( 'text' === browserTest.type ) {
					$( '#wcpn_export' ).find( 'input[type=date]' ).datepicker( {
						dateFormat : 'yy-mm-dd',
						changeMonth: true,
						changeYear : true
					} );
				}
			} );
		</script>

		<form id="wcpn_export" method="POST">
			<?php wp_nonce_field( 'export', 'wcpn_request_export' ); ?>

			<p>
				This form will supply a CSV file with payment requests matching the parameters you select below.
				For example, all requests that were <code>paid</code> between <code><?php echo esc_html( $last_month ); ?></code> and <code><?php echo esc_html( $today ); ?></code>.
			</p>

			<p>
				<label>
					Date type:
					<select name="wcpn_date_type">
						<option value="created">created</option>
						<option value="paid" selected>paid</option>
					</select>
				</label>
			</p>

			<p>
				<label>
					Start date:
					<input type="date" name="wcpn_export_start_date" class="medium-text" value="<?php echo esc_attr( $last_month ); ?>" />
				</label>
			</p>

			<p>
				<label>
					End date:
					<input type="date" name="wcpn_export_end_date" class="medium-text" value="<?php echo esc_attr( $today ); ?>" />
				</label>
			</p>

			<?php submit_button( 'Export' ); ?>
		</form>

		<?php
	}

	/**
	 * Loads and initializes the list table object.
	 */
	public static function pre_render_dashboard() {
		require_once( __DIR__ . '/payment-requests-list-table.php' );

		self::$list_table = new Payment_Requests_List_Table();
	}

	/**
	 * Returns the current active tab in the UI.
	 */
	public static function get_current_tab() {
		$tab = 'overdue';

		if ( isset( $_REQUEST['wcp-section'] ) && in_array( $_REQUEST['wcp-section'], array( 'pending', 'overdue', 'paid', 'incomplete', 'export' ) ) ) {
			$tab = $_REQUEST['wcp-section'];
		}

		return $tab;
	}

	/**
	 * Renders available tabs.
	 */
	public static function render_dashboard_tabs() {
		$current_section = self::get_current_tab();
		$sections = array(
			'overdue' => 'Overdue',
			'pending' => 'Pending',
			'paid'    => 'Paid',
			'incomplete' => __( 'Incomplete', 'wordcamporg' ),
			'export'     => __( 'Export', 'wordcamporg' ),
		);

		foreach ( $sections as $section_key => $section_caption ) {
			$active = $current_section === $section_key ? 'nav-tab-active' : '';
			$url = add_query_arg( array(
				'wcp-section' => $section_key,
				'page' => 'wcp-dashboard',
			), network_admin_url( 'admin.php' ) );
			echo '<a class="nav-tab ' . $active . '" href="' . esc_url( $url ) . '">' . esc_html( $section_caption ) . '</a>';
		}
	}
}
