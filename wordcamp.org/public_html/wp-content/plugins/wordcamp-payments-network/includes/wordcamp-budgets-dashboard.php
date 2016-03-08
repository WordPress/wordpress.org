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
		'request_indexes' => array(),
		'status' => '',
	) );

	ob_start();
	$report = fopen( 'php://output', 'w' );

	// JPM Header
	fputcsv( $report, array( 'HEADER', gmdate( 'YmdHis' ), '1' ) );

	$total = 0;
	$count = 0;

	foreach ( $args['request_indexes'] as $index ) {
		switch_to_blog( $index->blog_id );
		$post = get_post( $index->post_id );

		if ( $args['status'] && $post->post_status != $args['status'] ) {
			restore_current_blog();
			continue;
		}

		// Only wires here.
		if ( get_post_meta( $post->ID, '_camppayments_payment_method', true ) != 'Wire' ) {
			restore_current_blog();
			continue;
		}

		$amount = round( floatval( get_post_meta( $post->ID, '_camppayments_payment_amount', true ) ), 2);
		$total += $amount;
		$count += 1;

		// If account starts with two letters, it's most likely an IBAN
		$account = get_post_meta( $post->ID, '_camppayments_beneficiary_account_number', true );
		$account = \WCP_Encryption::maybe_decrypt( $account );
		$account = preg_replace( '#\s#','', $account );
		$account_type = preg_match( '#^[a-z]{2}#i', $account ) ? 'IBAN' : 'ACCT';

		$row = array(
			'1-input-type' => 'P',
			'2-payment-method' => 'WIRES',
			'3-debit-bank-id' => apply_filters( 'wcb_payment_req_bank_id', '' ), // external file
			'4-account-number' => apply_filters( 'wcb_payment_req_bank_number', '' ), // external file
			'5-bank-to-bank' => 'N',
			'6-txn-currency' => get_post_meta( $post->ID, '_camppayments_currency', true ),
			'7-txn-amount' => number_format( $amount, 2, '.', '' ),
			'8-equiv-amount' => '',
			'9-clearing' => '',
			'10-ben-residence' => '',
			'11-rate-type' => '',
			'12-blank' => '',
			'13-value-date' => '',

			'14-id-type' => $account_type,
			'15-id-value' => $account,
			'16-ben-name' => substr( \WCP_Encryption::maybe_decrypt(
				get_post_meta( $post->ID, '_camppayments_beneficiary_name', true ) ), 0, 35 ),
			'17-address-1' => substr( \WCP_Encryption::maybe_decrypt(
				get_post_meta( $post->ID, '_camppayments_beneficiary_street_address', true ) ), 0, 35 ),
			'18-address-2' => '',
			'19-city-state-zip' => substr( sprintf( '%s %s %s',
					\WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_beneficiary_city', true ) ),
					\WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_beneficiary_state', true ) ),
					\WCP_Encryption::maybe_decrypt( get_post_meta( $post->ID, '_camppayments_beneficiary_zip_code', true ) )
				), 0, 32 ),
			'20-blank' => '',
			'21-country' => \WCP_Encryption::maybe_decrypt(
				get_post_meta( $post->ID, '_camppayments_beneficiary_country_iso3166', true ) ),
			'22-blank' => '',
			'23-blank' => '',

			'24-id-type' => 'SWIFT',
			'25-id-value' => get_post_meta( $post->ID, '_camppayments_bank_bic', true ),
			'26-ben-bank-name' => substr( get_post_meta( $post->ID, '_camppayments_bank_name', true ), 0, 35 ),
			'27-ben-bank-address-1' => substr( get_post_meta( $post->ID, '_camppayments_bank_street_address', true ), 0, 35 ),
			'28-ben-bank-address-2' => '',
			'29-ben-bank-address-3' => substr( sprintf( '%s %s %s',
					get_post_meta( $post->ID, '_camppayments_bank_city', true ),
					get_post_meta( $post->ID, '_camppayments_bank_state', true ),
					get_post_meta( $post->ID, '_camppayments_bank_zip_code', true )
				 ), 0, 35 ),
			'30-ben-bank-country' => get_post_meta( $post->ID, '_camppayments_bank_country_iso3166', true ),
			'31-supl-id-type' => '',
			'32-supl-id-value' => '',

			'33-blank' => '',
			'34-blank' => '',
			'35-blank' => '',
			'36-blank' => '',
			'37-blank' => '',
			'38-blank' => '',
			'39-blank' => '',

			// Filled out later if not empty.
			'40-id-type' => '',
			'41-id-value' => '',
			'42-interm-bank-name' => '',
			'43-interm-bank-address-1' => '',
			'44-interm-bank-address-2' => '',
			'45-interm-bank-address-3' => '',
			'46-interm-bank-country' => '',
			'47-supl-id-type' => '',
			'48-supl-id-value' => '',

			'49-id-type' => '',
			'50-id-value' => '',
			'51-party-name' => '',
			'52-party-address-1' => '',
			'53-party-address-2' => '',
			'54-party-address-3' => '',
			'55-party-country' => '',

			'56-blank' => '',
			'57-blank' => '',
			'58-blank' => '',
			'59-blank' => '',
			'60-blank' => '',
			'61-blank' => '',
			'62-blank' => '',
			'63-blank' => '',
			'64-blank' => '',
			'65-blank' => '',
			'66-blank' => '',
			'67-blank' => '',
			'68-blank' => '',
			'69-blank' => '',
			'70-blank' => '',
			'71-blank' => '',
			'72-blank' => '',
			'73-blank' => '',

			'74-ref-text' => substr( get_post_meta( $post->ID, '_camppayments_invoice_number', true ), 0, 16 ),
			'75-internal-ref' => '',
			'76-on-behalf-of' => '',

			'77-detial-1' => '',
			'78-detial-2' => '',
			'79-detial-3' => '',
			'80-detail-4' => '',

			'81-blank' => '',
			'82-blank' => '',
			'83-blank' => '',
			'84-blank' => '',
			'85-blank' => '',
			'86-blank' => '',
			'87-blank' => '',
			'88-blank' => '',

			'89-reporting-code' => '',
			'90-country' => '',
			'91-inst-1' => '',
			'92-inst-2' => '',
			'93-inst-3' => '',
			'94-inst-code-1' => '',
			'95-inst-text-1' => '',
			'96-inst-code-2' => '',
			'97-inst-text-2' => '',
			'98-inst-code-3' => '',
			'99-inst-text-3' => '',

			'100-stor-code-1' => '',
			'101-stor-line-2' => '', // Hmm?
			'102-stor-code-2' => '',
			'103-stor-line-2' => '',
			'104-stor-code-3' => '',
			'105-stor-line-3' => '',
			'106-stor-code-4' => '',
			'107-stor-line-4' => '',
			'108-stor-code-5' => '',
			'109-stor-line-5' => '',
			'110-stor-code-6' => '',
			'111-stor-line-6' => '',

			'112-priority' => '',
			'113-blank' => '',
			'114-charges' => '',
			'115-blank' => '',
			'116-details' => '',
			'117-note' => substr( sprintf( 'wcb-%d-%d', $index->blog_id, $index->post_id ), 0, 70 ),
		);

		// If an intermediary bank is given.
		$interm_swift = get_post_meta( $post->ID, '_camppayments_interm_bank_swift', true );
		if ( ! empty( $iterm_swift ) ) {
			$row['40-id-type'] = 'SWIFT';
			$row['41-id-value'] = $interm_swift;

			$row['42-interm-bank-name'] = substr( get_post_meta( $post->ID, '_camppayments_interm_bank_name', true ), 0, 35 );
			$row['43-interm-bank-address-1'] = substr( get_post_meta( $post->ID, '_camppayments_interm_bank_street_address', true ), 0, 35 );

			$row['44-interm-bank-address-2'] = '';
			$row['45-interm-bank-address-3'] = substr( sprintf( '%s %s %s',
				get_post_meta( $post->ID, '_camppayments_interm_bank_city', true ),
				get_post_meta( $post->ID, '_camppayments_interm_bank_state', true ),
				get_post_meta( $post->ID, '_camppayments_interm_bank_zip_code', true )
			), 0, 32 );

			$row['46-interm-bank-country'] = get_post_meta( $post->ID, '_camppayments_interm_bank_country_iso3166', true );

			$row['47-supl-id-type'] = 'ACCT';
			$row['48-supl-id-value'] = get_post_meta( $post->ID, '_camppayments_interm_bank_account', true );
		}

		// Use for debugging.
		// print_r( $row );

		fputcsv( $report, array_values( $row ) );
		restore_current_blog();
	}

	// JPM Trailer
	fputcsv( $report, array( 'TRAILER', $count, $total ) );

	fclose( $report );
	$results = ob_get_clean();

	// JPM chokes on accents and non-latin characters.
	$results = remove_accents( $results );
	return $results;
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
