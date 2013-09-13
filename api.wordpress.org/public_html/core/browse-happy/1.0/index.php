<?php

require dirname( __FILE__ ) . '/parse.php';

$jsonp = '';
if ( ! empty( $_GET['jsonp'] ) ) {
	$jsonp = preg_replace( '/[^a-zA-Z0-9_]/', '', $_GET['jsonp'] );
	header( 'Content-Type: application/javascript' );
} else if ( ! empty( $_GET['callback'] ) ) {
	$jsonp = preg_replace( '/[^a-zA-Z0-9_]/', '', $_GET['callback'] );
	header( 'Content-Type: application/javascript' );
}

if ( empty( $_REQUEST['useragent'] ) )
	return;

$user_agent = $_REQUEST['useragent'];
$data = browsehappy_parse_user_agent( $user_agent );

// Collect a sample: One out of every 25.
if ( 0 === strpos( $_SERVER['HTTP_USER_AGENT'], 'WordPress/' ) && 1 === rand( 1, 25 ) ) {
	require( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/includes/hyperdb/bb-10-hyper-db.php' );
	bh_record_data( $user_agent, $data );
}

if ( $jsonp )
	echo $jsonp.'('.json_encode($data).')';
elseif ( defined( 'JSON_RESPONSE' ) )
	echo json_encode( $data );
else
	echo serialize( $data );

function bh_record_data( $ua, $data ) {
	global $wpdb;

	list( $wp_ver, $url ) = explode( ';', $_SERVER['HTTP_USER_AGENT'], 2 );
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
