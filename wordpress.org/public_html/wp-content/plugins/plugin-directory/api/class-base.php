<?php
namespace WordPressdotorg\Plugin_Directory\API;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * @package WordPressdotorg_Plugin_Directory
 */
class Base {

	/**
	 * The request parameter that carries a privileged route's action nonce.
	 *
	 * Kept separate from `_wpnonce`, which core's cookie authentication claims for
	 * the generic `wp_rest` action before a route is reached.
	 *
	 * @var string
	 */
	const ACTION_NONCE_PARAM = '_wporg_action';

	/**
	 * Initializes REST API customizations.
	 */
	public static function init() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'reject_cross_origin_write' ), 10, 3 );

		self::load_routes();
	}

	/**
	 * Loads all API route we offer.
	 */
	public static function load_routes() {
		new Routes\Internal_Stats();
		new Routes\Plugin();
		new Routes\Locale_Banner();
		new Routes\Plugin_Favorites();
		new Routes\Commit_Subscriptions();
		new Routes\Popular_Tags();
		new Routes\Query_Plugins();
		new Routes\SVN_Access();
		new Routes\Plugin_Committers();
		new Routes\Plugin_Support_Reps();
		new Routes\Plugin_Self_Close();
		new Routes\Plugin_Self_Transfer();
		new Routes\Plugin_Self_Toggle_Preview();
		new Routes\Plugin_Release_Confirmation();
		new Routes\Plugin_Categorization();
		new Routes\Plugin_Upload();
		new Routes\Plugin_Blueprint();
		new Routes\Plugin_Review();
		new Routes\Gandalf_Scan();
	}

	/**
	 * A validation callback for REST API Requests to ensure a valid plugin slug is presented.
	 *
	 * @param string $value The plugin slug to be checked for.
	 * @return bool Whether the plugin slug exists.
	 */
	function validate_plugin_slug_callback( $value ) {
		return is_string( $value ) && $value && Plugin_Directory::get_plugin_post( $value );
	}

	/**
	 * A Permission Check callback which validates the request against the internal api-call token.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_internal_api_bearer( $request ) {
		return $this->permission_check_api_bearer( $request, 'PLUGIN_API_INTERNAL_BEARER_TOKEN' );
	}

	/**
	 * A Permission Check callback which validates the request against a GitHub specific token.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_github_api_bearer( $request ) {
		return $this->permission_check_api_bearer( $request, 'PLUGIN_API_GITHUB_BEARER_TOKEN' );
	}

	/**
	 * A Permission Check callback which validates the a request against a given token.
	 *
	 * @param \WP_REST_Request $request  The Rest API Request.
	 * @param string           $constant The constant that contains the expected bearer.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_api_bearer( $request, $constant = false ) {
		$authorization_header = $request->get_header( 'authorization' ) ?? '';
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! $constant ||
			! defined( $constant ) ||
			! hash_equals( constant( $constant ), $authorization_header )
		) {
			return new \WP_Error(
				'not_authorized',
				__( 'Sorry! You cannot do that.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::UNAUTHORIZED )
			);
		}

		return true;
	}

	/**
	 * Mints the nonce a privileged route requires, bound to the action it authorizes.
	 *
	 * @param string $action  The route action, such as `add_committer`.
	 * @param string $subject Optional. What the action is performed on, usually a plugin slug.
	 * @return string The nonce to pass as {@see Base::ACTION_NONCE_PARAM}.
	 */
	public static function action_nonce( $action, $subject = '' ) {
		return wp_create_nonce( self::action_nonce_name( $action, $subject ) );
	}

	/**
	 * Names the nonce action for a privileged route.
	 *
	 * @param string $action  The route action, such as `add_committer`.
	 * @param string $subject Optional. What the action is performed on, usually a plugin slug.
	 * @return string The nonce action.
	 */
	protected static function action_nonce_name( $action, $subject = '' ) {
		$subject = (string) $subject;

		return 'wporg_plugins_' . $action . ( '' !== $subject ? ':' . $subject : '' );
	}

	/**
	 * A Permission Check callback which requires both the capability and the route's own nonce.
	 *
	 * The nonce is bound to the request's `plugin_slug`; a route without one calls
	 * {@see Base::verify_action_nonce()} itself with whatever identifies its subject.
	 *
	 * @param \WP_REST_Request $request    The Rest API Request.
	 * @param string           $capability The capability the action requires.
	 * @param string           $action     The route action, such as `add_committer`.
	 * @param mixed            $subject    Optional. What the capability is checked against.
	 * @return bool|\WP_Error True when both hold, false or WP_Error upon failure.
	 */
	public function permission_check_action( $request, $capability, $action, $subject = null ) {
		if ( ! current_user_can( $capability, $subject ) ) {
			return false;
		}

		return $this->verify_action_nonce( $request, $action, $request['plugin_slug'] ?? '' );
	}

	/**
	 * Verifies that a request carries the nonce for the action it is asking for.
	 *
	 * Returns the same error code as a stale `wp_rest` nonce, so the routes that turn
	 * that into a "link has expired" page keep doing so.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @param string           $action  The route action, such as `add_committer`.
	 * @param string           $subject Optional. What the action is performed on.
	 * @return bool|\WP_Error True if the nonce is valid, WP_Error upon failure.
	 */
	public function verify_action_nonce( $request, $action, $subject = '' ) {
		$nonce = $request->get_param( self::ACTION_NONCE_PARAM );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::action_nonce_name( $action, $subject ) ) ) {
			return new \WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Sorry! You cannot do that.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::FORBIDDEN )
			);
		}

		return true;
	}

	/**
	 * Keeps a cookie-authenticated write to this namespace on the directory's own pages.
	 *
	 * The login cookie is shared across the wordpress.org hosts, so the session does not
	 * say which host a request came from; `Origin` does.
	 *
	 * Routes registered without a permission check are exempt, such as the blueprint
	 * Playground fetches from its own origin: the session is not what authorizes those.
	 *
	 * @param mixed            $response Result to send to the client.
	 * @param array            $handler  Route handler used for the request.
	 * @param \WP_REST_Request $request  Request used to generate the response.
	 * @return mixed The response, or WP_Error when the write came from elsewhere.
	 */
	public static function reject_cross_origin_write( $response, $handler, $request ) {
		if ( null !== $response ) {
			return $response;
		}

		if ( ! str_starts_with( strtolower( ltrim( $request->get_route(), '/' ) ), 'plugins/v1/' ) ) {
			return $response;
		}

		if ( in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return $response;
		}

		if ( isset( $handler['permission_callback'] ) && '__return_true' === $handler['permission_callback'] ) {
			return $response;
		}

		if ( ! is_user_logged_in() ) {
			return $response;
		}

		$origin = get_http_origin();
		if ( ! $origin || in_array( $origin, self::same_site_origins(), true ) ) {
			return $response;
		}

		return new \WP_Error(
			'rest_cross_origin_write',
			__( 'Sorry! You cannot do that.', 'wporg-plugins' ),
			array( 'status' => \WP_Http::FORBIDDEN )
		);
	}

	/**
	 * The origins this site's own pages are served from.
	 *
	 * Whether the localised hosts carry their own `home_url()` is decided by the host
	 * mapping, which lives outside this repository; the filter is there for it to add
	 * them. Anything missing here is a legitimate write refused.
	 *
	 * @return array The origins a write may carry.
	 */
	protected static function same_site_origins() {
		$origins = array();

		foreach ( array( home_url(), admin_url() ) as $url ) {
			$parts = wp_parse_url( $url );

			if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
				$origins[] = $parts['scheme'] . '://' . $parts['host'] .
					( empty( $parts['port'] ) ? '' : ':' . $parts['port'] );
			}
		}

		/**
		 * Filters the origins a `plugins/v1` write may be made from.
		 *
		 * @param array $origins Origins serving this directory's own pages.
		 */
		$origins = apply_filters( 'wporg_plugins_same_site_origins', array_unique( $origins ) );

		return array_map( 'strval', (array) $origins );
	}
}
