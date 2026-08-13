<?php
/**
 * WordPress.org Browse Happy API endpoint.
 *
 * @package BrowseHappy
 */

/*
 * This is a standalone, unauthenticated, stateless API endpoint: WordPress is not loaded,
 * so request data is never slashed, and there is no session or nonce infrastructure.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

require dirname( __FILE__ ) . '/parse.php';

// A JSONP callback is a plain JavaScript identifier; anything else is discarded.
$jsonp_filter_args = array(
	'options' => array(
		'regexp'  => '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
		'default' => '',
	),
	'flags'   => FILTER_REQUIRE_SCALAR,
);

$jsonp = '';
if ( ! empty( $_GET['jsonp'] ) ) {
	$jsonp = filter_var( $_GET['jsonp'], FILTER_VALIDATE_REGEXP, $jsonp_filter_args );
	header( 'Content-Type: application/javascript' );
} else if ( ! empty( $_GET['callback'] ) ) {
	$jsonp = filter_var( $_GET['callback'], FILTER_VALIDATE_REGEXP, $jsonp_filter_args );
	header( 'Content-Type: application/javascript' );
}

if ( empty( $_REQUEST['useragent'] ) || ! is_string( $_REQUEST['useragent'] ) ) {
	return;
}

$user_agent = filter_var( $_REQUEST['useragent'], FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );
$data = browsehappy_parse_user_agent( $user_agent );

// Collect a sample: One out of every 25.
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only used for a prefix comparison, never output or stored.
if ( 0 === strpos( $_SERVER['HTTP_USER_AGENT'] ?? '', 'WordPress/' ) && 1 === rand( 1, 25 ) ) {
	require( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/includes/hyperdb/bb-10-hyper-db.php' );
	bh_record_data( $user_agent, $data );
}

if ( $jsonp ) {
	header( 'Access-Control-Allow-Origin: *' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated callback, JSON-encoded payload.
	echo $jsonp . '(' . json_encode( $data ) . ')';
} elseif ( defined( 'JSON_RESPONSE' ) ) {
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Content-Type: application/json' );
	echo json_encode( $data );
} else {
	header( 'Content-Type: text/plain' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- serialized payload served as text/plain.
	echo serialize( $data );
}

/**
 * Logs anonymized user-agent data.
 *
 * @param string $ua   The user-agent string.
 * @param array  $data Parsed user-agent data.
 * @global wpdb  $wpdb WordPress database abstraction object.
 */
function bh_record_data( $ua, $data ) {
	global $wpdb;

	// The requesting client's own user agent, which is recorded alongside the reported one.
	$client_ua = filter_var( $_SERVER['HTTP_USER_AGENT'] ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

	/* Core sends `WordPress/{version}; {site url}`, but the URL may be absent. */
	list( $wp_ver, $url ) = array_pad( explode( ';', $client_ua, 2 ), 2, '' );

	$wp_ver = substr( $wp_ver, 10, 64 );
	$url = rtrim( strtolower( trim( $url ) ), '/' );
	$pk = md5( $url . '|' . $ua );
	$url = md5( $url );
	$browser = $data['name'];
	$version = $data['version'];
	$ts = date( 'Y-m-d H:i:s' );

	$wpdb->query( $wpdb->prepare( "INSERT INTO browsehappy (pk, url, ua, browser, version, wp_ver, ts)
		VALUES ( %s, %s, %s, %s, %s, %s, %s )
		ON DUPLICATE KEY UPDATE ts = %s", $pk, $url, $ua, $browser, $version, $wp_ver, $ts, $ts ) );
}
