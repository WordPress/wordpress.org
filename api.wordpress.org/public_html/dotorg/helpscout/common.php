<?php
use WordPressdotorg\MU_Plugins\Utilities\HelpScout;

if ( ! isset( $wp_init_host ) ) {
	$wp_init_host = 'https://api.wordpress.org/';
}
$base_dir = dirname( dirname( __DIR__ ) );
require( $base_dir . '/wp-init.php' );

// function to verify signature from HelpScout
function is_from_helpscout( $data, $signature ) {
	if ( ! defined( 'HELPSCOUT_SECRET_KEY' ) || ! $signature ) {
		return false;
	}

	$calculated = base64_encode( hash_hmac( 'sha1', $data, HELPSCOUT_SECRET_KEY, true ) );

	return hash_equals( $signature, $calculated );
}

/**
 * Caching wrapper around the HelpScout conversation API.
 */
function get_email_thread( $thread_id, $force = false ) {
	wp_cache_add_global_groups( 'helpscout-thread' );

	if ( $thread = wp_cache_get( $thread_id, 'helpscout-thread' ) ) {
		if ( ! $force ) {
			return $thread;
		}
	}

	$email_obj = Helpscout::instance()->get( '/v2/conversations/' . $thread_id . '?embed=threads' );

	wp_cache_set( $thread_id, $email_obj, 'helpscout-thread', 6 * HOUR_IN_SECONDS );

	return $email_obj;
}

/**
 * Get the user associated with a HelpScout email.
 */
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

	// If the customer object has alternative emails listed, check to see if they have a profile.
	if ( ! $user && ! empty( $request->customer->emails ) ) {
		foreach ( $request->customer->emails as $alt_email ) {
			$user = get_user_by( 'email', $alt_email );
			if ( $user ) {
				break;
			}
		}
	}

	// Determine if this is a bounce, and if so, find out who for.
	if ( ! $user && $email && isset( $request->ticket->id ) ) {
		$from          = strtolower( implode( ' ', array_filter( [ $email, ( $request->customer->fname ?? false ), ( $request->customer->lname ?? false ) ] ) ) );
		$subject_lower = strtolower( $subject );

		if (
			str_contains( $from, 'mail delivery' ) ||
			str_contains( $from, 'postmaster' ) ||
			str_contains( $from, 'mailer-daemon' ) ||
			str_contains( $from, 'noreply' ) ||
			str_contains( $subject_lower, 'undelivered mail' ) ||
			str_contains( $subject_lower, 'returned mail' ) ||
			str_contains( $subject_lower, 'returned to sender' ) ||
			str_contains( $subject_lower, 'delivery status' ) ||
			str_contains( $subject_lower, 'delivery report' ) ||
			str_contains( $subject_lower, 'mail delivery failed' ) ||
			str_contains( $subject_lower, 'mail delivery failure' )
		) {


			// Fetch the email.
			$email_obj = get_email_thread( $request->ticket->id );
			if ( ! empty( $email_obj->_embedded->threads ) ) {
				foreach ( $email_obj->_embedded->threads as $thread ) {
					if ( 'customer' !== $thread->type ) {
						continue;
					}

					// Extract emails from the mailer-daemon.
					$email_body = strip_tags( str_replace( '<br>', "\n", $thread->body ) );

					// Extract `To:`, `X-Orig-To:`, and fallback to all emails.
					$emails = [];
					if ( preg_match( '!^(x-orig-to:|to:|Final-Recipient:(\s*rfc\d+;)?)\s*(?P<email>.+@.+)$!im', $email_body, $m ) ) {
						$m['email'] = str_replace( [ '&lt;', '&gt;' ], '', $m['email'] );
						$m['email'] = trim( $m['email'], '<> ' );

						$emails = [ $m['email'] ];
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

function get_plugin_or_theme_from_email( $request ) {
	$subject = $request->ticket->subject ?? '';

	$possible = [
		'themes'  => [],
		'plugins' => [],
	];

	// Reported themes, shortcut, assume the slug is the title.. since it is..
	if ( str_starts_with( $subject, 'Reported Theme:' ) ) {
		$possible['themes'][] = sanitize_title_with_dashes( trim( explode( ':', $request->ticket->subject )[1] ) );
	}

	// Often a slug is mentioned in the title, so let's try to extract that.
	if ( preg_match_all( '!(?P<slug>[a-z0-9\-]{10,})!', $subject, $m ) ) {
		$possible['plugins'] = array_merge( $possible['plugins'], $m['slug'] );
		$possible['themes']  = array_merge( $possible['themes'],  $m['slug'] );
	}

	$regexes = [
		'!/([^/]+\.)?wordpress.org/(?<type>plugins|themes)/(?P<slug>[^/]+)/?!im',
		'!(?P<type>Plugin|Theme):\s*(?P<slug>[a-z0-9-]+)$!im',
		'!(?P<type>plugins|themes)\.(trac|svn)\.wordpress\.org/(browser/)?(?P<slug>[^/]+)!im',
	];

	// Fetch the email.
	$email_obj = get_email_thread( $request->ticket->id );
	if ( ! empty( $email_obj->_embedded->threads ) ) {
		foreach ( $email_obj->_embedded->threads as $thread ) {
			if ( empty( $thread->body ) ) {
				continue;
			}

			// Extract matches from the email.
			$email_body = strip_tags( str_replace( '<br>', "\n", $thread->body ) );

			foreach ( $regexes as $regex ) {
				if ( ! preg_match_all( $regex, $email_body, $m ) ) {
					continue;
				}

				foreach ( $m[0] as $i => $match ) {
					if ( str_contains( $match, 'developer.wordpress.org' ) || str_contains( $match, 'make.wordpress.org' ) ) {
						continue; // Sometimes it picks up the references to devhub or make in threads we don't want.
					}

					$type = strtolower( $m['type'][ $i ] );
					if ( ! str_ends_with( $type, 's' ) ) {
						$type .= 's';
					}
					$slug = strtolower( $m['slug'][ $i ] );

					$possible[ $type ][] = $slug;
				}
			}
		}
	}

	$possible['themes']  = array_unique( $possible['themes'] );
	$possible['plugins'] = array_unique( $possible['plugins'] );

	return array_filter( $possible );
}

// HelpScout sends json data in the POST, so grab it from the input directly.
$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );

// Check the signature matches.
if ( ! is_from_helpscout( $HTTP_RAW_POST_DATA, $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'] ?? '' ) ) {
	exit;
}

// get the info from HS.
return json_decode( $HTTP_RAW_POST_DATA );
