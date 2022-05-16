<?php
use WordPressdotorg\API\HelpScout\API as Helpscout_API;

if ( ! isset( $wp_init_host ) ) {
	$wp_init_host = 'https://api.wordpress.org/';
}
$base_dir = dirname( dirname( __DIR__ ) );
require( $base_dir . '/wp-init.php' );

include_once __DIR__ . '/class-helpscout.php';

// function to verify signature from HelpScout
function isFromHelpScout($data, $signature) {
	if ( ! defined( 'HELPSCOUT_SECRET_KEY' ) ) {
		return false;
	}

	$calculated = base64_encode( hash_hmac( 'sha1', $data, HELPSCOUT_SECRET_KEY, true ) );

	return hash_equals( $signature, $calculated );
}

function get_user_email_for_email( $request ) {
	$email   = $request->customer->email ?? false;
	$subject = $request->ticket->subject ?? '';
	$user    = get_user_by( 'email', $email );

	// Ignore @wordpress.org "users", unless it's literally the only match (The ?? $email fallback at the end).
	if ( $user && str_ends_with( $user->user_email, '@wordpress.org' ) ) {
		$user = false;
	}

	// If this is related to a slack user, fetch their details instead.
	if (
		false !== stripos( $email, 'slack' ) &&
		preg_match( '/(\S+)@chat.wordpress.org/i', $subject, $m )
	) {
		$user = get_user_by( 'slug', $m[1] );
	}

	// Determine if this is a bounce, and if so, find out who for.
	if ( ! $user && $email && isset( $request->ticket->id ) ) {
		$from          = strtolower( implode( ' ', array_filter( [ $email, ( $request->customer->fname ?? false ), ( $request->customer->lname ?? false ) ] ) ) );
		$subject_lower = strtolower( $subject );
		if (
			str_contains( $from, 'mail delivery' ) ||
			str_contains( $from, 'postmaster' ) ||
			str_contains( $from, 'mailer-daemon' ) ||
			str_contains( $subject_lower, 'undelivered mail' ) ||
			str_contains( $subject_lower, 'returned mail' ) ||
			str_contains( $subject_lower, 'returned to sender' ) ||
			str_contains( $subject_lower, 'delivery status' ) ||
			str_contains( $subject_lower, 'delivery report' ) ||
			str_contains( $subject_lower, 'mail delivery failed' )
		) {
			// Fetch the email.
			$email_obj = Helpscout_API::api( '/v2/conversations/' . $request->ticket->id . '?embed=threads' );
			if ( ! empty( $email_obj->_embedded->threads ) ) {
				foreach ( $email_obj->_embedded->threads as $thread ) {
					if ( 'customer' !== $thread->type ) {
						continue;
					}

					// Extract emails from the mailer-daemon.
					$email_body = strip_tags( str_replace( '<br>', "\n", $thread->body ) );

					// Extract `To:`, `X-Orig-To:`, and fallback to all emails.
					$emails = [];
					if ( preg_match( '!^(x-orig-to:|to:)\s*(.+@.+)$!im', $email_body, $m ) ) {
						$m[2] = str_replace( [ '&lt;', '&gt;' ], '', $m[2] );
						$m[2] = trim( $m[2], '<> ' );

						$emails = [ $m[2] ];
					} else {
						// Ugly regex for emails, but it's good for mailer-daemon emails.
						if ( preg_match_all( '![^\s;"]+@[^\s;&"]+\.[^\s;&"]+[a-z]!', $email_body, $m ) ) {
							$emails = array_unique( array_diff( $m[0], [ $request->mailbox->email ] ) );
						}
					}

					foreach ( $emails as $maybe_email ) {
						$user = get_user_by( 'email', $maybe_email );
						if ( $user ) {
							break;
						}
					}
				}
			}
		}
	}

	return $user->user_email ?? $email;
}

//  HelpScout sends json data in the POST, so grab it from the input directly
$data = file_get_contents( 'php://input' );

// check the signature header
if ( ! isset( $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'] ) ) {
	exit;
}

$signature = $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'];
if ( ! isFromHelpScout( $data, $signature ) ) {
	// failure = no response
	exit;
}

// get the info from HS
return json_decode( $data );
