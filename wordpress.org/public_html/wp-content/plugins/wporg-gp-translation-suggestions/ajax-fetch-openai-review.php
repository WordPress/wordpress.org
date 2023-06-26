<?php
if ( defined( 'ABSPATH' ) ) {
	// only meant to be called directly.
	exit;
}
header( 'Content-type: application/json' );

if ( empty( $_POST['action'] ) || 'fetch_openai_review' !== $_POST['action'] ) {
	echo '{"success":false","data":{"error":"wrong-action","status":404}}';
	exit;
}

require_once dirname( __DIR__, 3 ) . '/wp-config.php';
require_once ABSPATH . 'wp-includes/ms-functions.php';
require_once ABSPATH . 'wp-includes/pluggable.php';
check_ajax_referer( 'gp_comment_feedback', 'nonce' );

$decoded = wp_parse_auth_cookie( '', 'logged_in' );

if ($decoded['expiration'] < time()) {
	echo '{"success":false","data":{"error":"expired","status":404}}';
	exit;
}
wp_set_current_user( null, $decoded['username'] );
if ( ! is_user_logged_in() ) {
	echo '{"success":false","data":{"error":"not-logged-in","status":404}}';
	exit;
}
switch_to_blog( 351 ); // translate.wordpress.org

$openai_key = false;
$gp_chatgpt_custom_prompt = false;

$default_sort = get_user_option( 'gp_default_sort' );
if ( is_array( $default_sort ) ) {
		if ( isset( $default_sort['openai_api_key'] ) ) {
			$openai_key = $default_sort['openai_api_key'];
		}
		if ( isset( $default_sort['openai_custom_prompt'] ) ) {
			$gp_chatgpt_custom_prompt = $default_sort['openai_custom_prompt'];
		}
}
if ( ! $openai_key ) {
	echo '{"success":false","data":{"error":"no-openai-key","status":404}}';
	exit;
}

$original_singular = substr( $_POST['data']['original'], 0, 1000 );
$translation = substr( $_POST['data']['translation'], 0, 1000 );
$language = substr( $_POST['data']['language'], 0, 50 );

$glossary_query = substr( $_POST['data']['glossary_query'], 0, 1000 );

$messages = array();
$openai_query .= 'For the english text  "' . addslashes( $original_singular ) . '", is "' . addslashes( $translation ) . '" a correct translation in ' . addslashes( $language ) . '?';
$openai_query  = ( $is_retry ) ? 'Are you sure that ' . $openai_query : $openai_query;
if ( $glossary_query ) {
	$messages[] = array(
		'role'    => 'system',
		'content' => $glossary_query,
	);
}

$messages[]      = array(
	'role'    => 'user',
	'content' => $openai_query,
);

$start_time = microtime( true );
$openai_response = wp_remote_post(
	'https://api.openai.com/v1/chat/completions',
	array(
		'timeout' => 20,
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $openai_key,
		),
		'body'    => wp_json_encode(
			array(
				'model'       => 'gpt-3.5-turbo',
				'max_tokens'  => 1000,
				'n'           => 1,
				'messages'    => $messages,
			)
		),
	)
);
$end_time   = microtime( true );
$time_taken = $end_time - $start_time;

$response_status = wp_remote_retrieve_response_code( $openai_response );
$output          = json_decode( wp_remote_retrieve_body( $openai_response ), true );
$response = array();

if ( 200 !== $response_status || is_wp_error( $openai_response ) ) {
	$response['status'] = $response_status;
	$response['error']  = wp_remote_retrieve_response_message( $openai_response );
	echo json_encode( $response );
	exit;
}

$message                          = $output['choices'][0]['message'];
$response['status']     = $response_status;
$response['review']     = trim( trim( $message['content'] ), '"' );
$response['time_taken'] = $time_taken;

echo json_encode( array( 'success' => true, 'data' => $response ) );
