<?php
namespace Dotorg\API\Events\Tests;

// Load the project's composer autoloader (PHPUnit, yoast/phpunit-polyfills).
require_once dirname( __DIR__, 5 ) . '/vendor/autoload.php';

// Signal to index.php that we're running tests so it skips main() / bootstrap() / init.php.
define( 'WPORG_RUNNING_TESTS', true );

// Time constants normally defined inside main().
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 60 * 60 );
defined( 'DAY_IN_SECONDS' )  || define( 'DAY_IN_SECONDS', HOUR_IN_SECONDS * 24 );
defined( 'WEEK_IN_SECONDS' ) || define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );

// Throttle constants normally defined inside main(). Default to "off" for tests.
defined( 'THROTTLE_STICKY_WORDCAMPS' ) || define( 'THROTTLE_STICKY_WORDCAMPS', false );
defined( 'THROTTLE_GEONAMES' )         || define( 'THROTTLE_GEONAMES', 0 );
defined( 'THROTTLE_IP2LOCATION' )      || define( 'THROTTLE_IP2LOCATION', 0 );

// Other constants/globals normally initialised in main(); required when the
// needs-db tests are exercised on a sandbox that provides init.php.
defined( 'COVID_IMPACT_EXPIRATION' ) || define( 'COVID_IMPACT_EXPIRATION', strtotime( 'January 1 2022' ) );
$GLOBALS['cache_group'] = 'events';
$GLOBALS['cache_life']  = 12 * HOUR_IN_SECONDS;
if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
	$_SERVER['HTTP_USER_AGENT'] = '';
}

// Pull in the global API config when running on a WordPress.org sandbox; absent locally / in standalone CI.
$api_init_file = dirname( __DIR__, 3 ) . '/init.php';
if ( file_exists( $api_init_file ) ) {
	require_once $api_init_file;
}

// Load the API entry-point so its functions are defined. main() is gated by WPORG_RUNNING_TESTS.
require_once dirname( __DIR__ ) . '/index.php';

// Provide cache-function stubs (normally registered by disable_caching() inside main()).
\Dotorg\API\Events\disable_caching();

/**
 * Make a real HTTP request to the live Events API.
 *
 * Used by the e2e group to detect production regressions and stale data.
 *
 * @param string $path Request path including query string, e.g. "/events/1.0/?location=seattle".
 * @return object { body: string, status_code: int }
 * @throws \RuntimeException When the request fails at the transport layer.
 */
function send_request( $path ) {
	$sandboxed = defined( 'WPORG_SANDBOXED' ) ? WPORG_SANDBOXED : false;
	$host      = $sandboxed ? $sandboxed . '.wordpress.org' : 'api.wordpress.org';
	$url       = 'https://' . $host . $path;

	$body = file_get_contents(
		$url,
		false,
		stream_context_create( array(
			'http' => array(
				'header'        => 'Host: api.wordpress.org',
				'ignore_errors' => true,
				'user_agent'    => 'WordPress/' . PHP_VERSION . '; https://example.org',
				'timeout'       => 15,
			),
			'ssl'  => array(
				'verify_peer_name' => ! $sandboxed,
			),
		) )
	);

	if ( false === $body ) {
		$last_error = error_get_last();
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test bootstrap; message is CLI output for PHPUnit, not HTML.
		throw new \RuntimeException( sprintf( 'Failed to fetch %s: %s', $url, $last_error['message'] ?? 'unknown error' ) );
	}

	$status_code = 0;
	foreach ( $http_response_header ?? array() as $header ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $header, $m ) ) {
			$status_code = (int) $m[1];
		}
	}

	return (object) array(
		'body'        => $body,
		'status_code' => $status_code,
	);
}
