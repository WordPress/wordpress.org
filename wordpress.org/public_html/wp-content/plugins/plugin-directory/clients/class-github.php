<?php
namespace WordPressdotorg\Plugin_Directory\Clients;
use Ahc\Jwt\JWT;

/**
 * Simple GitHub client.
 *
 * @package WordPressdotorg\Plugin_Directory\Clients
 */
class GitHub {

	public static function api( $url, $args = null, $headers = [], $method = null ) {
		// Verify the configuration variables are available.
		if ( ! defined( 'PLUGIN_GITHUB_APP_ID' ) || ! defined( 'PLUGIN_GITHUB_APP_PRIV_KEY' ) ) {
			return false;
		}

		// Prepend GitHub URL for relative URLs, not all API URI's are on api.github.com, which is why we support full URI's.
		if ( '/' === substr( $url, 0, 1 ) ) {
			$url = 'https://api.github.com' . $url;
		}
	
		$request = wp_remote_request(
			$url,
			array(
				'method' =>  $method ?: ( is_null( $args ) ? 'GET' : 'POST' ),
				'headers'    => array_merge(
					[
						'Accept'        => 'application/vnd.github.machine-man-preview+json',
						'Authorization' => 'BEARER ' . self::get_app_install_token(),
					],
					$headers
				),
				'body' => $args ?: null,
			)
		);

		return json_decode( wp_remote_retrieve_body( $request ) );
	}

	/**
	 * Fetch an App Authorization token for accessing Github Resources.
	 */
	protected static function get_app_install_token() {
		$token = false; get_site_transient( __CLASS__ . 'app_install_token' );
		if ( $token ) {
			return $token;
		}

		$jwt_token = self::get_jwt_app_token();
		if ( ! $jwt_token ) {
			return false;
		}

		$installs = wp_remote_get(
			'https://api.github.com/app/installations',
			array(
				'headers'    => array(
					'Accept'        => 'application/vnd.github.machine-man-preview+json',
					'Authorization' => 'BEARER ' . $jwt_token,
				),
			)
		);

		$installs = is_wp_error( $installs ) ? false : json_decode( wp_remote_retrieve_body( $installs ) );

		if ( ! $installs || empty( $installs[0]->access_tokens_url ) ) {
			return false;
		}

		$access_token = wp_remote_post(
			$installs[0]->access_tokens_url,
			array(
				'headers'    => array(
					'Accept'        => 'application/vnd.github.machine-man-preview+json',
					'Authorization' => 'BEARER ' . $jwt_token,
				),
			)
		);

		$access_token = is_wp_error( $access_token ) ? false : json_decode( wp_remote_retrieve_body( $access_token ) );
		if ( ! $access_token || empty( $access_token->token ) ) {
			return false;
		}

		$token     = $access_token->token;
		$token_exp = strtotime( $access_token->expires_at );

		// Cache the token for 1 minute less than what it's valid for.
		set_site_transient( __CLASS__ . 'app_install_token', $token, $token_exp - time() - MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Generate a JWT Authorization token for the Github /app API endpoints.
	 */
	protected static function get_jwt_app_token() {
		$token = get_site_transient( __CLASS__ . 'app_token' );
		if ( $token ) {
			return $token;
		}

		// This should be replaced with an Autoloader.
		if ( ! class_exists( 'Ahc\Jwt\JWT' ) ) {
			require_once dirname( __DIR__ ) . '/libs/adhocore-php-jwt/JWTException.php';
			require_once dirname( __DIR__ ) . '/libs/adhocore-php-jwt/ValidatesJWT.php';
			require_once dirname( __DIR__ ) . '/libs/adhocore-php-jwt/JWT.php';
		}

		$key = openssl_pkey_get_private( base64_decode( PLUGIN_GITHUB_APP_PRIV_KEY ) );
		$jwt = new JWT( $key, 'RS256' );

		$token = $jwt->encode( array(
			'iat' => time(),
			'exp' => time() + 10 * MINUTE_IN_SECONDS,
			'iss' => PLUGIN_GITHUB_APP_ID,
		) );

		// Cache it for 9 mins (It's valid for 10min).
		set_site_transient( __CLASS__ . 'app_token', $token, 9 * MINUTE_IN_SECONDS );

		return $token;
	}
}