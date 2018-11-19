<?php
/**
 * WordCamp Payment Methods.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use Exception;
use WordCamp\Reports;
use WordCamp\Utilities;
use function WordCamp\Reports\Validation\{validate_wordcamp_id};

/**
 * Class WordCamp_Payment_Methods
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Payment_Methods extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'WordCamp Payment Methods';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'wordcamp-payment-methods';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'WordCamp ticket sales broken out by payment method.';

	/**
	 * Report methodology.
	 *
	 * @var string
	 */
	public static $methodology = "
		<ol>
			<li>Generate a transaction data set from the Ticket Revenue report, using the specified date range.</li>
			<li>Strip out refund transactions, since we're only interested in the initial purchase.</li>
			<li>Count the number of transactions for each payment method.</li>
			<li>Count the number of transactions for each payment method, for each WordCamp that had transactions during the specified date range.</li>
		</ol>
	";

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'misc';

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
		'timestamp'        => '',
		'blog_id'          => 0,
		'object_id'        => 0,
		'method'           => '',
		'currency'         => '',
		'full_price'       => 0,
		'discounted_price' => 0,
	);

	/**
	 * Ticket_Payment_Methods constructor.
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

		$transactions = $this->get_ticket_transactions();

		// We're not interested in refunds, only the original purchases.
		$purchases = array_filter( $transactions, function( $transaction ) {
			if ( 'Purchase' === $transaction['type'] ) {
				return true;
			}

			return false;
		} );

		// Thus we don't need the `type` property. We need every method field to have a value, though.
		$data = array_map( function( $purchase ) {
			unset( $purchase['type'] );

			if ( ! $purchase['method'] ) {
				$purchase['method'] = 'none';
			}

			return $purchase;
		}, $purchases );

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
		$totals = array_reduce( $data, function( $carry, $item ) {
			$method = $item['method'];

			if ( ! isset( $carry['Total'] ) ) {
				$carry['Total'] = 0;
			}

			if ( ! isset( $carry[ $method ] ) ) {
				$carry[ $method ] = 0;
			}

			$carry[ $method ] ++;
			$carry['Total'] ++;

			return $carry;
		} );

		uksort( $totals, function( $a, $b ) {
			if ( 'Total' === $a ) {
				return 1;
			}

			if ( 'Total' === $b ) {
				return -1;
			}

			return ( $a < $b ) ? -1 : 1;
		} );

		$by_site = array_reduce( $data, function( $carry, $item ) use ( $totals ) {
			$blog_id = $item['blog_id'];
			$method  = $item['method'];

			if ( ! isset( $carry[ $blog_id ] ) ) {
				$carry[ $blog_id ] = array_merge( array_fill_keys( array_keys( $totals ), 0 ), array(
					'name' => get_wordcamp_name( $blog_id ),
				) );
			}

			$carry[ $blog_id ][ $method ] ++;
			$carry[ $blog_id ]['Total'] ++;

			return $carry;
		} );

		usort( $by_site, function( $a, $b ) {
			if ( $a['Total'] === $b['Total'] ) {
				return 0;
			}

			return ( $a['Total'] < $b['Total'] ) ? 1 : -1;
		} );

		return array(
			'method_totals'   => $totals,
			'methods_by_site' => $by_site,
		);
	}

	/**
	 * Get a list of ticket transaction events from the given date range.
	 *
	 * Uses the Ticket Revenue report to get the relevant transaction event data.
	 *
	 * @return array
	 */
	protected function get_ticket_transactions() {
		$ticket_revenue_report = new Ticket_Revenue(
			$this->start_date->format( 'Y-m-d' ),
			$this->end_date->format( 'Y-m-d' ),
			$this->wordcamp_id,
			$this->options
		);

		return $ticket_revenue_report->get_data();
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$data = $this->compile_report_data( $this->get_data() );

		$start_date    = $this->start_date;
		$end_date      = $this->end_date;
		$wordcamp_name = ( $this->wordcamp_site_id ) ? get_wordcamp_name( $this->wordcamp_site_id ) : '';
		$method_totals = $data['method_totals'];
		$site_totals   = $data['methods_by_site'];

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
		} else {
			include Reports\get_views_dir_path() . 'html/wordcamp-payment-methods.php';
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
				'earliest_start' => new \DateTime( '2015-01-01' ), // No indexed CampTix events before 2015.
				'max_interval'   => new \DateInterval( 'P1Y' ), // 1 year. See http://php.net/manual/en/dateinterval.construct.php.
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

		include Reports\get_views_dir_path() . 'report/wordcamp-payment-methods.php';
	}
}
