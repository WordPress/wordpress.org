<?php
/**
 * Payment Activity.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use WordCamp\Reports;
use WordCamp\Utilities;
use function WordCamp\Reports\Validation\{validate_wordcamp_id};
use WordCamp\Budgets_Dashboard\Reimbursement_Requests;

/**
 * Class Payment_Activity
 *
 * @package WordCamp\Reports\Report
 */
class Payment_Activity extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Payment Activity';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'payment-activity';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Vendor payments and reimbursement requests.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Retrieve index entries for vendor payments and reimbursement requests that have a created and/or paid date that fall within the specified date range.</li>
			<li>Query each WordCamp site from the index results and retrieve additional data for each matched payment.</li>
			<li>Parse the activity log for each payment and determine (or guess) if/when the payment was approved, if/when it was paid, and if/when it was cancelled or it failed.</li>
			<li>Filter out payments don't have an approved, paid, or failed date within the specified date range.</li>
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
	public static $shortcode_tag = 'payment_activity_report';

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
		'blog_id'            => 0,
		'post_id'            => 0,
		'post_type'          => '',
		'currency'           => '',
		'amount'             => 0,
		'status'             => '',
		'timestamp_approved' => 0,
		'timestamp_paid'     => 0,
		'timestamp_failed'   => 0,
	);

	/**
	 * Payment_Activity constructor.
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

		$indexed_payments = $this->get_indexed_payments();
		$payments_by_site = array();

		foreach ( $indexed_payments as $index ) {
			if ( ! isset( $payments_by_site[ $index['blog_id'] ] ) ) {
				$payments_by_site[ $index['blog_id'] ] = array();
			}

			$payments_by_site[ $index['blog_id'] ][] = $index['post_id'];
		}

		$payment_posts = array();

		foreach ( $payments_by_site as $blog_id => $post_ids ) {
			$payment_posts = array_merge( $payment_posts, $this->get_payment_posts( $blog_id, $post_ids ) );
		}

		$payment_posts = array_map( array( $this, 'parse_payment_post_log' ), $payment_posts );

		$data = array_filter( $payment_posts, function( $payment ) {
			if ( ! $this->timestamp_within_date_range( $payment['timestamp_approved'] )
			     && ! $this->timestamp_within_date_range( $payment['timestamp_paid'] )
			     && ! $this->timestamp_within_date_range( $payment['timestamp_failed'] )
			) {
				return false;
			}

			return true;
		} );

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
		$compiled_data = $this->derive_totals_from_payment_events( $data );

		return $compiled_data;
	}

	/**
	 * Retrieve Vendor Payments and Reimbursement Requests from their respective index database tables.
	 *
	 * @return array
	 */
	protected function get_indexed_payments() {
		// Ensure all the needed files are loaded.
		$wordcamp_payments_network_path = trailingslashit( str_replace( 'wordcamp-reports', 'wordcamp-payments-network', Reports\PLUGIN_DIR ) );
		require_once $wordcamp_payments_network_path . 'includes/payment-requests-dashboard.php';
		require_once $wordcamp_payments_network_path . 'includes/reimbursement-requests-dashboard.php';

		/** @global \wpdb $wpdb */
		global $wpdb;

		$payments_table       = \Payment_Requests_Dashboard::get_table_name();
		$reimbursements_table = Reimbursement_Requests\get_index_table_name();

		if ( $this->wordcamp_site_id ) {
			$extra_where = sprintf(
				' AND blog_id = %d',
				intval( $this->wordcamp_site_id )
			);
		} else {
			$excluded_ids = implode( ',', array_map( 'absint', Reports\get_excluded_site_ids() ) );
			$extra_where = " AND blog_id NOT IN ( $excluded_ids )";
		}

		$index_query = $wpdb->prepare( "
			(
				SELECT blog_id, post_id
				FROM $payments_table
				WHERE created <= %d
					AND ( paid = 0 OR paid >= %d )
					$extra_where
			) UNION (
				SELECT blog_id, request_id AS post_id
				FROM $reimbursements_table
				WHERE date_requested <= %d
					AND ( date_paid = 0 OR date_paid >= %d )
					$extra_where
			)",
			$this->end_date->getTimestamp(),
			$this->start_date->getTimestamp(),
			$this->end_date->getTimestamp(),
			$this->start_date->getTimestamp()
		);

		return $wpdb->get_results( $index_query, ARRAY_A );
	}

	/**
	 * Get payment posts from a particular site.
	 *
	 * @param int   $blog_id  The ID of the site.
	 * @param array $post_ids The list of post IDs to get.
	 *
	 * @return array
	 */
	protected function get_payment_posts( $blog_id, array $post_ids ) {
		$payment_posts = array();
		$post_types    = array( 'wcp_payment_request', 'wcb_reimbursement' );

		switch_to_blog( $blog_id );

		$query_args = array(
			'post_type'           => $post_types,
			'post_status'         => 'all',
			'post__in'            => $post_ids,
			'nopaging'            => true,
		);

		$raw_posts = get_posts( $query_args );

		foreach ( $raw_posts as $raw_post ) {
			switch ( $raw_post->post_type ) {
				case 'wcp_payment_request' :
					$currency = $raw_post->_camppayments_currency;
					$amount   = $raw_post->_camppayments_payment_amount;
					break;

				case 'wcb_reimbursement' :
					$currency = get_post_meta( $raw_post->ID, '_wcbrr_currency', true );
					$amount   = Reimbursement_Requests\get_amount( $raw_post->ID );
					break;

				default :
					$currency = '';
					$amount   = '';
					break;
			}

			$payment_posts[] = array(
				'blog_id'   => $blog_id,
				'post_id'   => $raw_post->ID,
				'post_type' => $raw_post->post_type,
				'currency'  => $currency,
				'amount'    => $amount,
				'status'    => $raw_post->post_status,
				'log'       => json_decode( $raw_post->_wcp_log, true ),
			);

			clean_post_cache( $raw_post );
		}

		restore_current_blog();

		return $payment_posts;
	}

	/**
	 * Determine the timestamps for particular payment post events from the post's log.
	 *
	 * This walks through the log array looking for specific events. If it finds them, it adds the event
	 * timestamp to a new key in the payment post array. At the end, it removes the log from the array.
	 *
	 * @param array $payment_post The array of data for a payment post.
	 *
	 * @return array
	 */
	protected function parse_payment_post_log( array $payment_post ) {
		$parsed_post = wp_parse_args( array(
			'timestamp_approved' => 0,
			'timestamp_paid'     => 0,
			'timestamp_failed'   => 0,
		), $payment_post );

		if ( ! isset( $parsed_post['log'] ) ) {
			return $parsed_post;
		}

		usort( $parsed_post['log'], function( $a, $b ) {
			// Sort log entries in chronological order.
			if ( $a['timestamp'] === $b['timestamp'] ) {
				return 0;
			}

			return ( $a['timestamp'] > $b['timestamp'] ) ? 1 : -1;
		} );

		foreach ( $parsed_post['log'] as $index => $entry ) {
			if ( \BLOG_ID_CURRENT_SITE === $parsed_post['blog_id'] && 0 === $index ) {
				// Payments on central.wordcamp.org have a different workflow.
				$parsed_post['timestamp_approved'] = $entry['timestamp'];
			} elseif ( false !== stripos( $entry['message'], 'Request approved' ) ) {
				$parsed_post['timestamp_approved'] = $entry['timestamp'];
			} elseif ( false !== stripos( $entry['message'], 'Pending Payment' ) ) {
				$parsed_post['timestamp_paid'] = $entry['timestamp'];
			} elseif ( false !== stripos( $entry['message'], 'Marked as paid' ) && ! $parsed_post['timestamp_paid'] ) {
				$parsed_post['timestamp_paid'] = $entry['timestamp'];
			}
		}

		if ( $parsed_post['timestamp_paid'] && ! $parsed_post['timestamp_approved'] ) {
			// If we didn't find an approved timestamp, but we did find a paid timestamp, use the same for both.
			$parsed_post['timestamp_approved'] = $parsed_post['timestamp_paid'];
		}

		// There isn't an explicit log entry for failed or cancelled payments, so we have to look at the post status.
		if ( in_array( $parsed_post['status'], array( 'wcb-failed', 'wcb-cancelled' ), true ) ) {
			$parsed_post['timestamp_paid'] = 0;

			// Assume the last log entry is when the payment was marked failed/cancelled.
			$last_log = array_slice( $parsed_post['log'], -1 )[0];
			$parsed_post['timestamp_failed'] = $last_log['timestamp'];
		}

		unset( $parsed_post['log'] );

		return $parsed_post;
	}

	/**
	 * Aggregate the number and payment amounts of a group of Vendor Payments and Reimbursement Requests.
	 *
	 * @param array $payment_posts The group of posts to aggregate.
	 *
	 * @return array
	 */
	protected function derive_totals_from_payment_events( array $payment_posts ) {
		$data = array(
			'vendor_payment_count'              => 0,
			'reimbursement_count'               => 0,
			'vendor_payment_amount_by_currency' => array(),
			'reimbursement_amount_by_currency'  => array(),
			'total_amount_by_currency'          => array(),
			'converted_amounts'                 => array(),
			'total_amount_converted'            => 0,
		);

		$data_groups = array(
			'requests' => $data,
			'payments' => $data,
			'failures' => $data,
		);

		$currencies      = array();
		$failed_statuses = array( 'wcb-failed', 'wcb-cancelled' );

		foreach ( $payment_posts as $payment ) {
			if ( ! isset( $payment['currency'] ) || ! $payment['currency'] ) {
				continue;
			}

			if ( ! in_array( $payment['currency'], $currencies, true ) ) {
				$data_groups['requests']['vendor_payment_amount_by_currency'][ $payment['currency'] ] = 0;
				$data_groups['requests']['reimbursement_amount_by_currency'][ $payment['currency'] ]  = 0;
				$data_groups['requests']['total_amount_by_currency'][ $payment['currency'] ]          = 0;
				$data_groups['payments']['vendor_payment_amount_by_currency'][ $payment['currency'] ] = 0;
				$data_groups['payments']['reimbursement_amount_by_currency'][ $payment['currency'] ]  = 0;
				$data_groups['payments']['total_amount_by_currency'][ $payment['currency'] ]          = 0;
				$data_groups['failures']['vendor_payment_amount_by_currency'][ $payment['currency'] ] = 0;
				$data_groups['failures']['reimbursement_amount_by_currency'][ $payment['currency'] ]  = 0;
				$data_groups['failures']['total_amount_by_currency'][ $payment['currency'] ]          = 0;
				$currencies[]                                                                         = $payment['currency'];
			}

			switch ( $payment['post_type'] ) {
				case 'wcp_payment_request' :
					if ( $this->timestamp_within_date_range( $payment['timestamp_approved'] ) ) {
						$data_groups['requests']['vendor_payment_count'] ++;
						$data_groups['requests']['vendor_payment_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
						$data_groups['requests']['total_amount_by_currency'][ $payment['currency'] ]          += floatval( $payment['amount'] );
					}
					if ( $this->timestamp_within_date_range( $payment['timestamp_paid'] ) ) {
						$data_groups['payments']['vendor_payment_count'] ++;
						$data_groups['payments']['vendor_payment_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
						$data_groups['payments']['total_amount_by_currency'][ $payment['currency'] ]          += floatval( $payment['amount'] );
					} elseif ( $this->timestamp_within_date_range( $payment['timestamp_failed'] ) ) {
						$data_groups['failures']['vendor_payment_count'] ++;
						$data_groups['failures']['vendor_payment_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
						$data_groups['failures']['total_amount_by_currency'][ $payment['currency'] ]          += floatval( $payment['amount'] );
					}
					break;

				case 'wcb_reimbursement' :
					if ( $this->timestamp_within_date_range( $payment['timestamp_approved'] ) ) {
						$data_groups['requests']['reimbursement_count'] ++;
						$data_groups['requests']['reimbursement_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
						$data_groups['requests']['total_amount_by_currency'][ $payment['currency'] ]         += floatval( $payment['amount'] );
					}
					if ( $this->timestamp_within_date_range( $payment['timestamp_paid'] ) ) {
						$data_groups['payments']['reimbursement_count'] ++;
						$data_groups['payments']['reimbursement_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
						$data_groups['payments']['total_amount_by_currency'][ $payment['currency'] ]         += floatval( $payment['amount'] );
					} elseif ( $this->timestamp_within_date_range( $payment['timestamp_failed'] ) ) {
						$data_groups['failures']['reimbursement_count'] ++;
						$data_groups['failures']['reimbursement_amount_by_currency'][ $payment['currency'] ] += floatval( $payment['amount'] );
						$data_groups['failures']['total_amount_by_currency'][ $payment['currency'] ]         += floatval( $payment['amount'] );
					}
					break;
			}
		} // End foreach().

		foreach ( $data_groups as &$group ) {
			ksort( $group['vendor_payment_amount_by_currency'] );
			ksort( $group['reimbursement_amount_by_currency'] );
			ksort( $group['total_amount_by_currency'] );

			foreach ( $group['total_amount_by_currency'] as $currency => $amount ) {
				if ( 'USD' === $currency ) {
					$group['converted_amounts'][ $currency ] = $amount;
				} else {
					$group['converted_amounts'][ $currency ] = 0;

					$conversion = $this->xrt->convert( $amount, $currency, $this->end_date->format( 'Y-m-d' ) );

					if ( is_wp_error( $conversion ) ) {
						// Unsupported currencies are ok, but other errors should be surfaced.
						if ( 'unknown_currency' !== $conversion->get_error_code() ) {
							$this->merge_errors( $this->error, $conversion );
						}
					} else {
						$group['converted_amounts'][ $currency ] = $conversion->USD;
					}
				}
			}

			$group['total_amount_converted'] = array_reduce( $group['converted_amounts'], function( $carry, $item ) {
				return $carry + floatval( $item );
			}, 0 );
		}

		return $data_groups;
	}

	/**
	 * Check if a given Unix timestamp is within the date range set in the report.
	 *
	 * @param int $timestamp The Unix timestamp to test.
	 *
	 * @return bool True if within the date range.
	 */
	protected function timestamp_within_date_range( $timestamp ) {
		$date = new \DateTime();
		$date->setTimestamp( $timestamp );

		if ( $date >= $this->start_date && $date <= $this->end_date ) {
			return true;
		}

		return false;
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$now  = new \DateTime();
		$data = $this->compile_report_data( $this->get_data() );

		$start_date = $this->start_date;
		$end_date   = $this->end_date;
		$xrt_date      = ( $end_date > $now ) ? $now : $end_date;
		$wordcamp_name = ( $this->wordcamp_site_id ) ? get_wordcamp_name( $this->wordcamp_site_id ) : '';
		$requests      = $data['requests'];
		$payments      = $data['payments'];
		$failures      = $data['failures'];

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include Reports\get_views_dir_path() . 'html/payment-activity.php';
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
				'earliest_start' => new \DateTime( '2015-01-01' ), // No indexed payment data before 2015.
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

		include Reports\get_views_dir_path() . 'report/payment-activity.php';
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
				'earliest_start' => new \DateTime( '2015-01-01' ), // No indexed payment data before 2015.
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

			$headers = array( 'Blog ID', 'Payment ID', 'Payment Type', 'Currency', 'Amount', 'Status', 'Date Approved', 'Date Paid', 'Date Failed/Cancelled' );

			$data = $report->get_data();

			array_walk( $data, function( &$payment ) {
				$payment['post_type']          = get_post_type_labels( get_post_type_object( $payment['post_type'] ) )->singular_name;
				$payment['timestamp_approved'] = ( $payment['timestamp_approved'] > 0 ) ? date( 'Y-m-d', $payment['timestamp_approved'] ) : '';
				$payment['timestamp_paid']     = ( $payment['timestamp_paid'] > 0 ) ? date( 'Y-m-d', $payment['timestamp_paid'] ) : '';
				$payment['timestamp_failed']   = ( $payment['timestamp_failed'] > 0 ) ? date( 'Y-m-d', $payment['timestamp_failed'] ) : '';
			} );

			$exporter = new Utilities\Export_CSV( array(
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

		$years    = self::year_array( absint( date( 'Y' ) ), 2015 );
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
				'earliest_start' => new \DateTime( '2015-01-01' ), // No indexed payment data before 2015.
			);

			$report = new self( $range['start_date'], $range['end_date'], $wordcamp_id, $options );
		}

		include Reports\get_views_dir_path() . 'public/payment-activity.php';
	}
}
