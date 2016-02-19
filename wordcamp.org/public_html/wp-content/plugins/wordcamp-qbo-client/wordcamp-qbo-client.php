<?php
/**
 * Plugin Name: WordCamp.org QBO Client
 */

class WordCamp_QBO_Client {
	private static $hmac_key;
	private static $api_base;
	private static $options;

	public static function load_options() {
		if ( isset( self::$options ) )
			return self::$options;

		self::$options = wp_parse_args( get_option( 'wordcamp-qbo-client', array() ), array(
			'default-class' => '',
		) );
	}

	public static function load() {
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
	}

	public static function plugins_loaded() {
		$init_options = wp_parse_args( apply_filters( 'wordcamp_qbo_client_options', array() ), array(
			'hmac_key' => '',
			'api_base' => '',
		) );

		foreach ( $init_options as $key => $value )
			self::$$key = $value;

		if ( empty( self::$hmac_key ) )
			return;

		add_action( 'admin_init', array( __CLASS__, 'admin_init' ), 20 );
	}

	public static function admin_init() {
		$cap = is_multisite() ? 'manage_network' : 'manage_options';

		if ( ! current_user_can( $cap ) )
			return;

		if ( ! class_exists( 'WCP_Payment_Request' ) )
			return;

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 20, 2 );
	}

	public static function admin_notices() {
		$screen = get_current_screen();
		if ( $screen->id != 'wcp_payment_request' )
			return;

		$post = get_post();
		if ( $post->post_status == 'auto-draft' )
			return;

		$data = get_post_meta( $post->ID, '_wordcamp-qbo-client-data', true );
		if ( empty( $data['last_error'] ) )
			return;

		printf( '<div class="notice error is-dismissible"><p>QBO Sync Error: %s</p></div>', esc_html( $data['last_error'] ) );
	}

	public static function update_options() {
		self::load_options();
		update_option( 'wordcamp-qbo-client', self::$options );
	}

	public static function add_meta_boxes() {
		add_meta_box( 'qbo-metabox-quickbooks', 'QuickBooks', array( __CLASS__, 'metabox_quickbooks' ),
			WCP_Payment_Request::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Get an array of classes from QBO.
	 *
	 * @uses get_transient()
	 *
	 * @return array Class IDs as keys, names as values.
	 */
	public static function get_classes() {
		$cache_key = md5( 'wordcamp-qbo-client:classes' );
		$cache = get_transient( $cache_key );

		if ( $cache !== false )
			return $cache;

		$request_url = esc_url_raw( self::$api_base . '/classes/' );
		$response = wp_remote_get( $request_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => self::_get_auth_header( 'get', $request_url ),
			),
		) );

		$classes = array();

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body ) && is_array( $body ) )
				$classes = $body;
		}

		set_transient( $cache_key, $classes, 12 * HOUR_IN_SECONDS );
		return $classes;
	}

	public static function metabox_quickbooks() {
		self::load_options();

		$post = get_post();
		$classes = self::get_classes();
		$data = get_post_meta( $post->ID, '_wordcamp-qbo-client-data', true );

		$selected_class = self::$options['default-class'];
		if ( ! empty( $data['class'] ) && array_key_exists( $data['class'], $classes ) )
			$selected_class = $data['class'];

		?>

		<?php if ( ! empty( $data['last_error'] ) ) : ?>
			<p><?php echo esc_html( $data['last_error'] ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $data['transaction_id'] ) ) : ?>
			<p>This request has not been synced with QuickBooks yet.</p>
		<?php else: ?>
			<pre><?php echo esc_html( print_r( $data, true ) ); ?></pre>
		<?php endif; ?>

		<input type="hidden" name="wordcamp-qbo-client-post" value="<?php echo esc_attr( $post->ID ); ?>" />
		<?php wp_nonce_field( 'wordcamp-qbo-client-push-' . $post->ID, 'wordcamp-qbo-client-nonce' ); ?>

		<p>
			<label>
				<input type="checkbox" value="1" name="wordcamp-qbo-client-push"
					<?php checked( ! empty( $data['transaction_id'] ) ); ?> />

				<?php if ( empty( $data['transaction_id'] ) ) : ?>
					Push to QuickBooks
				<?php else : ?>
					Push Changes to QuickBooks
				<?php endif; ?>
			</label>
		</p>
		<p>
			<label>QuickBooks Class:</label>
			<select name="wordcamp-qbo-client-class">
				<option value="">Not Set</option>
				<?php foreach ( self::get_classes() as $id => $class ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $selected_class ); ?>><?php echo esc_html( $class ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<?php if ( ! empty( $data['transaction_id'] ) ) : ?>
			<p>
				Last Sync: <?php echo gmdate( 'Y-m-d H:i:s', absint( $data['timestamp'] ) ); ?> UTC<br />
				Transaction ID: <?php echo absint( $data['transaction_id'] ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	public static function save_post( $post_id, $post ) {
		if ( $post->post_type !== WCP_Payment_Request::POST_TYPE )
			return;

		if ( empty( $_POST['wordcamp-qbo-client-nonce'] ) || empty( $_POST['wordcamp-qbo-client-post'] ) )
			return;

		if ( intval( $_POST['wordcamp-qbo-client-post'] ) !== $post->ID )
			return;

		if ( ! wp_verify_nonce( $_POST['wordcamp-qbo-client-nonce'], 'wordcamp-qbo-client-push-' . $post->ID ) )
			wp_die( 'Could not verify QBO nonce. Please go back, refresh the page and try again.' );

		// No need to push.
		if ( empty( $_POST['wordcamp-qbo-client-push'] ) )
			return;

		if ( $post->post_status != 'paid' )
			wp_die( 'A request has to be marked as paid before it could be synced to QuickBooks.' );

		if ( empty( $_POST['wordcamp-qbo-client-class'] ) )
			wp_die( 'You need to set a QuickBooks class before you can sync this payment request.' );

		$class = $_POST['wordcamp-qbo-client-class'];
		if ( ! array_key_exists( $class, self::get_classes() ) )
			wp_die( 'The class you have picked does not exist.' );

		$data = get_post_meta( $post->ID, '_wordcamp-qbo-client-data', true );
		$txn_id = false;

		if ( ! is_array( $data ) )
			$data = array();

		// This request has not been synced before.
		if ( ! empty( $data['transaction_id'] ) )
			$txn_id = $data['transaction_id'];

		$amount = get_post_meta( $post->ID, '_camppayments_payment_amount', true );
		$amount = preg_replace( '#[^\d.-]+#', '', $amount );
		$amount = floatval( $amount );

		$currency = get_post_meta( $post->ID, '_camppayments_currency', true );
		if ( strtoupper( $currency ) != 'USD' )
			wp_die( 'Non-USD payments sync to QuickBooks is not available yet.' );

		$description_chunks = array( $post->post_title );
		$description = get_post_meta( $post->ID, '_camppayments_description', true );
		if ( ! empty( $description ) )
			$description_chunks[] = $description;

		$description_chunks[] = esc_url_raw( get_edit_post_link( $post->ID, 'raw' ) );
		$description = implode( "\n", $description_chunks );
		unset( $description_chunks );

		$category = get_post_meta( $post->ID, '_camppayments_payment_category', true );
		$date = absint( get_post_meta( $post->ID, '_camppayments_date_vendor_paid', true ) );

		$body = array(
			'id' => $txn_id,
			'date' => $date,
			'amount' => $amount,
			'category' => $category,
			'description' => $description,
			'class' => $class,
		);

		$body = json_encode( $body );
		$request_url = esc_url_raw( self::$api_base . '/expense/' );
		$response = wp_remote_post( $request_url, array(
			'body' => $body,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => self::_get_auth_header( 'post', $request_url, $body ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			$data['last_error'] = $response->get_error_message();
		} elseif ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			$data['last_error'] = 'Could not create or update the QBO transaction.';
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $body['transaction_id'] ) ) {
				$data['last_error'] = 'Could not decode JSON response from API.';
			} else {
				unset( $data['last_error'] );
				$data['transaction_id'] = $body['transaction_id'];
				$data['timestamp'] = time();
				$data['class'] = $class;

				// Remember this class for future reference.
				if ( self::$options['default-class'] != $class ) {
					self::$options['default-class'] = $class;
					self::update_options();
				}
			}
		}

		update_post_meta( $post->ID, '_wordcamp-qbo-client-data', $data );
	}

	/**
	 * Send an invoice to the sponsor through QuickBooks Online's API
	 *
	 * @param int $invoice_id
	 *
	 * @return string
	 */
	public static function send_invoice_to_quickbooks( $invoice_id ) {
		$request  = self::build_send_invoice_request( $invoice_id );
		$response = wp_remote_post( $request['url'], $request['args'] );

		if ( is_wp_error( $response ) ) {
			$sent = $response->get_error_message();
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( is_numeric( $body ) ) {
				$sent = absint( $body );
			} elseif ( isset( $body->message ) ) {
				$sent = $body->message;
			} else {
				$sent = 'Unknown error.';
			}
		}

		return $sent;
	}

	/**
	 * Build a request for sending an invoice to QuickBooks
	 *
	 * @param int $invoice_id
	 *
	 * @return array
	 */
	protected static function build_send_invoice_request( $invoice_id ) {
		$invoice      = get_post( $invoice_id );
		$invoice_meta = get_post_custom( $invoice_id );
		$sponsor_meta = get_post_custom( $invoice_meta['_wcbsi_sponsor_id'][0] );

		$due_date = new \DateTime(
			date( 'Y-m-d', $invoice_meta['_wcbsi_due_date'][0] ),
			new \DateTimeZone( get_option('timezone_string') )
		);

		$payload = array(
			'invoice_title'   => sanitize_text_field( $invoice->post_title                       ),
			'currency_code'   => sanitize_text_field( $invoice_meta['_wcbsi_currency'       ][0] ),
			'qbo_class_id'    => sanitize_text_field( $invoice_meta['_wcbsi_qbo_class_id'   ][0] ),
			'amount'          => floatval(            $invoice_meta['_wcbsi_amount'         ][0] ),
			'description'     => sanitize_text_field( $invoice_meta['_wcbsi_description'    ][0] ),
			'due_date'        => $due_date->format( 'Y-m-dP' ),

			'statement_memo' => sprintf(
				'WordCamp.org Invoice: %s',
				esc_url_raw( admin_url( sprintf( 'post.php?post=%s&action=edit', $invoice_id ) ) )
			),

			'sponsor' => array(
				'company-name'  => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_company_name' ][0] ),
				'first-name'    => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_first_name'   ][0] ),
				'last-name'     => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_last_name'    ][0] ),
				'email-address' => is_email(            $sponsor_meta['_wcpt_sponsor_email_address'][0] ),
				'phone-number'  => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_phone_number' ][0] ),

				'address1' => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_street_address1'][0] ),
				'city'     => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_city'           ][0] ),
				'state'    => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_state'          ][0] ),
				'zip-code' => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_zip_code'       ][0] ),
				'country'  => sanitize_text_field( $sponsor_meta['_wcpt_sponsor_country'        ][0] ),
			)
		);

		if ( isset( $sponsor_meta['_wcpt_sponsor_street_address2'][0] ) ) {
			$payload['sponsor']['address2'] = sanitize_text_field( $sponsor_meta['_wcpt_sponsor_street_address2'][0] );
		}

		$request_url  = self::$api_base . '/invoice';
		$body         = wp_json_encode( $payload );
		$oauth_header = self::_get_auth_header( 'post', $request_url, $body );

		$args = array(
			'headers' => array(
				'Authorization' => $oauth_header,
				'Content-Type'  => 'application/json',
			),
			'body' => $body,
		);

		return array(
			'url'  => $request_url,
			'args' => $args,
		);
	}

	/**
	 * Create an HMAC signature header for a request.
	 *
	 * Use with Authorization HTTP header.
	 *
	 * @see WordCamp_QBO::_is_valid_request()
	 *
	 * @param string $method The request method: GET, POST, etc.
	 * @param string $request_url The clean request URI, without any query arguments.
	 * @param string $body The payload body.
	 * @param array $args The query arguments.
	 *
	 * @return string A sha256 HMAC signature.
	 */
	private static function _get_auth_header( $method, $request_url, $body = '', $args = array() ) {
		$signature = hash_hmac( 'sha256', json_encode( array( strtolower( $method ),
			strtolower( $request_url ), $body, $args ) ), self::$hmac_key );

		return 'wordcamp-qbo-hmac ' . $signature;
	}
}

WordCamp_QBO_Client::load();