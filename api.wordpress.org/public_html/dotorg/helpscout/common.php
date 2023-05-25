<?php
use WordPressdotorg\MU_Plugins\Utilities\HelpScout;

if ( ! isset( $wp_init_host ) ) {
	$wp_init_host = 'https://api.wordpress.org/';
}
$base_dir = dirname( dirname( __DIR__ ) );
require( $base_dir . '/wp-init.php' );

// function to verify signature from HelpScout
function is_from_helpscout( $data, $signature ) {
	$instances = [ 'wordpress', 'foundation' ];

	foreach ( $instances as $instance ) {
		$client = get_client( $instance );

		if ( $client && $client->validate_webhook_signature( $data, $signature ) ) {
			define( 'HELPSCOUT_WEBHOOK_INSTANCE', $client->name );

			return true;
		}
	}

	return false;
}

/**
 * Fetch the appropriate HelpScout API client.
 *
 * @param string $instance The instance to fetch. Accepts 'wordpress', 'foundation', from ?instance=..., or if a webhook based on the calling webhook secret.
 * @return HelpScout
 */
function get_client( $instance = false ) {
	if ( ! $instance && defined( 'HELPSCOUT_WEBHOOK_INSTANCE' ) ) {
		$instance = HELPSCOUT_WEBHOOK_INSTANCE;
	}

	return Helpscout::instance( $instance );
}

/**
 * Caching wrapper around the HelpScout conversation API.
 */
function get_email_thread( $thread_id, $force = false ) {
	if ( ! $thread_id ) {
		return false;
	}

	return cached_helpscout_get( '/v2/conversations/' . $thread_id . '?embed=threads', $force );
}

/**
 * Caching wrapper around the HelpScout GET API.
 *
 * TODO: This should probably be moved to the MU plugin.
 */
function cached_helpscout_get( $url, $force = false, $instance = false ) {
	wp_cache_add_global_groups( 'helpscout-cache' );
	$client    = get_client( $instance );
	$cache_key = "{$client->name}:" . sha1( $url );

	if ( $data = wp_cache_get( $cache_key, 'helpscout-cache' ) ) {
		if ( ! $data ) {
			return $thread;
		}
	}

	$data = $client->get( $url );

	wp_cache_set( $cache_key, $data, 'helpscout-cache', 6 * HOUR_IN_SECONDS );

	return $data;
}

/**
 * Get the user associated with a HelpScout email.
 */
function get_user_email_for_email( $request ) {
	$email   = $request->customer->email ?? false;
	$subject = $request->ticket->subject ?? '';
	$user    = get_user_by( 'email', $email );

	// If this is related to a slack user, fetch their details instead.
	if (
		false !== stripos( $email, 'slack' ) &&
		preg_match( '/(\S+)@chat.wordpress.org/i', $subject, $m )
	) {
		$user = get_user_by( 'slug', $m[1] );
	}

	// If the customer object has alternative emails listed, check to see if they have a profile.
	if ( ! $user && ! empty( $request->customer->emails ) ) {
		$user = get_user_from_emails( $request->customer->emails );
	}

	// Ignore @wordpress.org "users", unless it's literally the only match (The ?? $email fallback at the end).
	if ( $user && str_ends_with( $user->user_email, '@wordpress.org' ) ) {
		$user = false;
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
			$email_obj = get_email_thread( $request->ticket->id ?? 0 );
			if ( ! empty( $email_obj->_embedded->threads ) ) {
				$attachment_api_urls = [];

				foreach ( $email_obj->_embedded->threads as $thread ) {
					if ( 'customer' !== $thread->type ) {
						continue;
					}

					// Extract emails from the mailer-daemon.
					$email_body = strip_tags( str_replace( '<br>', "\n", $thread->body ) );
					$user       = get_user_from_emails( extract_emails_from_text( $email_body ) );
					if ( $user ) {
						break;
					}

					// Track the attachments too, sometimes the email included in the body of the email is a final forwarded destination, but the attachment contains the real email.
					foreach ( $thread->_embedded->attachments ?? [] as $attachment ) {
						if (
							! $attachment->width && // Exclude imagey attachments.
							$attachment->size < 100 * KB_IN_BYTES &&
							(
								str_contains( $attachment->mimeType, 'message' ) ||
								str_contains( $attachment->mimeType, 'text' ) ||
								str_contains( $attachment->mimeType, 'rfc' )
							)
						) {
							$attachment_api_urls[] = $attachment->_links->data->href;
						}
					}
				}

				// If we didn't find a user, try to extract the email from the attachments (Which is likely the original email)
				if ( ! $user && $attachment_api_urls ) {
					foreach ( $attachment_api_urls as $attachment_api_url ) {
						$data = cached_helpscout_get( $attachment_api_url )->data ?? '';
						if ( $data ) {
							$data = base64_decode( $data ) ?: $data;
							$user = get_user_from_emails( extract_emails_from_text( $data ) );
							if ( $user ) {
								break;
							}
						}
					}
				}
			}
		}
	}

	return $user->user_email ?? $email;
}

/**
 * Extract email-like strings from a string.
 *
 * @param string $text The text to look in.
 * @return array
 */
function extract_emails_from_text( $text ) {
	// Extract `To:`, `X-Orig-To:`, and fallback to all emails.
	$emails = [];
	if ( preg_match_all( '!^(x-orig-to:|to:|(Final|Original)-Recipient:(\s*rfc\d+;)?)\s*(?P<email>.+@.+)$!im', $text, $m ) ) {
		$emails = $m['email'];
	} elseif (
		// Ugly regex for emails, but it's good for mailer-daemon emails.
		preg_match_all( '![^\s;"]+@[^\s;&"]+\.[^\s;&"]+[a-z]!', $text, $m )
	) {
		$emails = $m[0];
	}

	// Clean them up.
	foreach ( $emails as &$email ) {
		$email = str_replace( [ '&lt;', '&gt;' ], '', $email );
		$email = trim( $email, '<> ' );
	}
	$emails = array_unique( $emails );

	// Remove any internal emails.
	$emails = array_filter( $emails, function( $email ) {
		return ! str_ends_with( $email, '@wordpress.org' );
	} );

	return $emails;
}

/**
 * Given a list of emails, find the first user that matches.
 *
 * @param array $emails The list of emails to check.
 * @return WP_User|false
 */
function get_user_from_emails( $emails ) {
	foreach ( $emails as $maybe_email ) {
		$user = get_user_by( 'email', $maybe_email );
		if ( $user ) {
			return $user;
		}
	}

	return false;
}

/**
 * Get the possible plugins or themes from the email.
 */
function get_plugin_or_theme_from_email( $request ) {
	$subject = $request->ticket->subject ?? '';

	$possible = [
		'themes'  => [],
		'plugins' => [],
	];

	// Reported themes, shortcut, assume the slug is the title.. since it always is..
	if ( str_starts_with( $subject, 'Reported Theme:' ) ) {
		$possible['themes'][] = sanitize_title_with_dashes( trim( explode( ':', $request->ticket->subject )[1] ) );
	}

	// Plugin reviews, match the format of "[WordPress Plugin Directory] {Type Of Email}: {Plugin Title}"
	if ( preg_match( '!^(Re: )?\[WordPress Plugin Directory\][^:]+: (?P<title>.+)$!i', $subject, $m ) ) {
		switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );
		$plugins = get_posts( [
			'title'       => trim( $m['title'] ),
			'post_type'   => 'plugin',
			'post_status' => 'any',
		] );
		restore_current_blog();

		// As we're searching by title, multiple plugins may come up.
		foreach ( $plugins as $plugin ) {
			$possible['plugins'][] = $plugin->post_name;
		}
	}

	// Often a slug is mentioned in the title, so let's try to extract that.
	if ( preg_match_all( '!(?P<slug>[a-z0-9\-]{10,})!', $subject, $m ) ) {
		$possible['plugins'] = array_merge( $possible['plugins'], $m['slug'] );
		$possible['themes']  = array_merge( $possible['themes'],  $m['slug'] );
	}

	$regexes = [
		'!/([^/]+\.)?wordpress.org/(?<type>plugins|themes)/(?P<slug>[a-z0-9-]+)/?!im',
		'!(?P<type>Plugin|Theme):\s*(?P<slug>[a-z0-9-]+)$!im',
		'!(?P<type>plugins|themes)\.(trac|svn)\.wordpress\.org/(browser/)?(?P<slug>[a-z0-9-]+)!im',
	];

	// Fetch the email.
	$email_obj = get_email_thread( $request->ticket->id ?? 0 );
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

/**
 * Determine the WordPress.org username for a Helpscout user ID.
 *
 * @param int $user_id Helpscout user ID.
 * @return \WP_User WordPress.org user object.
 */
function get_wporg_user_for_helpscout_user( $hs_id, $instance = false ) {
	wp_cache_add_global_groups( 'helpscout-users' );

	if ( ! $hs_id ) {
		return false;
	}

	$client    = get_client( $instance );
	$cache_key = "{$client->name}:{$hs_id}";

	$user_id = wp_cache_get( $cache_key, 'helpscout-users', false, $found );
	if ( $found ) {
		return $user_id ? get_user_by( 'id', $user_id ) : false;
	}

	$hs_user = $client->get( "/users/{$hs_id}" );
	if ( empty( $hs_user->email ) ) {
		return false;
	}

	$emails = array_unique( array_merge( $hs_user->alternateEmails, [ $hs_user->email ] ) );

	// Allow add plus-addresses without the sub-routing to the list of emails to check.
	foreach ( $emails as $email ) {
		$e = preg_replace( '!^([^+]+)[+][^+]+@(.+)$!', '$1@$2', $email );
		if ( $e != $email ) {
			$emails[] = $e;
		}
	}

	// Sort emails by string length
	usort( $emails, function( $a, $b ) {
		return strlen( $b ) <=> strlen( $a );
	} );

	foreach ( $emails as $email ) {
		if ( preg_match( '!^(?P<user>.+)@(chat|git).wordpress.org$!i', $email, $m ) ) {
			$user = get_user_by( 'login', $m['user'] );
			if ( $user ) {
				break;
			}
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			break;
		}
	}

	if ( $user ) {
		wp_cache_set( $cache_key, $user->ID, 'helpscout-users', MONTH_IN_SECONDS );
	}

	return $user;
}

// HelpScout sends json data in the POST, so grab it from the input directly.
$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );

// Check the signature matches.
if ( ! is_from_helpscout( $HTTP_RAW_POST_DATA, $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'] ?? '' ) ) {
	exit;
}

// get the info from HS.
return json_decode( $HTTP_RAW_POST_DATA );
