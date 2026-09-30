<?php
/**
 * Shared helpers for the FreeScout helpdesk endpoints.
 *
 * Requests come from the freescout.wordpress.net modules; see WPOrgSidebar and WPOrgWebhooks for the payloads.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

/**
 * Loads WordPress for the given host.
 *
 * @param string $wp_init_host Site to load, e.g. https://wordpress.org/plugins/.
 * @return void
 */
function load_wordpress( string $wp_init_host = '' ): void {
	// Set by the config this loads, and read by account.php; without this it would stay local to this function.
	global $nologin_accounts;

	if ( ! $wp_init_host ) {
		$wp_init_host = 'https://api.wordpress.org/';
	}

	$base_dir = dirname( __DIR__, 2 );
	require $base_dir . '/wp-init.php';
}

// Always load WordPress, if WordPress is not loaded.
if ( ! defined( 'ABSPATH' ) ) {
	load_wordpress( $wp_init_host ?? '' );
}

/**
 * Retrieves the incoming payload, and verifies it was signed by FreeScout.
 *
 * Ends the request with a 403 if the signature is missing, invalid, or stale.
 *
 * @return object
 */
function get_request(): object {
	static $request = null;

	if ( null !== $request ) {
		return $request;
	}

	$body      = (string) file_get_contents( 'php://input' );
	$signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FREESCOUT_SIGNATURE'] ?? '' ) );
	$payload   = json_decode( $body );

	if ( ! is_object( $payload ) || ! is_valid_signature( $body, $signature ) || ! is_fresh( $payload ) ) {
		status_header( 403 );
		exit;
	}

	$request = $payload;

	return $request;
}

/**
 * Whether a signature matches the request body.
 *
 * @param string $body      Raw request body.
 * @param string $signature Hex-encoded HMAC-SHA256 of the body.
 * @return bool
 */
function is_valid_signature( string $body, string $signature ): bool {
	if ( ! defined( 'FREESCOUT_SECRET' ) || ! FREESCOUT_SECRET ) {
		return false;
	}

	return hash_equals( hash_hmac( 'sha256', $body, FREESCOUT_SECRET ), $signature );
}

/**
 * Whether a payload was sent recently, so a captured request can't be replayed later.
 *
 * @param object $payload Decoded request payload.
 * @return bool
 */
function is_fresh( object $payload ): bool {
	return abs( time() - (int) ( $payload->sent_at ?? 0 ) ) <= 15 * MINUTE_IN_SECONDS;
}

/**
 * Sends sidebar HTML to FreeScout and ends the request.
 *
 * @param string $html Sidebar HTML.
 * @return never
 */
function send_html( string $html ): never {
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( array( 'html' => $html ) );
	exit;
}

/**
 * Renders a status badge; WPOrgSidebar's stylesheet colors it by tone.
 *
 * @param string $label Badge text.
 * @param string $tone  One of success, warning, error, or neutral.
 * @return string
 */
function render_badge( string $label, string $tone = 'neutral' ): string {
	return sprintf( '<span class="wporg-sidebar-badge is-%s">%s</span>', esc_attr( $tone ), esc_html( $label ) );
}

/**
 * Renders how many items a sidebar section has.
 *
 * @param int $count Number of items.
 * @return string
 */
function render_count( int $count ): string {
	return sprintf( '<span class="wporg-sidebar-count">%d</span>', $count );
}

/**
 * Determines the mailbox slug, e.g. "Plugins" => plugins.
 *
 * @param object $request Request payload.
 * @return string
 */
function get_mailbox_slug( object $request ): string {
	return sanitize_title( (string) ( $request->mailbox->name ?? '' ) );
}

/**
 * Gets the plain text of a thread body.
 *
 * @param string $body Thread body HTML.
 * @return string
 */
function get_thread_text( string $body ): string {
	return wp_strip_all_tags( str_replace( '<br>', "\n", $body ) );
}

/**
 * Gets the email address of the WordPress.org user a conversation is about.
 *
 * Usually that's the sender, but bounces and Slack notifications are about someone else.
 *
 * @param object $request Request payload.
 * @return string The user's email address, or the sender's if no user was found.
 */
function get_user_email_for_email( object $request ): string {
	$subject = (string) ( $request->conversation->subject ?? '' );
	$sender  = $request->sender ?? null;
	$email   = (string) ( $sender->email ?? '' );
	$user    = $email ? get_user_by( 'email', $email ) : false;

	// If this is related to a slack user, fetch their details instead.
	if (
		false !== stripos( $email, 'slack' ) &&
		preg_match( '/(\S+)@chat.wordpress.org/i', $subject, $m )
	) {
		$user = get_user_by( 'slug', $m[1] );
	}

	// If the sender has alternative emails listed, check to see if they have a profile.
	if ( ! $user && ! empty( $sender->emails ) ) {
		$user = get_user_from_emails( array_map( 'strval', (array) $sender->emails ) );
	}

	// Ignore @wordpress.org "users", unless it's literally the only match.
	if ( $user && str_ends_with( $user->user_email, '@wordpress.org' ) ) {
		$user = false;
	}

	// Is this is a bounce for an email that we have included the username in the subject for?
	if ( preg_match( '#Are your plugins ready, (.+?)[?]#i', $subject, $m ) ) {
		$user = get_user_by( 'login', $m[1] ) ?: $user;
	}

	if ( ! $user && $email && is_bounce( $request ) ) {
		$user = get_user_from_bounce( $request );
	}

	return $user ? $user->user_email : $email;
}

/**
 * Whether a conversation looks like a bounce notification.
 *
 * @param object $request Request payload.
 * @return bool
 */
function is_bounce( object $request ): bool {
	$sender  = $request->sender ?? null;
	$from    = strtolower( implode( ' ', array_filter( array( $sender->email ?? '', $sender->first_name ?? '', $sender->last_name ?? '' ) ) ) );
	$subject = strtolower( (string) ( $request->conversation->subject ?? '' ) );

	foreach ( array( 'mail delivery', 'postmaster', 'mailer-daemon', 'noreply' ) as $needle ) {
		if ( str_contains( $from, $needle ) ) {
			return true;
		}
	}

	$subjects = array(
		'undeliverable',
		'undelivered mail',
		'returned mail',
		'returned to sender',
		'delivery status',
		'delivery report',
		'mail delivery failed',
		'mail delivery failure',
	);
	foreach ( $subjects as $needle ) {
		if ( str_contains( $subject, $needle ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Finds the WordPress.org user an email bounced for.
 *
 * @param object $request Request payload.
 * @return \WP_User|false
 */
function get_user_from_bounce( object $request ): \WP_User|false {
	$attachments = array();

	foreach ( $request->threads ?? array() as $thread ) {
		if ( 'customer' !== ( $thread->type ?? '' ) ) {
			continue;
		}

		// Extract emails from the mailer-daemon.
		$user = get_user_from_emails( extract_emails_from_text( get_thread_text( (string) ( $thread->body ?? '' ) ) ) );
		if ( $user ) {
			return $user;
		}

		// The address in the body may be a final forwarding destination; the attached original has the real one.
		foreach ( $thread->attachments ?? array() as $attachment ) {
			$mime_type = (string) ( $attachment->mime_type ?? '' );

			if (
				isset( $attachment->content ) &&
				(int) ( $attachment->size ?? 0 ) <= 100 * KB_IN_BYTES &&
				(
					str_contains( $mime_type, 'message' ) ||
					str_contains( $mime_type, 'text' ) ||
					str_contains( $mime_type, 'rfc' )
				)
			) {
				$attachments[] = (string) $attachment->content;
			}
		}
	}

	foreach ( $attachments as $content ) {
		$user = get_user_from_emails( extract_emails_from_text( $content ) );
		if ( $user ) {
			return $user;
		}
	}

	return false;
}

/**
 * Extracts email-like strings from a string.
 *
 * @param string $text The text to look in.
 * @return array
 */
function extract_emails_from_text( string $text ): array {
	// Extract `To:`, `X-Orig-To:`, and fallback to all emails.
	$emails = array();
	if ( preg_match_all( '!^(x-orig-to:|to:|(Final|Original)-Recipient:(\s*rfc\d+;)?)\s*(?P<email>.+@.+)$!im', $text, $m ) ) {
		$emails = $m['email'];
	} elseif (
		// Ugly regex for emails, but it's good for mailer-daemon emails.
		preg_match_all( '![^\s;"]+@[^\s;&"]+\.[^\s;&"]+[a-z]!', $text, $m )
	) {
		$emails = $m[0];
	}

	$emails = array_map(
		static function ( string $email ): string {
			$email = str_replace( array( '&lt;', '&gt;' ), array( '<', '>' ), $email );

			// Headers can carry a name: `John Doe <john@example.org>`.
			if ( preg_match( '!<(?P<address>[^<>\s]+@[^<>\s]+)>!', $email, $m ) ) {
				return $m['address'];
			}

			return trim( $email, '<> ' );
		},
		$emails
	);

	// Remove any internal emails.
	return array_values(
		array_filter(
			array_unique( $emails ),
			static function ( string $email ): bool {
				return ! str_ends_with( $email, '@wordpress.org' );
			}
		)
	);
}

/**
 * Given a list of emails, finds the first user that matches.
 *
 * @param array $emails The list of emails to check.
 * @return \WP_User|false
 */
function get_user_from_emails( array $emails ): \WP_User|false {
	foreach ( $emails as $maybe_email ) {
		$user = get_user_by( 'email', $maybe_email );
		if ( $user ) {
			return $user;
		}

		// If the email is a plus address, try without. This is common with auto-responders it seems.
		$stripped = strip_plus_address( $maybe_email );
		if ( $stripped !== $maybe_email ) {
			$user = get_user_by( 'email', $stripped );
			if ( $user ) {
				return $user;
			}
		}
	}

	return false;
}

/**
 * Removes the sub-address from a plus address, e.g. `jane+wporg@example.org` => `jane@example.org`.
 *
 * @param string $email Email address.
 * @return string The address without its sub-address, or unchanged if it has none.
 */
function strip_plus_address( string $email ): string {
	return (string) preg_replace( '!^([^+@]+)[+][^@]*@(.+)$!', '$1@$2', $email );
}

/**
 * Gets the possible plugins or themes a conversation is about.
 *
 * @param object $request Request payload.
 * @return array Slugs, keyed by 'plugins' and 'themes'; types without slugs are omitted.
 */
function get_plugin_or_theme_from_email( object $request ): array {
	$subject = (string) ( $request->conversation->subject ?? '' );

	$possible = array(
		'themes'  => array(),
		'plugins' => array(),
	);

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
			'plugins' === get_mailbox_slug( $request ) &&
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
		$possible['plugins'] = array_merge( $possible['plugins'], get_plugin_slugs_by_title( trim( $m['title'] ) ) );
	}

	$regexes = array(
		'!/([^/]+\.)?wordpress.org/(?<type>plugins|themes)/(?P<slug>[a-z0-9-]+)/?!im',
		'!(?P<type>Plugin|Theme):\s*(?P<slug>[a-z0-9-]+)$!im',
		'!(?P<type>plugins|themes)\.(trac|svn)\.wordpress\.org/(browser/)?(?P<slug>[a-z0-9-]+)!im',
	);

	foreach ( $request->threads ?? array() as $thread ) {
		$body = (string) ( $thread->body ?? '' );
		if ( ! $body ) {
			continue;
		}

		$email_text = get_thread_text( $body );

		foreach ( $regexes as $regex ) {
			if (
				// Check the email text only.
				! preg_match_all( $regex, $email_text, $m ) &&
				// ..and the full email body, which may be HTML.
				! preg_match_all( $regex, $body, $m )
			) {
				continue;
			}

			foreach ( $m[0] as $i => $match ) {
				// Sometimes it picks up the references to devhub or make in threads we don't want.
				if ( str_contains( $match, 'developer.wordpress.org' ) || str_contains( $match, 'make.wordpress.org' ) ) {
					continue;
				}

				$type = strtolower( $m['type'][ $i ] );
				if ( ! str_ends_with( $type, 's' ) ) {
					$type .= 's';
				}

				$possible[ $type ][] = strtolower( $m['slug'][ $i ] );
			}
		}

		// If we have an edit url.. fetch that too. This is usually in a note from a reviewer.
		if ( preg_match( '!WordPress\.org/(?P<type>(plugins|themes))/wp-admin/post.php\?post=(?P<id>\d+)!i', $email_text . $body, $m ) ) {
			$type = strtolower( $m['type'] );
			switch_to_blog( 'plugins' === $type ? WPORG_PLUGIN_DIRECTORY_BLOGID : WPORG_THEME_DIRECTORY_BLOGID );
			$post = get_post( (int) $m['id'] );
			if ( $post ) {
				$possible[ $type ][] = $post->post_name;
			}
			restore_current_blog();
		}
	}

	// Often a slug is mentioned in the title, so let's try to extract that if we didn't find a better item.
	if ( preg_match_all( '!\b(?P<slug>[a-z0-9\-]{10,})\b!', $subject, $m ) ) {
		if ( ! $possible['plugins'] ) {
			$possible['plugins'] = $m['slug'];
		}

		if ( ! $possible['themes'] ) {
			$possible['themes'] = $m['slug'];
		}
	}

	$possible['themes']  = array_values( array_unique( $possible['themes'] ) );
	$possible['plugins'] = array_values( array_unique( $possible['plugins'] ) );

	return array_filter( $possible );
}

/**
 * Finds plugins by title, including former titles.
 *
 * @param string $title Plugin title.
 * @return array Plugin slugs; several plugins may share a title.
 */
function get_plugin_slugs_by_title( string $title ): array {
	switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );

	// Post titles are always escaped.
	$plugins = get_posts(
		array(
			'title'       => esc_html( $title ),
			'post_type'   => 'plugin',
			'post_status' => 'any',
		)
	);

	// Although the above should always catch it, let's try again with the unescaped title.
	if ( ! $plugins ) {
		$plugins = get_posts(
			array(
				'title'       => $title,
				'post_type'   => 'plugin',
				'post_status' => 'any',
			)
		);
	}

	// If that really didn't work, check the plugin_name_history.
	if ( ! $plugins ) {
		$plugins = get_posts(
			array(
				'post_type'   => 'plugin',
				'post_status' => 'any',
				'meta_query'  => array(
					array(
						'key'     => 'plugin_name_history',
						'compare' => 'LIKE',
						'value'   => '"' . $title . '"',
					),
				),
			)
		);
	}

	restore_current_blog();

	return wp_list_pluck( $plugins, 'post_name' );
}

/**
 * Determines the WordPress.org user for a helpdesk agent's email addresses.
 *
 * @param array $emails The agent's email addresses.
 * @return \WP_User|false
 */
function get_wporg_user_for_agent_emails( array $emails ): \WP_User|false {
	$emails = array_unique( array_filter( array_map( 'strval', $emails ) ) );

	// Also check plus-addresses without the sub-routing.
	foreach ( $emails as $email ) {
		$stripped = strip_plus_address( $email );
		if ( $stripped !== $email ) {
			$emails[] = $stripped;
		}
	}

	// Longest first, so the most specific address wins.
	usort(
		$emails,
		static function ( string $a, string $b ): int {
			return strlen( $b ) <=> strlen( $a );
		}
	);

	foreach ( $emails as $email ) {
		if ( preg_match( '!^(?P<user>.+)@(chat|git).wordpress.org$!i', $email, $m ) ) {
			$user = get_user_by( 'login', $m['user'] );
			if ( $user ) {
				return $user;
			}
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return $user;
		}
	}

	return false;
}
