<?php
/**
 * Functions for reCAPTCHA.
 */
namespace WordPressdotorg\MainTheme\reCAPTCHA;

function enqueue_script( $form_id ) {
	if ( ! defined( 'RECAPTCHA_INVIS_PUBKEY' ) ) {
		return;
	}

	wp_enqueue_script( 'recaptcha-api', 'https://www.google.com/recaptcha/api.js', array(), '2' );
	wp_add_inline_script( 'recaptcha-api', 'function reCAPTCHAPostSubmit(token) { document.getElementById(' . json_encode( (string)$form_id ) . ').submit(); }' );
}

function display_submit_button( $submit_text = 'Submit', $classes = 'button' ) {
	echo '<input' .
		' data-sitekey=' . esc_attr( RECAPTCHA_INVIS_PUBKEY ) . '"' .
		' data-callback="reCAPTCHAPostSubmit"' .
		' type="submit"' .
		' name="form-submit" id="form-submit"' .
		' class="g-recaptcha ' . esc_attr( $classes ) . '"' .
		' value="' . esc_attr( $submit_text ) . '"' .
		'/>';
}

function check_status() {
	// If reCAPTCHA is not setup, skip it.
	if ( ! defined( 'RECAPTCHA_INVIS_PUBKEY' ) ) {
		return true;
	}

	if ( empty( $_POST['g-recaptcha-response'] ) ) {
		return false;
	}

	$verify = array(
		'secret'   => RECAPTCHA_INVIS_PRIVKEY,
		'remoteip' => $_SERVER['REMOTE_ADDR'],
		'response' => $_POST['g-recaptcha-response'],
	);

	$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'body' => $verify ) );

	if ( is_wp_error( $resp ) || 200 != wp_remote_retrieve_response_code( $resp ) ) {
		return false;
	}

	$result = json_decode( wp_remote_retrieve_body( $resp ), true );

	return (bool) $result['success'];
}