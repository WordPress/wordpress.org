<?php
/**
 * Plugin Name: WordCamp.org QBO Integration
 */

class WordCamp_QBO {
	const REMOTE_REQUEST_TIMEOUT = 10; // seconds

	private static $app_token;
	private static $consumer_key;
	private static $consumer_secret;
	private static $hmac_key;

	private static $sandbox_mode;
	private static $account;
	private static $api_base_url;
	private static $options;
	private static $categories_map;

	public static function load_options() {
		if ( isset( self::$options ) )
			return self::$options;

		self::$options = wp_parse_args( get_option( 'wordcamp-qbo', array() ), array(
			'auth' => array(),
		) );
	}

	/**
	 * Runs immediately.
	 */
	public static function load() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
	}

	/**
	 * Runs during plugins_loaded.
	 */
	public static function plugins_loaded() {
		self::$sandbox_mode = apply_filters( 'wordcamp_qbo_sandbox_mode', false );

		$init_options = wp_parse_args( apply_filters( 'wordcamp_qbo_options', array() ), array(
			'app_token' => '',
			'consumer_key' => '',
			'consumer_secret' => '',
			'hmac_key' => '',

			'categories_map' => array(),
		) );

		foreach ( $init_options as $key => $value )
			self::$$key = $value;

		// There's no point in doing anything if we don't have the secrets.
		if ( empty( self::$consumer_key ) )
			return;

		self::$api_base_url = sprintf(
			'https://%squickbooks.api.intuit.com',
			 self::$sandbox_mode ? 'sandbox-' : ''
		);

		self::$account = apply_filters( 'wordcamp_qbo_account', array(
			'value' => '61',
			'name'  => 'Checking-JPM',
		) );

		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_filter( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );

		self::maybe_oauth_request();
	}

	/**
	 * Runs during rest_api_init.
	 */
	public static function rest_api_init() {
		register_rest_route( 'wordcamp-qbo/v1', '/expense', array(
			'methods' => 'GET, POST',
			'callback' => array( __CLASS__, 'rest_callback_expense' ),
		) );

		register_rest_route( 'wordcamp-qbo/v1', '/classes', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'rest_callback_classes' ),
		) );

		register_rest_route( 'wordcamp-qbo/v1', '/invoice', array(
			'methods' => 'GET, POST',
			'callback' => array( __CLASS__, 'rest_callback_invoice' ),
		) );

		register_rest_route( 'wordcamp-qbo/v1', '/paid_invoices', array(
			'methods' => 'GET',
			'callback' => array( __CLASS__, 'rest_callback_paid_invoices' ),
		) );
	}

	/**
	 * REST: /expense
	 *
	 * @param WP_REST_Request $request
	 */
	public static function rest_callback_expense( $request ) {
		if ( ! self::_is_valid_request( $request ) )
			return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );

		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$amount = floatval( $request->get_param( 'amount' ) );
		if ( ! $amount )
			return new WP_Error( 'error', 'An amount was not given.' );

		$description = $request->get_param( 'description' );
		if ( empty( $description ) )
			return new WP_Error( 'error', 'The expense description can not be empty.' );

		$category = $request->get_param( 'category' );
		if ( empty( $category ) || ! array_key_exists( $category, self::$categories_map ) )
			return new WP_Error( 'error', 'The category you have picked is invalid.' );

		$date = $request->get_param( 'date' );
		if ( empty( $date ) )
			return new WP_Error( 'error', 'The expense date can not be empty.' );

		$date = absint( $date );

		$class = $request->get_param( 'class' );
		if ( empty( $class ) )
			return new WP_Error( 'error', 'You need to set a class.' );

		$classes = self::_get_classes();
		if ( ! array_key_exists( $class, $classes ) )
			return new WP_Error( 'error', 'Unknown class.' );

		$class = array(
			'value' => $class,
			'name' => $classes[ $class ],
		);

		$payload = array(
			'AccountRef' => self::$account,
			'TxnDate' => gmdate( 'Y-m-d', $date ),
			'PaymentType' => 'Cash',
			'Line' => array(
				array(
					'Id' => 1,
					'Description' => $description,
					'Amount' => $amount,
					'DetailType' => 'AccountBasedExpenseLineDetail',
					'AccountBasedExpenseLineDetail' => array(
						'ClassRef' => $class,
						'AccountRef' => self::$categories_map[ $category ],
					),
				),
			),
		);

		if ( $request->get_param('id') ) {
			$payload['Id'] = absint( $request->get_param('id') );

			$request_url = esc_url_raw( sprintf( '%s/v3/company/%d/purchase/%d',
				self::$api_base_url, self::$options['auth']['realmId'], $payload['Id'] ) );

			$oauth_header = $oauth->get_oauth_header( 'GET', $request_url );
			$response = wp_remote_get( $request_url, array(
				'timeout' => self::REMOTE_REQUEST_TIMEOUT,
				'headers' => array(
					'Authorization' => $oauth_header,
					'Accept' => 'application/json',
				),
			) );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 )
				return new WP_Error( 'error', 'Could not find purchase to update.' );

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! isset( $body['Purchase']['SyncToken'] ) )
				return new WP_Error( 'error', 'Could not decode purchase for update.' );

			$payload['SyncToken'] = $body['Purchase']['SyncToken'];
			unset( $response );
		}

		$payload = json_encode( $payload );
		$request_url = esc_url_raw( sprintf( '%s/v3/company/%d/purchase',
			self::$api_base_url, self::$options['auth']['realmId'] ) );

		$oauth_header = $oauth->get_oauth_header( 'POST', $request_url, $payload );
		$response = wp_remote_post( $request_url, array(
			'timeout' => self::REMOTE_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => $oauth_header,
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			),
			'body' => $payload,
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 )
			return new WP_Error( 'error', 'Could not create purchase.' );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body ) )
			return new WP_Error( 'error', 'Could not decode create purchase result.' );

		return array(
			'transaction_id' => intval( $body['Purchase']['Id'] ),
		);
	}

	/**
	 * REST: /classes
	 *
	 * @param WP_REST_Request $request
	 */
	public static function rest_callback_classes( $request ) {
		if ( ! self::_is_valid_request( $request ) )
			return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );

		return self::_get_classes();
	}

	/**
	 * Get an array of available QBO classes.
	 *
	 * @uses get_transient, set_transient
	 *
	 * @return array An array of class IDs as keys, names as values.
	 */
	private static function _get_classes() {
		$cache_key = md5( 'wordcamp-qbo:classes' );
		$cache = get_transient( $cache_key );

		if ( $cache !== false )
			return $cache;

		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$args = array(
			'query' => 'SELECT * FROM Class',
			'minorversion' => 4,
		);

		$request_url = esc_url_raw( sprintf( '%s/v3/company/%d/query',
			self::$api_base_url, self::$options['auth']['realmId'] ) );

		$oauth_header = $oauth->get_oauth_header( 'GET', $request_url, $args );
		$response = wp_remote_get(
			esc_url_raw( add_query_arg( $args, $request_url ) ),
			array(
				'timeout' => self::REMOTE_REQUEST_TIMEOUT,
				'headers' => array(
					'Authorization' => $oauth_header,
					'Accept' => 'application/json',
				)
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'error', 'Could not fetch classes.' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body ) ) {
			return new WP_Error( 'error', 'Could not fetch classes body.' );
		}

		$classes = array();
		foreach ( $body['QueryResponse']['Class'] as $class ) {
			$classes[ $class['Id'] ] = $class['Name'];
		}

		asort( $classes );

		set_transient( $cache_key, $classes, 12 * HOUR_IN_SECONDS );
		return $classes;
	}

	/**
	 * REST: /invoice
	 *
	 * Creates a new Invoice in QuickBooks and sends it to the Customer
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return int|WP_Error The invoice ID on success, or a WP_Error on failure
	 */
	public static function rest_callback_invoice( $request ) {
		if ( ! self::_is_valid_request( $request ) ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );
		}

		$invoice_id = self::create_invoice(
			$request->get_param( 'sponsor'         ),
			$request->get_param( 'currency_code'   ),
			$request->get_param( 'qbo_class_id'    ),
			$request->get_param( 'invoice_title'   ),
			$request->get_param( 'amount'          ),
			$request->get_param( 'description'     ),
			$request->get_param( 'statement_memo'  )
		);

		if ( is_wp_error( $invoice_id ) ) {
			return $invoice_id;
		}

		/*
		 * @todo Sending invoices automatically is initially disabled so we can manually review them for accuracy
		$invoice_sent = self::send_invoice( $invoice_id );

		if ( is_wp_error( $invoice_sent ) ) {
			self::notify_invoice_failed_to_send( $invoice_id, $invoice_sent );
		}
		*/

		return $invoice_id;
	}

	/**
	 * Creates an Invoice in QuickBooks
	 *
	 * @param array  $sponsor
	 * @param string $currency_code
	 * @param int    $class_id
	 * @param string $invoice_title
	 * @param float  $amount
	 * @param string $description
	 * @param string $statement_memo
	 *
	 * @return int|WP_Error Invoice ID on success; error on failure
	 */
	protected static function create_invoice( $sponsor, $currency_code, $class_id, $invoice_title, $amount, $description, $statement_memo ) {
		$qbo_request = self::build_qbo_create_invoice_request(
			$sponsor,
			$currency_code,
			$class_id,
			$invoice_title,
			$amount,
			$description,
			$sponsor['email-address'],
			$statement_memo
		);

		if ( is_wp_error( $qbo_request ) ) {
			return $qbo_request;
		}

		$response = wp_remote_post( $qbo_request['url'], $qbo_request['args'] );

		if ( is_wp_error( $response ) ) {
			$result = $response;
		} elseif ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			$result = new WP_Error( 'invalid_http_code', 'Invalid HTTP response code', $response );
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['Invoice']['Id'] ) ) {
				$result = absint( $body['Invoice']['Id'] );
			} else {
				$result = new WP_Error( 'empty_body', 'Could not decode invoice result.', $response );
			}
		}

		return $result;
	}

	/**
	 * Build the requset to create an invoice in QuickBooks
	 *
	 * @param array  $sponsor
	 * @param string $currency_code
	 * @param int    $class_id
	 * @param string $invoice_title
	 * @param float  $amount
	 * @param string $description
	 * @param string $customer_email
	 * @param string $statement_memo
	 *
	 * @return array|WP_Error
	 */
	protected static function build_qbo_create_invoice_request( $sponsor, $currency_code, $class_id, $invoice_title, $amount, $description, $customer_email, $statement_memo ) {
		$customer_id = self::probably_get_customer_id( $sponsor, $currency_code );

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$class_id        = sanitize_text_field( $class_id        );
		$invoice_title   = sanitize_text_field( $invoice_title   );
		$amount          = floatval(            $amount          );
		$description     = sanitize_text_field( $description     );
		$statement_memo  = sanitize_text_field( $statement_memo  );

		/*
		 * The currency code only needs to be sanitized, not validated, because QBO will reject the invoice if
		 * an invalid code is passed. We don't have to worry about an invoice being assigned the the home currency
		 * by accident.
		 */
		$currency_code = sanitize_text_field( $currency_code );

		/*
		 * QBO sandboxes will send invoices to whatever e-mail address you assign them, rather than sending them
		 * to the sandbox owner. So to avoid sending sandbox e-mails to real sponsor addresses, we use a fake
		 * address instead.
		 */
		if ( self::$sandbox_mode ) {
			$customer_email = 'jane.doe@example.org';
		} else {
			$customer_email = is_email( $customer_email );
		}

		foreach ( array( 'amount', 'customer_id', 'customer_email' ) as $field ) {
			if ( empty( $$field ) ) {
				return new WP_Error( 'required_field_empty', "$field cannot be empty." );
			}
		}

		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$payment_instructions = str_replace( "\t", '', "
			Please remit checks to: WordPress Community Support, PBC, 3426 SE Kathryn Ct, Milwaukie, OR 97222

			For payments via ACH or international wire transfers:

			Bank Name: JPMorgan Chase Bank, N.A.
			Bank Address: 4 New York Plaza, Floor 15, New York, NY 10004, USA
			SWIFT/BIC: CHASUS33
			Bank Routing & Transit Number: 021000021
			Account Number: 791828879

			To pay via credit card: Please send the payment via PayPal to sponsor@wordcamp.org. An additional 3% on the payment to cover PayPal fees is highly appreciated."
		);

		$payload = array(
			'PrivateNote' => $statement_memo,

			'Line' => array(
				array(
					'Amount'      => $amount,
					'Description' => $invoice_title,
					'DetailType'  => 'SalesItemLineDetail',

					'SalesItemLineDetail' => array(
						'ItemRef' => array(
							'value' => '20', // Sponsorship
						),

						'ClassRef' => array(
							'value' => $class_id,
						),

						'UnitPrice' => $amount,
						'Qty'       => 1,
					)
				)
			),

			'CustomerRef' => array(
				'value' => $customer_id,
			),

			// Note: the limit for this is 1,000 characters
			'CustomerMemo' => array(
				'value' => sprintf( "%s\n%s", $description, $payment_instructions ),
			),

			'SalesTermRef' => array(
				'value' => 1, // Due on receipt
			),

			'BillEmail' => array(
				'Address' => $customer_email,
			),
		);

		/*
		 * QuickBooks doesn't have a CustomerCurrency row for the home currency, so a CurrencyRef is only used
		 * for foreign currencies.
		 *
		 * QBO will automatically activate a valid currency for our Company when we create an invoice using it
		 * for the first time, so we don't need any code to automatically activate them.
		 */
		if ( 'USD' != $currency_code ) {
			$payload['CurrencyRef'] = array(
				'value' => $currency_code,
			);
		}

		$request_url = sprintf(
			'%s/v3/company/%d/invoice',
			self::$api_base_url,
			rawurlencode( self::$options['auth']['realmId'] )
		);

		$payload = wp_json_encode( $payload );

		$args = array(
			'timeout' => self::REMOTE_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => $oauth->get_oauth_header( 'POST', $request_url, $payload ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'body' => $payload,
		);

		return array(
			'url'  => $request_url,
			'args' => $args
		);
	}

	/**
	 * Email a QuickBooks invoice to the Customer
	 *
	 * @param int $invoice_id
	 *
	 * @return bool|WP_Error true on success; WP_Error on failure
	 */
	protected static function send_invoice( $invoice_id ) {
		$qbo_request = self::build_qbo_send_invoice_request( $invoice_id );
		$response    = wp_remote_post( $qbo_request['url'], $qbo_request['args'] );

		if ( is_wp_error( $response ) ) {
			$result = $response;
		} elseif ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			$result = new WP_Error( 'invalid_http_code', 'Invalid HTTP response code', $response );
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['Invoice']['EmailStatus'] ) && 'EmailSent' === $body['Invoice']['EmailStatus'] ) {
				$result = true;
			} else {
				$result = new WP_Error( 'empty_body', 'Could not decode invoice result.', $response );
			}
		}

		return $result;
	}

	/**
	 * Build a request to send an Invoice via QuickBook's API
	 *
	 * @param int $invoice_id
	 *
	 * @return array
	 */
	protected static function build_qbo_send_invoice_request( $invoice_id ) {
		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$request_url = sprintf(
			'%s/v3/company/%d/invoice/%s/send',
			self::$api_base_url,
			rawurlencode( self::$options['auth']['realmId'] ),
			rawurlencode( absint( $invoice_id ) )
		);

		$args = array(
			'timeout' => self::REMOTE_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => $oauth->get_oauth_header( 'POST', $request_url ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/octet-stream',
			),
			'body' => '',
		);

		return array(
			'url'  => $request_url,
			'args' => $args,
		);
	}

	/**
	 * Notify Central that an invoice was created but couldn't be sent to the sponsor
	 *
	 * @param int      $invoice_id
	 * @param WP_Error $error
	 *
	 * @return bool
	 */
	protected static function notify_invoice_failed_to_send( $invoice_id, $error ) {
		$message = sprintf( "
			QuickBooks invoice $invoice_id was created, but an error occurred while trying to send it to the sponsor.

			This may be an indication of a bug on WordCamp.org, so please ask your friendly neighborhood developers to investigate.

			The invoice will probably need to be sent manually in QuickBooks, but let the developers investigate first, and then go from there.

			Debugging information for the developers:

			%s",
			print_r( $error, true )
		);
		$message = str_replace( "\t", '', $message );

		return wp_mail( 'support@wordcamp.org', "QuickBooks invoice $invoice_id failed to send", $message );
	}

	/**
	 * Get a Customer ID, either by finding an existing one, or creating a new one
	 *
	 * @param string $sponsor
	 * @param string $currency_code
	 *
	 * @return int|WP_Error The customer ID if success; a WP_Error if failure
	 */
	protected static function probably_get_customer_id( $sponsor, $currency_code ) {
		$customer_id = self::get_customer( $sponsor['company-name'], $currency_code );

		if ( is_wp_error( $customer_id ) || ! $customer_id ) {
			$customer_id = self::create_customer( $sponsor, $currency_code );
		}

		return $customer_id;
	}

	/**
	 * Fetch a Customer record from QBO
	 *
	 * @param string $customer_name
	 * @param string $currency_code
	 *
	 * @return int|false|WP_Error A customer ID as integer, if one was found; false if no match was found; a WP_Error if an error occurred.
	 */
	protected static function get_customer( $customer_name, $currency_code ) {
		$qbo_request = self::build_qbo_get_customer_request( $customer_name );

		if ( is_wp_error( $qbo_request ) ) {
			return $qbo_request;
		}

		$response = wp_remote_get( $qbo_request['url'], $qbo_request['args'] );

		if ( is_wp_error( $response ) ) {
			$result = $response;
		} elseif ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			$result = new WP_Error( 'invalid_http_code', 'Invalid HTTP response code', $response );
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['QueryResponse']['Customer'][0]['Id'] ) ) {
				$result = self::pluck_customer_id_by_currency( $body['QueryResponse']['Customer'], $currency_code );
			} elseif ( isset( $body['QueryResponse'] ) && 0 === count( $body['QueryResponse'] ) ) {
				$result = false;
			} else {
				$result = new WP_Error( 'invalid_response_body', 'Could not extract information from response.', $response );
			}
		}

		return $result;
	}

	/**
	 * Build a request to fetch a Customer from QuickBook's API
	 *
	 * @param string $customer_name
	 *
	 * @return array|WP_Error
	 */
	protected static function build_qbo_get_customer_request( $customer_name ) {
		global $wpdb;

		$customer_name = sanitize_text_field( $customer_name );

		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$request_url = sprintf(
			'%s/v3/company/%d/query',
			self::$api_base_url,
			rawurlencode( self::$options['auth']['realmId'] )
		);

		$request_url_query = array(
			'query' => $wpdb->prepare(
				"SELECT * FROM Customer WHERE CompanyName = '%s'",
				$customer_name
			),
		);

		$args = array(
			'timeout' => self::REMOTE_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => $oauth->get_oauth_header( 'GET', $request_url, $request_url_query ),
				'Accept'        => 'application/json',
			),
		);

		$request_url_query = array_map( 'rawurlencode', $request_url_query ); // has to be done after get_oauth_header(), or oauth_signature won't be generated correctly
		$request_url       = add_query_arg( $request_url_query, $request_url );

		return array(
			'url'  => $request_url,
			'args' => $args,
		);
	}

	/**
	 * Pluck a Customer out of an array based on their currency
	 *
	 * QuickBook's API doesn't allow you to filter query results based on a CurrencyRef, so we have to do it
	 * manually.
	 *
	 * @param array  $customers
	 * @param string $currency_code
	 *
	 * @return int|false A customer ID on success, or false on failure
	 */
	protected static function pluck_customer_id_by_currency( $customers, $currency_code ) {
		$customer_id = false;

		foreach ( $customers as $customer ) {
			if ( $customer['CurrencyRef']['value'] === $currency_code ) {
				$customer_id = absint( $customer['Id'] );
				break;
			}
		}

		return $customer_id;
	}

	/**
	 * Create a customer in QuickBooks for a corresponding Sponsor in WordCamp.org
	 *
	 * @param array  $sponsor
	 * @param string $currency_code
	 *
	 * @return int|WP_Error The customer ID if success; a WP_Error if failure
	 */
	protected static function create_customer( $sponsor, $currency_code ) {
		$qbo_request = self::build_qbo_create_customer_request( $sponsor, $currency_code );

		if ( is_wp_error( $qbo_request ) ) {
			return $qbo_request;
		}

		$response = wp_remote_post( $qbo_request['url'], $qbo_request['args'] );

		if ( is_wp_error( $response ) ) {
			$result = $response;
		} elseif ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			$result = new WP_Error( 'invalid_http_code', 'Invalid HTTP response code', $response );
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['Customer']['Id'] ) ) {
				$result = absint( $body['Customer']['Id'] );
			} else {
				$result = new WP_Error( 'invalid_response_body', 'Could not extract customer ID from response.', $response );
			}
		}

		return $result;
	}

	/**
	 * Build a request to create a Customer via QuickBook's API
	 *
	 * @param array  $sponsor
	 * @param string $currency_code
	 *
	 * @return array|WP_Error
	 */
	protected static function build_qbo_create_customer_request( $sponsor, $currency_code ) {
		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$sponsor                  = array_map( 'sanitize_text_field', $sponsor );
		$sponsor['email-address'] = is_email( $sponsor['email-address'] );
		$currency_code            = sanitize_text_field( $currency_code );

		if ( empty( $sponsor['company-name'] ) || empty( $sponsor['email-address'] ) ) {
			return new WP_Error( 'required_fields_missing', 'Required fields are missing.', $sponsor );
		}

		$payload = array(
			'BillAddr' => array(
				'Line1'                  => $sponsor['address1'],
				'City'                   => $sponsor['city'],
				'Country'                => $sponsor['country'],
				'CountrySubDivisionCode' => $sponsor['state'],
				'PostalCode'             => $sponsor['zip-code'],
			),

			'CurrencyRef' => array(
				'value' => $currency_code
			),

			'PreferredDeliveryMethod' =>'Email',

			'GivenName'        => $sponsor['first-name'],
			'FamilyName'       => $sponsor['last-name'],
			'CompanyName'      => $sponsor['company-name'],
			'DisplayName'      => sprintf( '%s - %s', $sponsor['company-name'], $currency_code ),
			'PrintOnCheckName' => $sponsor['company-name'],

			'PrimaryPhone' => array(
				'FreeFormNumber' => $sponsor['phone-number'],
			),

			'PrimaryEmailAddr' => array(
				'Address' => $sponsor['email-address'],
			),
		);

		if ( isset( $sponsor['address2'] ) ) {
			$payload['BillAddr']['Line2'] = $sponsor['address2'];
		}

		$request_url = sprintf(
			'%s/v3/company/%d/customer',
			self::$api_base_url,
			rawurlencode( self::$options['auth']['realmId'] )
		);

		$payload = wp_json_encode( $payload );

		$args = array(
			'timeout' => self::REMOTE_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => $oauth->get_oauth_header( 'POST', $request_url, $payload ),
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
			'body' => $payload,
		);

		return array(
			'url'  => $request_url,
			'args' => $args,
		);
	}

	/**
	 * Returns the subset of the given invoices that have been paid
	 *
	 * For example, if QBO invoice IDs 15, 18, 29, and 54 are passed, and 15 and 29 are marked as paid in QBO,
	 * then this endpoint will return 15 and 29.
	 *
	 * @param WP_REST_Request $wordcamp_request
	 *
	 * @return array|WP_Error
	 */
	public static function rest_callback_paid_invoices( $wordcamp_request ) {
		if ( ! self::_is_valid_request( $wordcamp_request ) ) {
			return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );
		}

		$qbo_request = self::build_qbo_paid_invoices_request( $wordcamp_request->get_param( 'invoice_ids' ) );

		if ( is_wp_error( $qbo_request ) ) {
			return $qbo_request;
		}

		$response = wp_remote_get( $qbo_request['url'], $qbo_request['args'] );

		if ( is_wp_error( $response ) ) {
			$result = $response;
		} elseif ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			$result = new WP_Error( 'invalid_http_code', 'Invalid HTTP response code', $response );
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['QueryResponse']['Invoice'][0]['Id'] ) ) {
				$result = wp_list_pluck( $body['QueryResponse']['Invoice'], 'Id' );
				$result = array_map( 'absint', $result );
			} elseif ( isset( $body['QueryResponse'] ) && 0 === count( $body['QueryResponse'] ) ) {
				$result = array();  // no invoices have been paid
			} else {
				$result = new WP_Error( 'invalid_response', 'Could not extract invoice IDs from response.', $response );
			}
		}

		return $result;
	}

	/**
	 * Build a request to check QuickBook's API for paid invoices
	 *
	 * @param array $sent_invoice_ids
	 *
	 * @return array|WP_Error
	 */
	protected static function build_qbo_paid_invoices_request( $sent_invoice_ids ) {
		global $wpdb;

		self::load_options();
		$oauth = self::_get_oauth();
		$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );

		$request_url = sprintf(
			'%s/v3/company/%d/query',
			self::$api_base_url,
			rawurlencode( self::$options['auth']['realmId'] )
		);

		$sent_invoice_ids = array_map( 'absint', $sent_invoice_ids );    // Invoice IDs are initially cast as integers for validation, and then converted back to strings, because that's what QBO expects.

		if ( empty( $sent_invoice_ids ) ) {
			return new WP_Error( 'no_ids', 'No Invoice IDs were given.' );
		}

		$invoice_id_placeholders = implode( ', ', array_fill( 0, count( $sent_invoice_ids ), '%s' ) );

		$request_url_query = array(
			'query' => $wpdb->prepare( "
				SELECT Id, Balance
				FROM Invoice
				WHERE
					Id IN ( $invoice_id_placeholders ) AND
					Balance = '0'", // QBO doesn't have an explicit status parameter, it just considers an invoice paid if the balance is 0.
				$sent_invoice_ids
			),
		);

		$args = array(
			'timeout' => self::REMOTE_REQUEST_TIMEOUT,
			'headers' => array(
				'Authorization' => $oauth->get_oauth_header( 'GET', $request_url, $request_url_query ),
				'Accept'        => 'application/json',
			),
		);

		$request_url_query = array_map( 'rawurlencode', $request_url_query );   // The URL query parameters have to be encoded after the OAuth header is generated so that the signature will be generated correctly.
		$request_url       = add_query_arg( $request_url_query, $request_url );

		return array(
			'url'  => $request_url,
			'args' => $args,
		);
	}

	/**
	 * Verify an HMAC signature for an API request.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 *
	 * @return bool True if valid, false if invalid.
	 */
	private static function _is_valid_request( $request ) {
		if ( ! $request->get_header( 'authorization' ) )
			return false;

		if ( ! preg_match( '#^wordcamp-qbo-hmac (.+)$#', $request->get_header( 'authorization' ), $matches ) )
			return false;

		$given_hmac = $matches[1];
		$request_url = esc_url_raw( home_url( parse_url( home_url( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) );
		$payload = json_encode( array( strtolower( $request->get_method() ), strtolower( $request_url ),
			$request->get_body(), $request->get_query_params() ) );

		return hash_equals( hash_hmac( 'sha256', $payload, self::$hmac_key ), $given_hmac );
	}

	/**
	 * Update self::$options.
	 */
	public static function update_options() {
		self::load_options();
		update_option( 'wordcamp-qbo', self::$options );
	}

	/**
	 * Catch an OAuth authentication flow if it is one.
	 */
	public static function maybe_oauth_request() {
		if ( empty( $_GET['wordcamp-qbo-oauth-request'] ) )
			return;

		if ( empty( $_GET['wordcamp-qbo-oauth-nonce'] ) || ! wp_verify_nonce( $_GET['wordcamp-qbo-oauth-nonce'], 'oauth-request' ) )
			wp_die( 'Could not verify nonce.' );

		self::load_options();
		$oauth = self::_get_oauth();

		if ( empty( $_GET['oauth_token'] ) ) {

			// We don't have an access token yet.
			$request_url = 'https://oauth.intuit.com/oauth/v1/get_request_token';
			$callback_url = esc_url_raw( add_query_arg( array(
				'wordcamp-qbo-oauth-request' => 1,
				'wordcamp-qbo-oauth-nonce' => wp_create_nonce( 'oauth-request' ),
			), admin_url() ) );

			$request_token = $oauth->get_request_token( $request_url, $callback_url );
			if ( is_wp_error( $request_token ) )
				wp_die( $request_token->get_error_message() );

			update_user_meta( get_current_user_id(), 'wordcamp-qbo-oauth', $request_token );

			wp_redirect( esc_url_raw( add_query_arg( 'oauth_token', $request_token['oauth_token'],
				'https://appcenter.intuit.com/Connect/Begin' ) ) );
			die();

		} else {

			// We have a token.
			$request_token = get_user_meta( get_current_user_id(), 'wordcamp-qbo-oauth', true );

			if ( $request_token['oauth_token'] != $_GET['oauth_token'] )
				wp_die( 'Could not verify OAuth token.' );

			if ( empty( $_GET['oauth_verifier'] ) )
				wp_die( 'Could not obtain OAuth verifier.' );

			$oauth->set_token( $request_token['oauth_token'], $request_token['oauth_token_secret'] );
			$request_url = 'https://oauth.intuit.com/oauth/v1/get_access_token';

			$access_token = $oauth->get_access_token( $request_url, $_GET['oauth_verifier'] );

			if ( is_wp_error( $access_token ) )
				wp_die( 'Could not obtain an access token.' );

			// We have an access token.
			$data = array(
				'oauth_token' => $access_token['oauth_token'],
				'oauth_token_secret' => $access_token['oauth_token_secret'],
				'realmId' => $_GET['realmId'],
			);

			self::$options['auth'] = $data;

			$oauth->set_token( self::$options['auth']['oauth_token'], self::$options['auth']['oauth_token_secret'] );
			$request_url = sprintf( '%s/v3/company/%d/companyinfo/%d',
				self::$api_base_url, self::$options['auth']['realmId'], self::$options['auth']['realmId'] );

			$oauth_header = $oauth->get_oauth_header( 'GET', $request_url );
			$response = wp_remote_get( $request_url, array(
				'timeout' => self::REMOTE_REQUEST_TIMEOUT,
				'headers' => array(
					'Authorization' => $oauth_header,
					'Accept' => 'application/json',
				)
			) );

			if ( is_wp_error( $response ) ) {
				wp_die( 'Could not obtain company information.' );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['CompanyInfo'] ) ) {
				wp_die( 'Could not obtain company information.' );
			}
			$company_name = $body['CompanyInfo']['CompanyName'];

			self::$options['auth']['name'] = $company_name;
			self::$options['auth']['timestamp'] = time();
			self::update_options();

			// Flush some caches.
			delete_transient( md5( 'wordcamp-qbo:classes' ) );

			wp_die( sprintf( 'Your QBO account (%s) has been linked. You can now close this window.', esc_html( $company_name ) ) );
		}
	}

	/**
	 * Runs during admin_menu
	 */
	public static function admin_menu() {
		$cap = is_multisite() ? 'manage_network' : 'manage_options';
		add_submenu_page( 'options-general.php', 'WordCamp QBO', 'QuickBooks',
			$cap, 'wordcamp-qbo', array( __CLASS__, 'render_settings' ) );
	}

	/**
	 * Runs during admin_init.
	 */
	public static function admin_init() {
		register_setting( 'wordcamp-qbo', 'wordcamp-qbo', array( __CLASS__, 'sanitize_options' ) );
	}

	/**
	 * Runs whenever our options are updated, not necessarily
	 * in an admin or POST context.
	 */
	public static function sanitize_options( $input ) {
		self::load_options();
		$output = self::$options;

		return $output;
	}

	/**
	 * Get an OAuth client object.
	 *
	 * @return WordCamp_QBO_OAuth_Client object.
	 */
	private static function _get_oauth() {
		static $oauth;

		if ( ! isset( $oauth ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'class-wordcamp-qbo-oauth-client.php' );
			$oauth = new WordCamp_QBO_OAuth_Client( self::$consumer_key, self::$consumer_secret );
		}

		return $oauth;
	}

	/**
	 * Render the plugin settings screen.
	 */
	public static function render_settings() {
		self::load_options();
		?>
		<style>
			.qbo-connect {
				width: 195px;
				height: 34px;
				display: inline-block;
				background: url(<?php echo esc_url( plugins_url( '/images/qbo-connect.png', __FILE__ ) ); ?>) 0 0 no-repeat;
				background-size: 195px 34px;
				text-indent: -4000px;
			}
		</style>

		<div class="wrap wordcamp-qbo-settings">
			<h2>QuickBooks Settings</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wordcamp-qbo' ); ?>

				<h2>Account</h2>

				<?php if ( ! empty( self::$options['auth']['name'] ) ) : ?>
					<?php $expires = (int) ( 180 - ( time() - self::$options['auth']['timestamp'] ) / DAY_IN_SECONDS ); ?>
					<p>Connected to <?php echo esc_html( self::$options['auth']['name'] ); ?>.
						<?php printf( _n( 'Expires in %d day.', 'Expires in %d days.', $expires ), $expires ); ?>
						<br />Use the button below to connect to a QuickBooks account.</p>
				<?php endif; ?>

				<a href="#" class="qbo-connect">Connect to QuickBooks</a>
				<?php wp_nonce_field( 'oauth-request', 'wordcamp-qbo-oauth-nonce' ); ?>

				<?php /* submit_button(); */ ?>
			</form>
		</div>
		
		<script>
			(function($){
				$('.qbo-connect').on('click', function(){
					var $form = $('.wordcamp-qbo-settings'),
					    nonce = $form.find('input[name="wordcamp-qbo-oauth-nonce"]').val(),
					    url = '<?php echo esc_js( add_query_arg( 'wordcamp-qbo-oauth-request', 1, admin_url() ) ); ?>',
					    popup = null;

					url += '&wordcamp-qbo-oauth-nonce=' + nonce;
					popup = window.open(url, 'qbo-oauth', 'width=800, height=560');
					return false;
				});
			}(jQuery));
		</script>
		<?php
	}
}

WordCamp_QBO::load();
