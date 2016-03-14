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
	echo '<p>Move along, nothing to see here.</p>';
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
		1
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