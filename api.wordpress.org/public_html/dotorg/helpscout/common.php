<?php
namespace WordPressdotorg\API\HelpScout;
use WordPressdotorg\MU_Plugins\Utilities\HelpScout;

/**
 * Load WordPress.
 */
function load_wordpress( $wp_init_host = '' ) {
	if ( ! $wp_init_host ) {
		$wp_init_host = 'https://api.wordpress.org/';
	}

	$base_dir = dirname( dirname( __DIR__ ) );
	require( $base_dir . '/wp-init.php' );
}
// Always load WordPress, if WordPress is not loaded.
if ( ! defined( 'ABSPATH' ) ) {
	load_wordpress( $wp_init_host ?? '' );
}

/**
 * Retrieve the incoming payload, and verify it's from HelpScout.
 */
function get_request() {
	global $HTTP_RAW_POST_DATA;

	// HelpScout sends json data in the POST, so grab it from the input directly.
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );

	// Check the signature matches.
	if ( ! is_from_helpscout( $HTTP_RAW_POST_DATA, $_SERVER['HTTP_X_HELPSCOUT_SIGNATURE'] ?? '' ) ) {
		exit;
	}

	// get the info from HS.
	return json_decode( $HTTP_RAW_POST_DATA );
}

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
	$email_id = $request->ticket->id      ?? ( $request->id ?? false );
	$subject  = $request->ticket->subject ?? ( $request->subject ?? '' );
	$customer = $request->customer        ?? ( $request->primaryCustomer ?? false );
	$email    = $customer->email          ?? false;
	$user     = get_user_by( 'email', $email );

	// If this is related to a slack user, fetch their details instead.
	if (
		false !== stripos( $email, 'slack' ) &&
		preg_match( '/(\S+)@chat.wordpress.org/i', $subject, $m )
	) {
		$user = get_user_by( 'slug', $m[1] );
	}

	// If the customer object has alternative emails listed, check to see if they have a profile.
	if ( ! $user && ! empty( $customer->emails ) ) {
		$user = get_user_from_emails( $customer->emails );
	}

	// Ignore @wordpress.org "users", unless it's literally the only match (The ?? $email fallback at the end).
	if ( $user && str_ends_with( $user->user_email, '@wordpress.org' ) ) {
		$user = false;
	}

	// Is this is a bounce for an email that we have included the username in the subject for?
	if ( preg_match( '#Are your plugins ready, (.+?)[?]#i', $subject, $m ) ) {
		$user = get_user_by( 'login', $m[1] );
	}

	// Determine if this is a bounce, and if so, find out who for.
	if ( ! $user && $email && $email_id ) {
		$from          = strtolower( implode( ' ', array_filter( [ $email, ( $customer->fname ?? false ), ( $customer->first ?? false ), ( $customer->lname ?? false ), ( $customer->last ?? false ) ] ) ) );
		$subject_lower = strtolower( $subject );

		if (
			str_contains( $from, 'mail delivery' ) ||
			str_contains( $from, 'postmaster' ) ||
			str_contains( $from, 'mailer-daemon' ) ||
			str_contains( $from, 'noreply' ) ||
			str_contains( $subject_lower, 'undeliverable' ) ||
			str_contains( $subject_lower, 'undelivered mail' ) ||
			str_contains( $subject_lower, 'returned mail' ) ||
			str_contains( $subject_lower, 'returned to sender' ) ||
			str_contains( $subject_lower, 'delivery status' ) ||
			str_contains( $subject_lower, 'delivery report' ) ||
			str_contains( $subject_lower, 'mail delivery failed' ) ||
			str_contains( $subject_lower, 'mail delivery failure' )
		) {

			// Fetch the email.
			$threads = $request->_embedded->threads ?? ( get_email_thread( $email_id )->_embedded->threads ?? [] );
			if (  $threads ) {
				$attachment_api_urls = [];

				foreach ( $threads as $thread ) {
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

		// If the email is a plus address, try without. This is common with auto-responders it seems.
		if ( str_contains( $maybe_email, '+' ) ) {
			$maybe_email = preg_replace( '/[+].+@/', '@', $maybe_email );
			$user        = get_user_by( 'email', $maybe_email );
			if ( $user ) {
				return $user;
			}
		}
	}

	return false;
}

/**
 * Get the possible plugins or themes from the email.
 */
function get_plugin_or_theme_from_email( $request, $validate_slugs = false ) {
	$subject    = $request->subject   ?? ( $request->ticket->subject ?? '' );
	$email_id   = $request->id        ?? ( $request->ticket->id      ?? 0 );
	$mailbox_id = $request->mailboxId ?? ( $request->mailbox->id     ?? 0 );

	$possible = [
		'themes'  => [],
		'plugins' => [],
	];

	// Reported themes, shortcut, assume the slug is the title.. since it always is..
	if ( str_starts_with( $subject, 'Reported Theme:' ) ) {
		$possible['themes'][] = sanitize_title_with_dashes( trim( explode( ':', $subject )[1] ) );
	}

	/*
	 * Plugin reviews, match the format of either:
	 *
	 * "[WordPress Plugin Directory] {Type Of Email}: {Plugin Title}"
	 * "[WordPress Plugin Directory] {Type Of Email} - {Plugin Title}"
	 * "[Translated WordPress Plugin Directory] {Translated Type} - {Plugin Title}
	 *
	 * Because of translations, we can't be sure of the exact wording, so we'll just hope that it matches the general format.
	 * NOTE: \p{Pd} is Regex for a dash-like character, which includes hyphens and ndashes.
	 */
	if (
		(
			'plugins' === get_mailbox_name( $mailbox_id ) &&
			(
				preg_match( '!\[[^]]+\][^:]+: (?P<title>.+)$!i', $subject, $m ) ||
				preg_match( '!\[[^]]+\].+? \p{Pd} (?P<title>.+)$!iu', $subject, $m )
			)
		) || (
			// Same as above, but in non-plugins mailboxes using the English strings only.
			preg_match( '!\[WordPress Plugin Directory\][^:]+: (?P<title>.+)$!i', $subject, $m ) ||
			preg_match( '!\[WordPress Plugin Directory\].+? \p{Pd} (?P<title>.+)$!iu', $subject, $m )
		)
	) {
		switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );
		$plugins = get_posts( [
			// Post titles are always escaped.
			'title'       => esc_html( trim( $m['title'] ) ),
			'post_type'   => 'plugin',
			'post_status' => 'any',
		] );

		// Although the above should always catch it, let's try again with the unescaped title.
		if ( ! $plugins ) {
			$plugins = get_posts( [
				'title'       => trim( $m['title'] ),
				'post_type'   => 'plugin',
				'post_status' => 'any',
			] );
		}

		// If that really didn't work, check the plugin_name_history
		if ( ! $plugins ) {
			$plugins = get_posts( [
				'post_type'   => 'plugin',
				'post_status' => 'any',
				'meta_query'  => [
					[
						'key'     => 'plugin_name_history',
						'compare' => 'LIKE',
						'value'   => '"' . trim( $m['title'] ) . '"',
					],
				]
			] );
		}
		restore_current_blog();

		// As we're searching by title, multiple plugins may come up.
		foreach ( $plugins as $plugin ) {
			$possible['plugins'][] = $plugin->post_name;
		}
	}

	$regexes = [
		'!/([^/]+\.)?wordpress.org/(?<type>plugins|themes)/(?P<slug>[a-z0-9-]+)/?!im',
		'!(?P<type>Plugin|Theme):\s*(?P<slug>[a-z0-9-]+)$!im',
		'!(?P<type>plugins|themes)\.(trac|svn)\.wordpress\.org/(browser/)?(?P<slug>[a-z0-9-]+)!im',
	];

	// Fetch the email threads.
	$threads = $request->_embedded->threads ?? ( get_email_thread( $email_id )->_embedded->threads ?? [] );
	if ( $threads ) {
		foreach ( $threads as $thread ) {
			if ( empty( $thread->body ) ) {
				continue;
			}

			// Extract matches from the email.
			$email_text = strip_tags( str_replace( '<br>', "\n", $thread->body ) );

			foreach ( $regexes as $regex ) {
				if (
					// Check the email text only
					! preg_match_all( $regex, $email_text, $m ) &&
					// ..and the full email body, which may be HTML.
					! preg_match_all( $regex, $thread->body, $m )
				) {
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

			// If we have an edit url.. fetch that too. This is usually in a note from a reviewer.
			if ( preg_match( '!wordpress\.org/(?P<type>(plugins|themes))/wp-admin/post.php\?post=(?P<id>\d+)!i', $email_text . $thread->body, $m ) ) {
				$site_id = 'plugins' == $m['type'] ? WPORG_PLUGIN_DIRECTORY_BLOGID : WPORG_THEME_DIRECTORY_BLOGID;

				switch_to_blog( $site_id );
				$post = get_post( $m['id'] );
				if ( $post ) {
					$possible[ $m['type'] ][] = $post->post_name;
				}
				restore_current_blog();
			}
		}
	}

	// Often a slug is mentioned in the title, so let's try to extract that if we didn't find a better item.
	if ( preg_match_all( '!\b(?P<slug>[a-z0-9\-]{10,})\b!', $subject, $m ) ) {
		if ( ! $possible['plugins'] ) {
			$possible['plugins'] = array_merge( $possible['plugins'], $m['slug'] );
		}

		if ( ! $possible['themes'] ) {
			$possible['themes']  = array_merge( $possible['themes'],  $m['slug'] );
		}
	}

	$possible['themes']  = array_unique( $possible['themes'] );
	$possible['plugins'] = array_unique( $possible['plugins'] );

	// If we only want valid slugs back, better validate them..
	if ( $validate_slugs ) {
		if ( $possible['themes'] ) {
			switch_to_blog( WPORG_THEME_DIRECTORY_BLOGID );
			$themes = get_posts( [
				'post_name__in' => $possible['themes'],
				'post_type'     => 'repopackage',
				'post_status'   => 'any',
			] );
			restore_current_blog();

			$possible['themes'] = wp_list_pluck( $themes, 'post_name' );
		}
		if ( $possible['plugins'] ) {
			switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );
			$plugins = get_posts( [
				'post_name__in' => $possible['plugins'],
				'post_type'     => 'plugin',
				'post_status'   => 'any',
			] );
			restore_current_blog();

			$possible['plugins'] = wp_list_pluck( $plugins, 'post_name' );
		}
	}

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
		wp_cache_set( $cache_key, $user->ID, 'helpscout-users', DAY_IN_SECONDS );
	}

	return $user;
}

/**
 * Find the human-readable name for a mailbox ID.
 *
 * @param int|object $mailbox_id_or_request Mailbox ID or request object.
 */
function get_mailbox_name( $mailbox_id_or_request ) {
	$constants = [
		'data',
		'jobs',
		'openverse',
		'password_resets',
		'photos',
		'plugins',
		'themes',
	];

	$mailbox_id = $mailbox_id_or_request->mailboxId ?? $mailbox_id_or_request;
	if ( ! $mailbox_id || ! is_numeric( $mailbox_id ) ) {
		return 0;
	}

	foreach ( $constants as $constant ) {
		if ( constant( 'HELPSCOUT_' . strtoupper( $constant ) . '_MAILBOXID' ) == $mailbox_id ) {
			return $constant;
		}
	}

	// Fetch the mailbox..
	$mailbox = cached_helpscout_get( "/mailboxes/{$mailbox_id}" );
	if ( ! $mailbox ) {
		return $mailbox_id;
	}

	return sanitize_title( $mailbox->name );
}

/**
 * Keep a cached copy of the received emails in the database for querying.
 *
 * @param string $event   Event name.
 * @param object $request Helpscout request object / Conversation object.
 */
function log_email( $event, $request ) {
	global $wpdb;

	if ( ! str_starts_with( $event, 'convo.' ) ) {
		return;
	}

	if ( empty( $request->id ) ) {
		return;
	}

	$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', "{$wpdb->base_prefix}helpscout", $request->id ) );
	$meta = $row ? $wpdb->get_results( $wpdb->prepare( 'SELECT meta_key, meta_value FROM %i WHERE helpscout_id = %d', "{$wpdb->base_prefix}helpscout_meta", $request->id ), ARRAY_A ) : [];

	// We don't need to know about deleted items or spam.
	if ( 'convo.deleted' === $event || 'spam' === $request->status ) {
		if ( $row ) {
			$wpdb->delete( 'wporg_helpscout', [ 'id' => $request->id ] );
			$wpdb->delete( 'wporg_helpscout_meta', [ 'helpscout_id' => $request->id ] );
		}
		return;
	}

	foreach ( get_plugin_or_theme_from_email( $request, true ) as $type => $slugs ) {
		foreach ( $slugs as $slug ) {
			if ( ! wp_list_filter( $meta, [ 'meta_key' => $type, 'meta_value' => $slug ] ) ) {
				$meta[] = [
					'meta_key'   => $type,
					'meta_value' => $slug,
				];
			}
		}
	}

	$user_id = $row->user_id ?? 0;
	if ( ! $user_id ) {
		$user_email = get_user_email_for_email( $request );
		if ( $user_email ) {
			$user_id = get_user_by( 'email', $user_email )->ID ?? 0;
		}
	}

	$email = $request->primaryCustomer->email ?? ( $row->email ?? '' );
	$name = '';
	if ( ! empty( $request->primaryCustomer ) ) {
		$name = $request->primaryCustomer->first ?? '';
		$name .= ' ' . ( $request->primaryCustomer->last ?? '' );
		$name = trim( $name );
	}
	$email = $name ? "{$name} <{$email}>" : $email;

	$data = [
		'id'       => $request->id,
		'number'   => $request->number,
		'user_id'  => $user_id,
		'mailbox'  => get_mailbox_name( $request->mailboxId ),
		'status'   => $request->status,
		'email'    => $email,
		'subject'  => $request->subject ?? ( $row->subject ?? '' ),
		'preview'  => $request->preview ?? ( $row->preview ?? '' ),
		'created'  => gmdate( 'Y-m-d H:i:s', strtotime( $request->createdAt ) ),
		'closed'   => empty( $request->closedAt ) ? '' : gmdate( 'Y-m-d H:i:s', strtotime( $request->closedAt ) ),
		'modified' => gmdate( 'Y-m-d H:i:s', max( array_filter( [ strtotime( $request->createdAt ), strtotime( $request->userUpdatedAt ), strtotime( $request->closedAt ?? '' ) ] ) ) ),
	];

	if ( $row ) {
		$wpdb->update( "{$wpdb->base_prefix}helpscout", $data, [ 'id' => $data['id'] ] );
	} else {
		$wpdb->insert( "{$wpdb->base_prefix}helpscout", $data );
	}

	foreach ( $meta as $kv ) {
		$wpdb->query( $wpdb->prepare(
			'INSERT INTO %i ( helpscout_id, meta_key, meta_value ) VALUES ( %d, %s, %s ) ON DUPLICATE KEY UPDATE meta_value = VALUES( meta_value )',
			"{$wpdb->base_prefix}helpscout_meta",
			$data['id'],
			$kv['meta_key'],
			$kv['meta_value']
		) );
	}
}
