<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

/**
 * Class QBO_Client
 *
 * This is a general purpose client, whereas the one in `plugins/wordcamp-qbo-client` is specific to the WordCamp
 * Payments plugin. Eventually, we should probably merge the two into a single general purpose client, rather
 * than having multiple.
 */
class QBO_Client {
	/**
	 * @var bool True if in Sandbox mode.
	 */
	protected $sandbox_mode = true;

	/**
	 * @var string The base URL for the API endpoints.
	 */
	protected $api_base = 'https://sandbox-quickbooks.api.intuit.com';

	/**
	 * @var int Number of seconds to wait during a request.
	 */
	protected $request_timeout = 45; //seconds

	/**
	 * @var \WordCamp_QBO_OAuth_Client|null OAuth client from the WordCamp QBO plugin.
	 */
	protected $oauth = null;

	/**
	 * @var array QBO options configured by the environment.
	 */
	protected $qbo_options = array();

	/**
	 * @var array OAuth options that were set by linking the OAuth client to the QBO account.
	 */
	protected $oauth_options = array();

	/**
	 * @var \WP_Error|null Container for errors.
	 */
	public $error = null;

	/**
	 * QBO_Client constructor.
	 */
	public function __construct() {
		$this->error = new \WP_Error();

		if ( defined( 'WORDCAMP_ENVIRONMENT' ) && 'production' === WORDCAMP_ENVIRONMENT ) {
			$this->sandbox_mode = false;
		}

		if ( ! $this->sandbox_mode ) {
			$this->api_base = 'https://quickbooks.api.intuit.com';
		}

		$this->get_oauth();
	}

	/**
	 * Get an instance of the OAuth client from the WordCamp.org QBO Integration plugin.
	 *
	 * @return \WordCamp_QBO_OAuth_Client
	 */
	protected function get_oauth() {
		if ( ! is_null( $this->oauth ) ) {
			return $this->oauth;
		}

		$qbo_options   = $this->get_qbo_options();
		$oauth_options = $this->get_oauth_options();

		require_once( WP_PLUGIN_DIR . '/wordcamp-qbo/class-wordcamp-qbo-oauth-client.php' );

		$this->oauth = new \WordCamp_QBO_OAuth_Client( $qbo_options['consumer_key'], $qbo_options['consumer_secret'] );

		$this->oauth->set_token( $oauth_options['auth']['oauth_token'], $oauth_options['auth']['oauth_token_secret'] );

		return $this->oauth;
	}

	/**
	 * Get the QBO options configured by the environment.
	 *
	 * The options should be set using the `wordcamp_qbo_options` filter.
	 *
	 * @return array
	 */
	protected function get_qbo_options() {
		if ( ! empty( $this->qbo_options ) ) {
			return $this->qbo_options;
		}

		$defaults = array(
			'app_token'       => '',
			'consumer_key'    => '',
			'consumer_secret' => '',
			'hmac_key'        => '',
		);

		$this->qbo_options = wp_parse_args( apply_filters( 'wordcamp_qbo_options', array() ), $defaults );

		if ( ! empty( array_intersect_assoc( $defaults, $this->qbo_options ) ) ) {
			$this->error->add(
				'qbo_options_not_set',
				'The QBO options are not correctly set.'
			);
		}

		return $this->qbo_options;
	}

	/**
	 * Get the OAuth options set when the client was linked to the QBO account.
	 *
	 * @return array
	 */
	protected function get_oauth_options() {
		if ( ! empty( $this->oauth_options ) ) {
			return $this->oauth_options;
		}

		$defaults = array(
			'auth' => array(),
		);

		$auth_defaults = array(
			'oauth_token'        => '',
			'oauth_token_secret' => '',
			'realmId'            => '',
			'name'               => '',
			'timestamp'          => 0,
		);

		$this->oauth_options = wp_parse_args( get_option( 'wordcamp-qbo', array() ), $defaults );
		$this->oauth_options['auth'] = wp_parse_args( $this->oauth_options['auth'], $auth_defaults );

		if ( ! empty( array_intersect_assoc( $auth_defaults, $this->oauth_options['auth'] ) ) ) {
			$this->error->add(
				'qbo_account_not_linked',
				'The QBO client has not been linked to the WPCS QBO account.'
			);
		}

		return $this->oauth_options;
	}

	/**
	 * Send a GET request to the QBO API.
	 *
	 * @param string $url  The complete URL of the API endpoint.
	 * @param array  $args Optional. Additional arguments for the API request.
	 *
	 * @return array|mixed|object
	 */
	protected function send_get_request( $url, $args = array() ) {
		$encoded_args = array_map( 'rawurlencode', $args );
		$request_url  = add_query_arg( $encoded_args, $url );

		$auth_header  = $this->oauth->get_oauth_header( 'GET', $url, $args );
		$request_args = array(
			'timeout' => $this->request_timeout,
			'headers' => array(
				'Authorization' => $auth_header,
				'Accept'        => 'application/json',
			),
		);

		$response = wcorg_redundant_remote_get( $request_url, $request_args );

		if ( is_wp_error( $response ) ) {
			foreach ( $response->get_error_codes() as $code ) {
				foreach ( $response->get_error_messages( $code ) as $message ) {
					$this->error->add( $code, $message );
				}
			}

			return array();
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->error->add(
				'invalid_http_code',
				sprintf(
					'Invalid HTTP response code: %d',
					wp_remote_retrieve_response_code( $response )
				)
			);

			return array();
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * @param $response
	 *
	 * @return bool
	 */
	protected function validate_get_response( $response ) {
		if ( ! isset( $response['QueryResponse'] ) ) {
			$this->error->add(
				'empty_response',
				'The GET request returned an empty response.'
			);

			return false;
		}

		if ( isset( $response['QueryResponse']['Fault'] ) ) {
			foreach ( $response['QueryResponse']['Fault'] as $error ) {
				$this->error->add(
					'response_fault',
					esc_html( $error['code'] . ': ' . $error['Message'] )
				);
			}

			return false;
		}

		return true;
	}

	/**
	 * Retrieve transactions from QBO.
	 *
	 * Supports making multiple requests if the results are paginated.
	 *
	 * @param string $type         The type of transactions to retrieve. Possible values: 'invoice', 'payment'.
	 * @param array  $filter       Optional. One or more WHERE clauses that will be joined together with AND. Specific
	 *                             values should be represented by placeholder tokens supported by $wpdb->prepare().
	 * @param array  $filter_input Optional. The values that will replace the placeholder tokens.
	 *
	 * @return array|\WP_Error
	 */
	protected function get_transactions( $type, array $filter = array(), array $filter_input = array() ) {
		$allowed_types = array(
			// Type => Fields to select.
			'Invoice' => 'Id, TxnDate, CurrencyRef, LinkedTxn, TotalAmt, Balance',
			'Payment' => 'Id, TxnDate, CurrencyRef, Line, TotalAmt, UnappliedAmt',
		);

		if ( ! array_key_exists( $type, $allowed_types ) ) {
			$this->error->add(
				'invalid_transaction_type',
				sprintf(
					'%s is not a valid transaction type.',
					esc_html( $type )
				)
			);
		}

		if ( ! empty( $this->error->get_error_messages() ) ) {
			return $this->error;
		}

		/** @var \wpdb $wpdb */
		global $wpdb;

		$url = sprintf(
			'%s/v3/company/%d/query',
			$this->api_base,
			rawurlencode( $this->oauth_options['auth']['realmId'] )
		);

		// Build query elements.
		$select_count  = 'SELECT count(*)';
		$select_fields = 'SELECT ' . $allowed_types[ $type ];
		$from          = 'FROM ' . $type;
		$where         = '';

		if ( ! empty( $filter ) ) {
			$where = 'WHERE ' . implode( ' AND ', $filter );
		}

		// First send an initial request to get the total number of items available.
		$count_query = $wpdb->prepare(
			"$select_count $from $where",
			$filter_input
		);

		$response = $this->send_get_request( $url, array( 'query' => $count_query ) );

		if ( ! $this->validate_get_response( $response ) ) {
			return $this->error;
		}

		// Then send paginated requests until all of the items have been retrieved.
		// See https://developer.intuit.com/docs/0100_quickbooks_online/0300_references/0000_programming_guide/0050_data_queries#/Maximum_number_of_entities_in_a_response
		$data           = array();
		$max_results    = 1000;
		$pages          = ceil( $response['QueryResponse']['totalCount'] / $max_results );
		$page           = 1;
		$start_position = 1;

		while ( $page <= $pages ) {
			$page_query = $wpdb->prepare(
				"$select_fields $from $where STARTPOSITION $start_position MAXRESULTS $max_results",
				$filter_input
			);

			$response = $this->send_get_request( $url, array( 'query' => $page_query ) );

			if ( ! $this->validate_get_response( $response ) ) {
				return $this->error;
			}

			$data = array_merge( $data, $response['QueryResponse'][ $type ] );

			$page++;
			$start_position += $max_results;
		}

		return $data;
	}

	/**
	 * A wrapper method for retrieving transactions occurring during a specific period of time.
	 *
	 * @param string    $type       The type of transactions to retrieve. Possible values: 'invoice', 'payment'.
	 * @param \DateTime $start_date The beginning of the date range.
	 * @param \DateTime $end_date   The end of the date range.
	 *
	 * @return array|\WP_Error
	 */
	public function get_transactions_by_date( $type, \DateTime $start_date, \DateTime $end_date ) {
		$filter = array(
			'TxnDate >= %s',
			'TxnDate <= %s',
		);

		return $this->get_transactions( $type, $filter, array( $start_date->format( 'Y-m-d' ), $end_date->format( 'Y-m-d' ) ) );
	}

	/**
	 * A wrapper method for retrieving specific transactions based on their IDs.
	 *
	 * @param string $type    The type of transactions to retrieve. Possible values: 'invoice', 'payment'.
	 * @param array  $txn_ids A list of transaction IDs.
	 *
	 * @return array|\WP_Error
	 */
	public function get_transactions_by_id( $type, array $txn_ids ) {
		// IDs are initially cast as integers for validation, and then converted back to strings, because that's what QBO expects.
		$txn_ids             = array_map( 'absint', $txn_ids );
		$txn_id_placeholders = implode( ', ', array_fill( 0, count( $txn_ids ), '%s' ) );

		$filter = array( 'Id IN ( ' . $txn_id_placeholders . ' )' );

		return $this->get_transactions( $type, $filter, $txn_ids );
	}
}
