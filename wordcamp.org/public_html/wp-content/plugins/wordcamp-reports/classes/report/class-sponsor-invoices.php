<?php
/**
 * Sponsor Invoices.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use WordCamp\Reports;
use WordCamp\Utilities;
use function WordCamp\Reports\Validation\{validate_wordcamp_id};
use WordCamp\Budgets_Dashboard\Sponsor_Invoices as WCBD_Sponsor_Invoices;

/**
 * Class Sponsor_Invoices
 *
 * @package WordCamp\Reports\Report
 */
class Sponsor_Invoices extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Sponsor Invoices';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'sponsor-invoices';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Sponsor invoices sent and paid.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Retrieve data from QuickBooks Online via their API for invoices sent during the specified date range.</li>
			<li>Match the invoice data against indexed invoices in the WordCamp database.</li>
			<li>Also via the QuickBooks Online API, retrieve data for payments made during the date range.</li>
			<li>Filter out payments that aren't related to invoices (but keep payments made to invoices not sent within the date range).</li>
		</ol>
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'finance';

	/**
	 * Shortcode tag for outputting the public report form.
	 *
	 * @var string
	 */
	public static $shortcode_tag = 'sponsor_invoices_report';

	/**
	 * WordCamp post ID.
	 *
	 * @var int The ID of the WordCamp post for this report.
	 */
	public $wordcamp_id = 0;

	/**
	 * WordCamp site ID.
	 *
	 * @var int The ID of the WordCamp site where the invoices are located.
	 */
	public $wordcamp_site_id = 0;

	/**
	 * Currency exchange rate client.
	 *
	 * @var Utilities\Currency_XRT_Client Utility to handle currency conversion.
	 */
	protected $xrt = null;

	/**
	 * Data fields that can be visible in a public context.
	 *
	 * @var array An associative array of key/default value pairs.
	 */
	protected $public_data_fields = array(
		'date'          => '',
		'type'          => '',
		'invoice_id'    => 0,
		'wordcamp_name' => '',
		'sponsor_name'  => '',
		'invoice_title' => '',
		'currency'      => '',
		'amount'        => 0,
	);

	/**
	 * Sponsor_Invoices constructor.
	 *
	 * @param string $start_date  The start of the date range for the report.
	 * @param string $end_date    The end of the date range for the report.
	 * @param int    $wordcamp_id Optional. The ID of a WordCamp post to limit this report to.
	 * @param array  $options     {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and Date_Range::__construct for additional parameters.
	 * }
	 */
	public function __construct( $start_date, $end_date, $wordcamp_id = 0, array $options = array() ) {
		parent::__construct( $start_date, $end_date, $options );

		$this->xrt = new Utilities\Currency_XRT_Client();

		if ( $wordcamp_id ) {
			try {
				$valid = validate_wordcamp_id( $wordcamp_id );

				$this->wordcamp_id      = $valid->post_id;
				$this->wordcamp_site_id = $valid->site_id;
			} catch( Exception $e ) {
				$this->error->add(
					self::$slug . '-wordcamp-id-error',
					$e->getMessage()
				);
			}
		}
	}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = parent::get_cache_key();

		if ( $this->wordcamp_id ) {
			$cache_key .= '_' . $this->wordcamp_id;
		}

		return $cache_key;
	}

	/**
	 * Query and parse the data for the report.
	 *
	 * @todo Take into account refunded invoice payments.
	 *
	 * @return array
	 */
	public function get_data() {
		// Bail if there are errors.
		if ( ! empty( $this->error->get_error_messages() ) ) {
			return array();
		}

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		$data = array();

		$indexed_invoices = $this->get_indexed_invoices();

		if ( ! empty( $indexed_invoices ) ) {
			$qbo_invoices = $this->get_qbo_invoices( $indexed_invoices );

			if ( is_wp_error( $qbo_invoices ) ) {
				$this->error = $this->merge_errors( $this->error, $qbo_invoices );

				return array();
			}

			$qbo_payments = $this->get_qbo_payments( $indexed_invoices );

			if ( is_wp_error( $qbo_payments ) ) {
				$this->error = $this->merge_errors( $this->error, $qbo_payments );

				return array();
			}

			$data = array_merge( $qbo_invoices, $qbo_payments );
		}

		$data = $this->filter_data_fields( $data );
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Compile the report data into results.
	 *
	 * @param array $data The data to compile.
	 *
	 * @return array
	 */
	public function compile_report_data( array $data ) {
		$invoices = $this->filter_transactions_by_type( $data, 'Invoice' );
		$payments = $this->filter_transactions_by_type( $data, 'Payment' );

		$compiled_data = array(
			'invoices' => $this->parse_transaction_stats( $invoices ),
			'payments' => $this->parse_transaction_stats( $payments ),
		);

		return $compiled_data;
	}

	/**
	 * Get invoices from the WordCamp database that that have a corresponding ID in QBO.
	 *
	 * Limit the returned invoices to a specific WordCamp if the `wordcamp_id` property has been set.
	 *
	 * @return array
	 */
	protected function get_indexed_invoices( array $ids = array() ) {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table_name = self::get_index_table_name();

		$where_clause = array();
		$where_values = array();
		$where        = '';

		// Invoices that don't have a corresponding entity in QBO yet have a `qbo_invoice_id` value of 0.
		$where_clause[]  = "qbo_invoice_id != 0";

		if ( $this->wordcamp_site_id ) {
			$where_clause[] = "blog_id = %d";
			$where_values[] = $this->wordcamp_site_id;
		} else {
			$excluded_ids = implode( ',', array_map( 'absint', Reports\get_excluded_site_ids() ) );
			$where_clause[] = "blog_id NOT IN ( $excluded_ids )";
		}

		if ( ! empty( $where_clause ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clause );
		}

		$sql = "
			SELECT qbo_invoice_id, blog_id, invoice_id, wordcamp_name, invoice_title, sponsor_name
			FROM $table_name
		" . $where;

		$query   = $wpdb->prepare( $sql, $where_values );
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $results ) ) {
			// Key the invoices array with the `qbo_invoice_id` field.
			$results = array_combine(
				wp_list_pluck( $results, 'qbo_invoice_id' ),
				$results
			);
		}

		return $results;
	}

	/**
	 * Get all the invoices created in QBO within the given date range.
	 *
	 * @param array $indexed_invoices Relevant invoices that are indexed in the WordCamp system.
	 *
	 * @return array|\WP_Error An array of invoices or an error object.
	 */
	protected function get_qbo_invoices( array $indexed_invoices ) {
		$qbo = new Utilities\QBO_Client();

		$invoices = $qbo->get_transactions_by_date( 'Invoice', $this->start_date, $this->end_date );

		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}

		$indexed_invoice_ids = array_keys( $indexed_invoices );

		// Filter out invoices that aren't in the index, or aren't for the specified WordCamp.
		$invoices = array_filter( $invoices, function( $invoice ) use ( $indexed_invoice_ids ) {
			if ( in_array( absint( $invoice['Id'] ), $indexed_invoice_ids, true ) ) {
				return true;
			}

			return false;
		} );

		// Normalize data keys.
		$normalized_invoices = array();

		foreach ( $invoices as $invoice ) {
			$normalized_invoices[] = array(
				'date'          => $invoice['TxnDate'],
				'type'          => 'Invoice',
				'invoice_id'    => $invoice['Id'],
				'wordcamp_name' => $indexed_invoices[ $invoice['Id'] ]['wordcamp_name'],
				'sponsor_name'  => $indexed_invoices[ $invoice['Id'] ]['sponsor_name'],
				'invoice_title' => $indexed_invoices[ $invoice['Id'] ]['invoice_title'],
				'currency'      => $invoice['CurrencyRef']['value'],
				'amount'        => $invoice['TotalAmt'],
			);
		}

		return $normalized_invoices;
	}

	/**
	 * Get all the payment transactions created in QBO within the given date range.
	 *
	 * @param array $indexed_invoices Relevant invoices that are indexed in the WordCamp system.
	 *
	 * @return array|\WP_Error An array of payments or an error object.
	 */
	protected function get_qbo_payments( array $indexed_invoices ) {
		$qbo = new Utilities\QBO_Client();

		$payments = $qbo->get_transactions_by_date( 'Payment', $this->start_date, $this->end_date );

		if ( is_wp_error( $payments ) ) {
			return $payments;
		}

		$indexed_invoice_ids = array_keys( $indexed_invoices );

		// Isolate the ID of the invoice each payment is for.
		array_walk( $payments, function( &$payment ) use ( $indexed_invoice_ids ) {
			$payment['invoice_id'] = 0;

			if ( isset( $payment['Line'] ) ) {
				foreach ( $payment['Line'] as $line ) {
					if ( ! isset( $line['LinkedTxn'] ) ) {
						continue;
					}

					foreach ( $line['LinkedTxn'] as $txn ) {
						if ( 'Invoice' === $txn['TxnType'] && in_array( absint( $txn['TxnId'] ), $indexed_invoice_ids, true ) ) {
							$payment['invoice_id'] = absint( $txn['TxnId'] );
							break 2;
						}
					}
				}

				unset( $payment['Line'] );
			}
		} );

		// Filter out payments that aren't for relevant invoices.
		$payments = array_filter( $payments, function ( $payment ) {
			if ( 0 !== $payment['invoice_id'] ) {
				return true;
			}

			return false;
		} );

		// Normalize data keys.
		$normalized_payments = array();

		foreach ( $payments as $payment ) {
			$normalized_payments[] = array(
				'date'          => $payment['TxnDate'],
				'type'          => 'Payment',
				'invoice_id'    => $payment['invoice_id'],
				'wordcamp_name' => $indexed_invoices[ $payment['invoice_id'] ]['wordcamp_name'],
				'sponsor_name'  => $indexed_invoices[ $payment['invoice_id'] ]['sponsor_name'],
				'invoice_title' => $indexed_invoices[ $payment['invoice_id'] ]['invoice_title'],
				'currency'      => $payment['CurrencyRef']['value'],
				'amount'        => $payment['TotalAmt'],
			);
		}

		return $normalized_payments;
	}

	/**
	 * Out of an array of transactions, generate an array of only one type of transaction.
	 *
	 * @param array  $transactions The transactions to filter.
	 * @param string $type         The type to filter for.
	 *
	 * @return array
	 */
	protected function filter_transactions_by_type( array $transactions, $type ) {
		return array_filter( $transactions, function( $transaction ) use ( $type ) {
			if ( $type === $transaction['type'] ) {
				return true;
			}

			return false;
		} );
	}

	/**
	 * Gather statistics about a given collection of transactions.
	 *
	 * @param array $transactions A list of invoice or payment entities from QBO.
	 *
	 * @return array
	 */
	protected function parse_transaction_stats( array $transactions ) {
		$total_count = count( $transactions );

		$amount_by_currency = array();

		foreach ( $transactions as $transaction ) {
			$currency = $transaction['currency'];
			$amount   = $transaction['amount'];

			if ( ! isset( $amount_by_currency[ $currency ] ) ) {
				$amount_by_currency[ $currency ] = 0;
			}

			$amount_by_currency[ $currency ] += $amount;
		}

		ksort( $amount_by_currency );

		$converted_amounts = array();

		foreach ( $amount_by_currency as $currency => $amount ) {
			if ( 'USD' === $currency ) {
				$converted_amounts[ $currency ] = $amount;
			} else {
				$converted_amounts[ $currency ] = 0;

				$conversion = $this->xrt->convert( $amount, $currency, $this->end_date->format( 'Y-m-d' ) );

				if ( is_wp_error( $conversion ) ) {
					// Unsupported currencies are ok, but other errors should be surfaced.
					if ( 'unknown_currency' !== $conversion->get_error_code() ) {
						$this->merge_errors( $this->error, $conversion );
					}
				} else {
					$converted_amounts[ $currency ] = $conversion->USD;
				}
			}
		}

		$total_amount_converted = array_reduce( $converted_amounts, function( $carry, $item ) {
			return $carry + floatval( $item );
		}, 0 );

		return array(
			'total_count'            => $total_count,
			'amount_by_currency'     => $amount_by_currency,
			'converted_amounts'      => $converted_amounts,
			'total_amount_converted' => $total_amount_converted,
		);
	}

	/**
	 * The name of the table containing an index of all sponsor invoices in the network.
	 *
	 * Wrapper method to help minimize coupling with the WordCamp Payments Network plugin.
	 *
	 * If this needs to be used outside of this class, move it to utilities.php.
	 *
	 * @return string
	 */
	protected static function get_index_table_name() {
		// Ensure the needed file is loaded.
		$wordcamp_payments_network_path = trailingslashit( str_replace( 'wordcamp-reports', 'wordcamp-payments-network', Reports\PLUGIN_DIR ) );
		require_once $wordcamp_payments_network_path . 'includes/sponsor-invoices-dashboard.php';

		return WCBD_Sponsor_Invoices\get_index_table_name();
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$now  = new \DateTime();
		$data = $this->compile_report_data( $this->get_data() );

		$start_date    = $this->start_date;
		$end_date      = $this->end_date;
		$xrt_date      = ( $end_date > $now ) ? $now : $end_date;
		$wordcamp_name = ( $this->wordcamp_site_id ) ? get_wordcamp_name( $this->wordcamp_site_id ) : '';
		$invoices      = $data['invoices'];
		$payments      = $data['payments'];

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include Reports\get_views_dir_path() . 'html/sponsor-invoices.php';
		}
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$wordcamp_id = filter_input( INPUT_POST, 'wordcamp-id' );
		$refresh     = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Show results' === $action
		     && wp_verify_nonce( $nonce, 'run-report' )
		     && current_user_can( 'manage_network' )
		) {
			$options = array(
				'earliest_start' => new \DateTime( '2016-01-01' ), // No invoices in QBO before 2016.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $wordcamp_id, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}

		include Reports\get_views_dir_path() . 'report/sponsor-invoices.php';
	}

	/**
	 * Export the report data to a file.
	 *
	 * @return void
	 */
	public static function export_to_file() {
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$wordcamp_id = filter_input( INPUT_POST, 'wordcamp-id' );
		$refresh     = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Export CSV' !== $action ) {
			return;
		}

		if ( wp_verify_nonce( $nonce, 'run-report' ) && current_user_can( 'manage_network' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2016-01-01' ), // No invoices in QBO before 2016.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $wordcamp_id, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}

			$filename = array( $report::$name );
			if ( $report->wordcamp_site_id ) {
				$filename[] = get_wordcamp_name( $report->wordcamp_site_id );
			}
			$filename[] = $report->start_date->format( 'Y-m-d' );
			$filename[] = $report->end_date->format( 'Y-m-d' );

			$headers = array( 'Date', 'Type', 'QBO Invoice ID', 'WordCamp', 'Sponsor', 'Invoice Title', 'Currency', 'Amount' );

			$data = $report->get_data();

			$exporter = new Reports\Export_CSV( array(
				'filename' => $filename,
				'headers'  => $headers,
				'data'     => $data,
			) );

			if ( ! empty( $report->error->get_error_messages() ) ) {
				$exporter->error = $report->merge_errors( $report->error, $exporter->error );
			}

			$exporter->emit_file();
		} // End if().
	}

	/**
	 * Determine whether to render the public report form.
	 *
	 * This shortcode is limited to use on pages.
	 *
	 * @return string HTML content to display shortcode.
	 */
	public static function handle_shortcode() {
		$html = '';

		if ( 'page' === get_post_type() ) {
			ob_start();
			self::render_public_page();
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Render the page for this report on the front end.
	 *
	 * @return void
	 */
	public static function render_public_page() {
		// Apparently 'year' is a reserved URL parameter on the front end, so we prepend 'report-'.
		$year        = filter_input( INPUT_GET, 'report-year', FILTER_VALIDATE_INT );
		$period      = filter_input( INPUT_GET, 'period' );
		$wordcamp_id = filter_input( INPUT_GET, 'wordcamp-id' );
		$action      = filter_input( INPUT_GET, 'action' );

		$years    = self::year_array( absint( date( 'Y' ) ), 2016 );
		$quarters = self::quarter_array();
		$months   = self::month_array();

		if ( ! $year ) {
			$year = absint( date( 'Y' ) );
		}

		if ( ! $period ) {
			$period = absint( date( 'm' ) );
		}

		$report = null;

		if ( 'Show results' === $action ) {
			$range = self::convert_time_period_to_date_range( $year, $period );

			$options = array(
				'earliest_start' => new \DateTime( '2016-01-01' ), // No invoices in QBO before 2016.
			);

			$report = new self( $range['start_date'], $range['end_date'], $wordcamp_id, $options );
		}

		include Reports\get_views_dir_path() . 'public/sponsor-invoices.php';
	}
}
