<?php
/**
 * Plugin Name: WordCamp.org QBO Integration
 */

class WordCamp_QBO {
	private static $app_token;
	private static $consumer_key;
	private static $consumer_secret;
	private static $hmac_key;

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
			'AccountRef' => array(
				'value' => '61',
				'name' => 'Checking-JPM',
			),
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

			$request_url = esc_url_raw( sprintf( 'https://quickbooks.api.intuit.com/v3/company/%d/purchase/%d',
				self::$options['auth']['realmId'], $payload['Id'] ) );
			$oauth_header = $oauth->get_oauth_header( 'GET', $request_url );
			$response = wp_remote_get( $request_url, array(
				'headers' => array(
					'Authorization' => $oauth_header,
					'Accept' => 'application/json',
				),
			) );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response != 200 ) )
				return new WP_Error( 'error', 'Could not find purchase to update.' );

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! isset( $body['Purchase']['SyncToken'] ) )
				return new WP_Error( 'error', 'Could not decode purchase for update.' );

			$payload['SyncToken'] = $body['Purchase']['SyncToken'];
			unset( $response );
		}

		$payload = json_encode( $payload );
		$request_url = esc_url_raw( sprintf( 'https://quickbooks.api.intuit.com/v3/company/%d/purchase',
			self::$options['auth']['realmId'] ) );

		$oauth_header = $oauth->get_oauth_header( 'POST', $request_url, $payload );
		$response = wp_remote_post( $request_url, array(
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

		$request_url = esc_url_raw( sprintf( 'https://quickbooks.api.intuit.com/v3/company/%d/query',
			self::$options['auth']['realmId'] ) );

		$oauth_header = $oauth->get_oauth_header( 'GET', $request_url, $args );
		$response = wp_remote_get( esc_url_raw( add_query_arg( $args, $request_url ) ), array( 'headers' => array(
			'Authorization' => $oauth_header,
			'Accept' => 'application/json',
		) ) );

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
			$request_url = sprintf( 'https://quickbooks.api.intuit.com/v3/company/%d/companyinfo/%d',
				self::$options['auth']['realmId'], self::$options['auth']['realmId'] );

			$oauth_header = $oauth->get_oauth_header( 'GET', $request_url );
			$response = wp_remote_get( $request_url, array( 'headers' => array(
				'Authorization' => $oauth_header,
				'Accept' => 'application/json',
			) ) );

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
