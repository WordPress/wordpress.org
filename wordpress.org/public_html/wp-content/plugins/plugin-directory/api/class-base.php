<?php
namespace WordPressdotorg\Plugin_Directory\API;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * @package WordPressdotorg_Plugin_Directory
 */
class Base {
	/**
	 * Initializes REST API customizations.
	 */
	public static function init() {
		self::load_routes();
		self::load_fields();
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
		new Routes\Plugin_Release_Confirmation();
		new Routes\Plugin_E2E_Callback();
	}

	/**
	 * Loads all API field for existing WordPress object types we offer.
	 */
	public static function load_fields() {
		new Fields\Plugin\Banners();
		new Fields\Plugin\Icons();
		new Fields\Plugin\Rating();
		new Fields\Plugin\Ratings();
		new Fields\Plugin\Screenshots();
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
	 * A Permission Check callback which validates the request with a Bearer token.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	function permission_check_internal_api_bearer( $request ) {
		$authorization_header = $request->get_header( 'authorization' );
		$authorization_header = trim( str_ireplace( 'bearer', '', $authorization_header ) );

		if (
			! $authorization_header ||
			! defined( 'PLUGIN_API_INTERNAL_BEARER_TOKEN' ) ||
			! hash_equals( PLUGIN_API_INTERNAL_BEARER_TOKEN, $authorization_header )
		) {
			return new \WP_Error(
				'not_authorized',
				__( 'Sorry! You cannot do that.', 'wporg-plugins' ),
				array( 'status' => \WP_Http::UNAUTHORIZED )
			);
		}

		return true;
	}
}
