<?php

namespace WordCamp\Budgets_Dashboard;
defined( 'WPINC' ) or die();

/*
 * Core functionality and helper functions shared between modules
 */

add_action( 'network_admin_menu', __NAMESPACE__ . '\register_budgets_menu' );
add_action( 'network_admin_menu', __NAMESPACE__ . '\remove_budgets_submenu', 11 ); // after other modules have registered their submenu pages
add_action( 'network_admin_menu', __NAMESPACE__ . '\import_export_admin_menu', 11 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts', 10, 1 );

add_action( 'admin_init', __NAMESPACE__ . '\process_export_request' );
add_action( 'admin_init', __NAMESPACE__ . '\process_action_approve', 11 );
add_action( 'admin_init', __NAMESPACE__ . '\process_action_set_pending_payment', 11 );

add_action( 'admin_init', __NAMESPACE__ . '\process_import_request', 11 );

/**
 * Register the Budgets Dashboard menu
 *
 * This is just an empty page so that a top-level menu can be created to hold the various pages.
 *
 * @todo This may no longer be needed once the Budgets post type and Overview pages are added
 */
function register_budgets_menu() {
	add_menu_page(
		'WordCamp Budgets Dashboard',
		'Budgets',
		'manage_network',
		'wordcamp-budgets-dashboard',
		'__return_empty_string',
		plugins_url( '/wordcamp-payments/images/dollar-sign-icon.svg' ),
		3
	);
}

/**
 * Register the Import/Export dashboard menu item.
 */
function import_export_admin_menu() {
	add_submenu_page(
		'wordcamp-budgets-dashboard',
		'WordCamp Budgets Import/Export',
		'Import/Export',
		'manage_network',
		'wcb-import-export',
		__NAMESPACE__ . '\render_import_export'
	);
}

/**
 * Render the import/export screen.
 */
function render_import_export() {
	$current_tab = 'import';
	if ( ! empty( $_GET['section'] ) && in_array( $_GET['section'], array( 'import', 'export' ) ) ) {
		$current_tab = $_GET['section'];
	}

	?>
		<div class="wrap">
			<h1>Import/Export</h1>

			<?php settings_errors(); ?>

			<h3 class="nav-tab-wrapper">
				<a class="nav-tab <?php if ( $current_tab == 'import' ) { echo 'nav-tab-active'; } ?>"
					href="<?php echo add_query_arg( array(
						'page' => 'wcb-import-export',
						'section' => 'import',
					), network_admin_url( 'admin.php' ) ); ?>">Import</a>

				<a class="nav-tab <?php if ( $current_tab == 'export' ) { echo 'nav-tab-active'; } ?>"
					href="<?php echo add_query_arg( array(
						'page' => 'wcb-import-export',
						'section' => 'export',
					), network_admin_url( 'admin.php' ) ); ?>">Export</a>
			</h3>

			<?php
				if ( 'export' == $current_tab ) {
					render_export_tab();
				} elseif ( 'import' == $current_tab ) {
					render_import_tab();
				}
			?>

		</div> <!-- /wrap -->
	<?php
}

/**
 * Get available export options.
 *
 * @return array
 */
function get_export_types() {
	return array(
		'default' => array(
			'label' => 'Regular CSV',
			'mime_type' => 'text/csv',
			'callback' => __NAMESPACE__ . '\_generate_payment_report_default',
			'filename' => 'wordcamp-payments-%s-%s-default.csv',
		),
		'jpm_wires' => array(
			'label' => 'JP Morgan Access - Wire Payments',
			'mime_type' => 'text/csv',
			'callback' => __NAMESPACE__ . '\_generate_payment_report_jpm_wires',
			'filename' => 'wordcamp-payments-%s-%s-jpm-wires.csv',
		),
		'jpm_ach' => array(
			'label' => 'JP Morgan - NACHA',
			'mime_type' => 'text/plain',
			'callback' => __NAMESPACE__ . '\_generate_payment_report_jpm_ach',
			'filename' => 'wordcamp-payments-%s-%s-jpm-ach.ach',
		),
		'jpm_checks' => array(
			'label' => 'JP Morgan - Quick Checks',
			'mime_type' => 'text/csv',
			'callback' => __NAMESPACE__ . '\_generate_payment_report_jpm_checks',
			'filename' => 'wordcamp-payments-%s-%s-jpm-checks.csv',
		),
	);
}

/**
 * Render the Import tab
 */
function render_import_tab() {
	?>
	<?php if ( isset( WCB_Import_Results::$data ) ) : ?>
	<h2>Import Results</h2>
	<table class="wp-list-table widefat">
		<thead>
			<tr>
				<th>Request</th>
				<th>Amount</th>
				<th>Blog ID</th>
				<th>Post ID</th>
				<th>Message</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( WCB_Import_Results::$data as $entry ) : ?>
			<tr>
				<td><?php
					$labels = array(
						'wcp_payment_request' => 'Vendor',
						'wcb_reimbursement' => 'Reimbursement',
					);
					$label = ! empty( $labels[ $entry['post_type'] ] ) ? $labels[ $entry['post_type'] ] : 'Unknown';
					printf( '%s: %s', $label, esc_html( $entry['post_title'] ) );
				?></td>
				<td><?php
					printf( '%s %s', number_format( $entry['amount'], 2 ), esc_html( $entry['currency'] ) );
				?></td>
				<td><?php
					if ( ! empty( $entry['blog_id'] ) ) {
						printf( '<a href="%s" target="_blank">%d</a>', esc_url( $entry['edit_all_url'] ), $entry['blog_id'] );
					}
				?></td>
				<td><?php
					if ( ! empty( $entry['post_id'] ) ) {
						printf( '<a href="%s" target="_blank">%d</a>', esc_url( $entry['edit_post_url'] ), $entry['post_id'] );
					}
				?></td>
				<td class="wcb-import-message">
					<?php if ( $entry['processed'] ) : ?>
						<svg class="success" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1472 930v318q0 119-84.5 203.5t-203.5 84.5h-832q-119 0-203.5-84.5t-84.5-203.5v-832q0-119 84.5-203.5t203.5-84.5h832q63 0 117 25 15 7 18 23 3 17-9 29l-49 49q-10 10-23 10-3 0-9-2-23-6-45-6h-832q-66 0-113 47t-47 113v832q0 66 47 113t113 47h832q66 0 113-47t47-113v-254q0-13 9-22l64-64q10-10 23-10 6 0 12 3 20 8 20 29zm231-489l-814 814q-24 24-57 24t-57-24l-430-430q-24-24-24-57t24-57l110-110q24-24 57-24t57 24l263 263 647-647q24-24 57-24t57 24l110 110q24 24 24 57t-24 57z"/></svg>
					<?php else : ?>
						<svg class="error" viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M896 128q209 0 385.5 103t279.5 279.5 103 385.5-103 385.5-279.5 279.5-385.5 103-385.5-103-279.5-279.5-103-385.5 103-385.5 279.5-279.5 385.5-103zm128 1247v-190q0-14-9-23.5t-22-9.5h-192q-13 0-23 10t-10 23v190q0 13 10 23t23 10h192q13 0 22-9.5t9-23.5zm-2-344l18-621q0-12-10-18-10-8-24-8h-220q-14 0-24 8-10 6-10 18l17 621q0 10 10 17.5t24 7.5h185q14 0 23.5-7.5t10.5-17.5z"/></svg>
					<?php endif; ?>
					<span><?php echo esc_html( $entry['message'] ); ?></span>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<p>Import payment results from JPM reports CSV.</p>
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="wcpn-request-import" value="1" />
		<?php wp_nonce_field( 'import', 'wcpn-request-import' ); ?>
		<label>Import File:</label>
		<input type="file" name="wcpn-import-file" />
		<?php submit_button( 'Import' ); ?>
	</form>
	<?php
}

/**
 * Render the export tab
 */
function render_export_tab() {
		$today = date( 'Y-m-d' );
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
			<?php wp_nonce_field( 'export', 'wcb-request-export' ); ?>

			<h2>Export Settings</h2>

			<table class="form-table">
				<tr>
					<th>Types</th>
					<td>
						<select name="wcb-export-post-type">
							<option value="wcp_payment_request">Vendor Payments</option>
							<option value="wcb_reimbursement">Reimbursements</option>
						</select>
					</td>
				<tr>
					<th>Status</th>
					<td>
						<select name="wcb-export-status">
							<option value="wcb-approved"><?php _e( 'Approved', 'wordcamporg' ); ?></option>
							<option value="wcb-paid"><?php _e( 'Paid', 'wordcamporg' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th>Date Range</th>
					<td>
						<input type="date" name="wcb-export-start-date"
							class="medium-text" value="<?php echo esc_attr( $last_month ); ?>" /> to
						<input type="date" name="wcb-export-end-date"
							class="medium-text" value="<?php echo esc_attr( $today ); ?>" />
					</td>
				</tr>
				<tr>
					<th>Format</th>
					<td>
						<select name="wcb-export-type">
							<?php foreach ( get_export_types() as $key => $export_type ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $export_type['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Download Export' ); ?>
		</form>
		<?php
}

/**
 * Process export requests
 */
function process_export_request() {
	if ( empty( $_GET['page'] ) || $_GET['page'] != 'wcb-import-export' )
		return;

	if ( empty( $_GET['section'] ) || $_GET['section'] != 'export' )
		return;

	if ( empty( $_POST['wcb-request-export'] ) )
		return;

	if ( empty( $_POST['wcb-export-post-type'] ) )
		return;

	if ( ! current_user_can( 'manage_network' ) || ! check_admin_referer( 'export', 'wcb-request-export' ) )
		return;

	$export_types = get_export_types();

	if ( array_key_exists( $_POST['wcb-export-type'], $export_types ) ) {
		$export_type = $export_types[ $_POST['wcb-export-type'] ];
	} else {
		$export_type = $export_types['default'];
	}

	$status = $_POST['wcb-export-status'];
	if ( ! in_array( $status, array( 'wcb-approved', 'wcb-paid' ) ) )
		$status = 'wcb-approved';

	$start_date = strtotime( $_POST['wcb-export-start-date'] . ' 00:00:00' );
	$end_date   = strtotime( $_POST['wcb-export-end-date']   . ' 23:59:59' );
	$filename = sprintf( $export_type['filename'], date( 'Ymd', $start_date ), date( 'Ymd', $end_date ) );
	$filename = sanitize_file_name( $filename );

	$post_type = $_POST['wcb-export-post-type'];
	if ( ! in_array( $post_type, array( 'wcp_payment_request', 'wcb_reimbursement' ) ) ) {
		add_settings_error( 'wcb-dashboard', 'bad_post_type', 'Invalid post type selected.' );
		return;
	}

	$report = generate_payment_report( array(
		'status' => $status,
		'start_date' => $start_date,
		'end_date' => $end_date,
		'export_type' => $export_type,
		'post_type' => $post_type,
	) );

	if ( is_wp_error( $report ) ) {
		add_settings_error( 'wcb-dashboard', $report->get_error_code(), $report->get_error_message() );
	} else {
		header( sprintf( 'Content-Type: %s', $export_type['mime_type'] ) );
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
 * @param array $args
 *
 * @return string | WP_Error
 */
function generate_payment_report( $args ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'status'      => '',
		'start_date'  => '',
		'end_date'    => '',
		'export_type' => '',
		'post_type'   => '',
	) );

	if ( ! is_int( $args['start_date'] ) || ! is_int( $args['end_date'] ) ) {
		return new WP_Error( 'wcb-bad-dates', 'Invalid start or end date.' );
	}

	if ( $args['post_type'] == 'wcp_payment_request' ) {
		$table_name = $wpdb->get_blog_prefix(0) . 'wordcamp_payments_index';
		$date_type = $args['status'] == 'wcb-paid' ? 'paid' : 'updated';
	} elseif ( $args['post_type'] == 'wcb_reimbursement' ) {
		$table_name = $wpdb->get_blog_prefix(0) . 'wcbd_reimbursement_requests_index';
		$date_type = $args['status'] == 'wcb-paid' ? 'date_paid' : 'date_requested'; // todo date_updated
	} else {
		return new \WP_Error( 'wcb-invalid-post-type', 'Invalid post type.' );
	}

	$request_indexes = $wpdb->get_results( $wpdb->prepare( "
		SELECT *
		FROM   `{$table_name}`
		WHERE  `{$date_type}` BETWEEN %d AND %d",
		$args['start_date'],
		$args['end_date']
	) );

	if ( ! is_callable( $args['export_type']['callback'] ) )
		return new \WP_Error( 'wcb-invalid-type', 'The export type is invalid.' );

	$args['data'] = $request_indexes;

	return call_user_func( $args['export_type']['callback'], $args );
}

/**
 * Default CSV report
 *
 * @param array $args
 *
 * @return string
 */
function _generate_payment_report_default( $args ) {
	$args = wp_parse_args( $args, array(
		'data' => array(),
		'status' => '',
		'post_type' => '',
	) );

	if ( $args['post_type'] == 'wcp_payment_request' ) {
		return \WCP_Payment_Request::_generate_payment_report_default( $args );
	} elseif ( $args['post_type'] == 'wcb_reimbursement' ) {
		return \WordCamp\Budgets\Reimbursement_Requests\_generate_payment_report_default( $args );
	}
}

/**
 * Quick Checks via JP Morgan
 *
 * @param array $args
 *
 * @return string
 */
function _generate_payment_report_jpm_checks( $args ) {
	$args = wp_parse_args( $args, array(
		'data' => array(),
		'status' => '',
		'post_type' => '',
	) );

	if ( $args['post_type'] == 'wcp_payment_request' ) {
		return \WCP_Payment_Request::_generate_payment_report_jpm_checks( $args );
	} elseif ( $args['post_type'] == 'wcb_reimbursement' ) {
		return \WordCamp\Budgets\Reimbursement_Requests\_generate_payment_report_jpm_checks( $args );
	}
}

/**
 * NACHA via JP Morgan
 *
 * @param array $args
 *
 * @return string
 */
function _generate_payment_report_jpm_ach( $args ) {
	$args = wp_parse_args( $args, array(
		'data' => array(),
		'status' => '',
		'post_type' => '',
	) );

	if ( $args['post_type'] == 'wcp_payment_request' ) {
		return \WCP_Payment_Request::_generate_payment_report_jpm_ach( $args );
	} elseif ( $args['post_type'] == 'wcb_reimbursement' ) {
		return \WordCamp\Budgets\Reimbursement_Requests\_generate_payment_report_jpm_ach( $args );
	}
}

/**
 * Wires via JP Morgan
 *
 * @param array $args
 *
 * @return string
 */
function _generate_payment_report_jpm_wires( $args ) {
	$args = wp_parse_args( $args, array(
		'data' => array(),
		'status' => '',
		'post_type' => '',
	) );

	if ( $args['post_type'] == 'wcp_payment_request' ) {
		return \WCP_Payment_Request::_generate_payment_report_jpm_wires( $args );
	} elseif ( $args['post_type'] == 'wcb_reimbursement' ) {
		return \WordCamp\Budgets\Reimbursement_Requests\_generate_payment_report_jpm_wires( $args );
	}
}

/**
 * Exclude weekends and JPM holidays.
 *
 * Needs to be updated every year.
 *
 * @return int Timestamp.
 */
function _next_business_day_timestamp() {
	static $timestamp;

	if ( isset( $timestamp ) )
		return $timestamp;

	$holidays = array(
		date( 'Ymd', strtotime( 'Friday, January 1, 2016' ) ),
		date( 'Ymd', strtotime( 'Monday, January 18, 2016' ) ),
		date( 'Ymd', strtotime( 'Monday, February 15, 2016' ) ),
		date( 'Ymd', strtotime( 'Monday, May 30, 2016' ) ),
		date( 'Ymd', strtotime( 'Monday, July 4, 2016' ) ),
		date( 'Ymd', strtotime( 'Monday, September 5, 2016' ) ),
		date( 'Ymd', strtotime( 'Friday, November 11, 2016' ) ),
		date( 'Ymd', strtotime( 'Thursday, November 24, 2016' ) ),
		date( 'Ymd', strtotime( 'Monday, December 26, 2016' ) ),
	);

	$timestamp = strtotime( 'today + 1 weekday' );
	$attempts = 5;

	while ( in_array( date( 'Ymd', $timestamp ), $holidays ) ) {
		$timestamp = strtotime( '+ 1 weekday', $timestamp );
		$attempts--;

		if ( ! $attempts )
			break;
	}

	return $timestamp;
}

/**
 * Remove the empty Budgets submenu item
 *
 * @todo This may no longer be needed once the Budgets post type and Overview pages are added
 */
function remove_budgets_submenu() {
	remove_submenu_page( 'wordcamp-budgets-dashboard', 'wordcamp-budgets-dashboard' );
}

/**
 * Enqueue scripts and styles
 */
function enqueue_scripts( $hook ) {
	wp_enqueue_style(
		'wordcamp-budgets-dashboard',
		plugins_url( 'css/wordcamp-budgets-dashboard.css', __DIR__ ),
		array(),
		3
	);

	if ( $hook == 'budgets_page_wcb-import-export' ) {
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'wp-datepicker-skins' );
	}
}

/**
 * Format an amount for display
 *
 * @param float  $amount
 * @param string $currency
 *
 * @return string
 */
function format_amount( $amount, $currency ) {
	$formatted_amount = '';
	$amount = \WordCamp_Budgets::validate_amount( $amount );

	if ( false === strpos( $currency, 'null' ) && $amount ) {
		$formatted_amount = sprintf( '%s&nbsp;%s', number_format( $amount, 2 ), $currency );

		if ( 'USD' !== $currency ) {
			$usd_amount = convert_currency( $currency, 'usd', $amount );

			if ( $usd_amount ) {
				$formatted_amount .= sprintf( '<br />~&nbsp;%s&nbsp;USD', number_format( $usd_amount, 2 ) );
			}
		}
	}

	return $formatted_amount;
}

/**
 * Currency Conversion
 *
 * @todo Now that we're pushing invoices and payments to QuickBooks, we can pull the actual value from their API,
 * instead of these estimates, which quickly become outdated since the conversion rates change daily.
 *
 * @param string $from   What currency are we selling.
 * @param string $to     What currency are we buying.
 * @param float  $amount How much we're selling.
 *
 * @return float Converted amount.
 */
function convert_currency( $from, $to, $amount ) {
	global $wpdb;

	$from = strtolower( $from );
	$to = strtolower( $to );
	$cache_key = md5( sprintf( 'wcp-exchange-rate-%s:%s', $from, $to ) );

	$rate = 0;

	if ( false === ( $rate = get_transient( $cache_key ) ) ) {
		$url = 'https://query.yahooapis.com/v1/public/yql';
		$url = add_query_arg( 'format', 'json', $url );
		$url = add_query_arg( 'env', rawurlencode( 'store://datatables.org/alltableswithkeys' ), $url );
		$url = add_query_arg( 'q',   rawurlencode( $wpdb->prepare( 'select * from yahoo.finance.xchange where pair = %s', $from . $to ) ), $url );

		$request = wp_remote_get( esc_url_raw( $url ) );
		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! empty( $body['query']['results']['rate']['Ask'] ) ) {
			$rate = floatval( $body['query']['results']['rate']['Ask'] );
		}

		set_transient( $cache_key, $rate, 24 * HOUR_IN_SECONDS );
	}

	if ( $rate < 0.0000000001 ) {
		return 0;
	}

	return $amount * $rate;
}


/**
 * Approve a payment or reimbursement request.
 */
function process_action_approve() {
	if ( ! current_user_can( 'manage_network' ) )
		return;

	if ( empty( $_GET['wcb-approve'] ) || empty( $_GET['_wpnonce'] ) )
		return;

	list( $blog_id, $post_id ) = explode( '-', $_GET['wcb-approve'] );

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], sprintf( 'wcb-approve-%d-%d', $blog_id, $post_id ) ) ) {
		add_settings_error( 'wcb-dashboard', 'nonce_error', 'Could not verify nonce.', 'error' );
		return;
	}

	switch_to_blog( $blog_id );
	$post = get_post( $post_id );

	if ( ! in_array( $post->post_type, array( 'wcp_payment_request', 'wcb_reimbursement' ) ) ) {
		add_settings_error( 'wcb-dashboard', 'type_error', 'Invalid post type.', 'error' );
		restore_current_blog();
		return;
	}

	$post->post_status = 'wcb-approved';
	wp_insert_post( $post );

	\WordCamp_Budgets::log( $post->ID, get_current_user_id(), 'Request approved via Network Admin', array(
		'action' => 'approved',
	) );

	restore_current_blog();
	add_settings_error( 'wcb-dashboard', 'success', 'Success! Request has been marked as Approved.', 'updated' );
}

/**
 * Process "Set as Pending Payment" dashboard action.
 */
function process_action_set_pending_payment() {
	if ( ! current_user_can( 'manage_network' ) )
		return;

	if ( empty( $_GET['wcb-set-pending-payment'] ) || empty( $_GET['_wpnonce'] ) )
		return;

	list( $blog_id, $post_id ) = explode( '-', $_GET['wcb-set-pending-payment'] );

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], sprintf( 'wcb-set-pending-payment-%d-%d', $blog_id, $post_id ) ) ) {
		add_settings_error( 'wcb-dashboard', 'nonce_error', 'Could not verify nonce.', 'error' );
		return;
	}

	switch_to_blog( $blog_id );
	$post = get_post( $post_id );

	if ( ! in_array( $post->post_type, array( 'wcp_payment_request', 'wcb_reimbursement' ) ) ) {
		add_settings_error( 'wcb-dashboard', 'type_error', 'Invalid post type.', 'error' );
		restore_current_blog();
		return;
	}

	$post->post_status = 'wcb-pending-payment';
	wp_insert_post( $post );

	\WordCamp_Budgets::log( $post->ID, get_current_user_id(), 'Request set as Pending Payment via Network Admin', array(
		'action' => 'set-pending-payment',
	) );

	restore_current_blog();
	add_settings_error( 'wcb-dashboard', 'success', 'Success! Request has been marked as Pending Payment.', 'updated' );
}

/**
 * Process a payments import, runs during init.
 */
function process_import_request() {
	if ( empty( $_POST['wcpn-request-import'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_network' ) || ! check_admin_referer( 'import', 'wcpn-request-import' ) ) {
		return;
	}

	if ( empty( $_FILES['wcpn-import-file'] ) ) {
		wp_die( 'Please select a file to import.' );
	}

	$file = $_FILES['wcpn-import-file'];
	$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

	if ( $ext != 'csv' ) {
		wp_die( 'Please upload a .csv file.' );
	}

	if ( $file['size'] < 1 ) {
		wp_die( 'Please upload a file that is not empty.' );
	}

	if ( $file['error'] ) {
		wp_die( 'Some other error has occurred. Sorry.' );
	}

	$handle = fopen( $file['tmp_name'], 'r' );
	$count = 0;
	$header = array();
	$results = array();

	while ( ( $line = fgetcsv( $handle ) ) !== false ) {
		// Skip first line.
		if ( ++$count == 1 ) {
			continue;
		}

		$entry = array(
			'type' => strtolower( $line[11] ),
			'title' => null,
			'post_type' => null,
			'post_title' => null,
			'status' => null,
			'amount' => null,
			'currency' => null,
			'blog_id' => null,
			'post_id' => null,
			'processed' => false,
			'message' => null,

			'edit_all_url' => null,
			'edit_post_url' => null,
		);

		switch ( $entry['type'] ) {
			case 'wire':
				if ( ! empty( $line[44] ) && preg_match( '#^wcb-([0-9]+)-([0-9]+)$#', $line[44], $matches ) ) {
					$entry['blog_id'] = $matches[1];
					$entry['post_id'] = $matches[2];
					$entry['status'] = strtolower( $line[7] );
					$entry['amount'] = round( floatval( $line[13] ), 2 );
					$entry['currency'] = strtoupper( $line[14] );
				}
				break;
			case 'ach':
				if ( ! empty( $line[91] ) && preg_match( '#^([0-9]+)-([0-9]+)$#', $line[91], $matches ) ) {
					$entry['blog_id'] = $matches[1];
					$entry['post_id'] = $matches[2];
					$entry['status'] = strtolower( $line[7] );
					$entry['amount'] = round( floatval( $line[13] ), 2 );
					$entry['currency'] = strtoupper( $line[14] );
				}
				break;
			default:
				$entry['message'] = 'Unknown payment type.';
				$results[] = $entry;
				continue;
		}

		if ( empty( $entry['blog_id'] ) || empty( $entry['post_id'] ) ) {
			$entry['message'] = 'Blog ID or post ID is empty.';
			$results[] = $entry;
			continue;
		}

		// Don't consume memory.
		wp_suspend_cache_addition( true );
		switch_to_blog( $entry['blog_id'] );

		$results[] = _import_process_entry( $entry );

		restore_current_blog();
		wp_suspend_cache_addition( false );
	}

	fclose( $handle );

	WCB_Import_Results::$data = $results;
}

/**
 * Process a single import entry.
 *
 * Runs in a switch_to_blog() context.
 *
 * @param $entry Array
 * @return Array
 */
function _import_process_entry( $entry ) {
	$post = get_post( $entry['post_id'] );
	if ( ! $post || ! in_array( $post->post_type, array( 'wcp_payment_request', 'wcb_reimbursement' ) ) ) {
		$entry['message'] = 'Post not found or post type mismatch';
		return $entry;
	}

	$entry['post_title'] = $post->post_title;
	$entry['post_type'] = $post->post_type;
	$entry['edit_all_url'] = admin_url( 'edit.php?post_type=' . $post->post_type );
	$entry['edit_post_url'] = get_edit_post_link( $post->ID );

	$currency = false;
	$amout = false;

	if ( $post->post_type == 'wcb_reimbursement' ) {
		$currency = get_post_meta( $post->ID, '_wcbrr_currency', true );
		$expenses = get_post_meta( $post->ID, '_wcbrr_expenses', true );
		foreach ( $expenses as $expense ) {
			if ( ! empty( $expense['_wcbrr_amount'] ) ) {
				$amount += floatval( $expense['_wcbrr_amount'] );
			}
		}
	} elseif ( $post->post_type == 'wcp_payment_request' ) {
		$currency = get_post_meta( $post->ID, '_camppayments_currency', true );
		$amount = floatval( get_post_meta( $post->ID, '_camppayments_payment_amount', true ) );
	}
	$amount = round( $amount, 2 );

	if ( empty( $entry['currency'] ) || $entry['currency'] != $currency ) {
		$entry['message'] = 'Currency mismatch';
		return $entry;
	}

	$entry['amount'] = floatval( $entry['amount'] );
	$entry['amount'] = round( $entry['amount'], 2 );

	if ( $entry['amount'] !== $amount ) {
		$entry['message'] = 'Payment amount mismatch';
		return $entry;
	}

	if ( $entry['type'] == 'wire' ) {

		if ( $entry['status'] != 'completed' ) {
			$entry['message'] = 'Unknown wire status.';
			return $entry;
		}

		$post->post_status = 'wcb-paid';
		wp_insert_post( $post );
		$entry['message'] = 'Wire request marked as paid.';

		\WordCamp_Budgets::log( $post->ID, get_current_user_id(), 'Wire marked as paid via an import in Network Admin', array(
			'action' => 'paid-via-import',
		) );

	} elseif ( $entry['type'] == 'ach' ) {

		if ( $entry['status'] != 'delivered' ) {
			$entry['message'] = 'Unknown ACH status.';
			return $entry;
		}

		$post->post_status = 'wcb-paid';
		wp_insert_post( $post );
		$entry['message'] = 'ACH request marked as paid.';

		\WordCamp_Budgets::log( $post->ID, get_current_user_id(), 'ACH marked as paid via an import in Network Admin', array(
			'action' => 'paid-via-import',
		) );

	} else {
		$entry['message'] = 'Unknown payment method.';
		return $entry;
	}

	// All good.
	$entry['processed'] = true;
	return $entry;
}

class WCB_Import_Results {
	public static $data;
}