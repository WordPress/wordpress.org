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
 * How long a request stays valid after it was sent, in seconds.
 *
 * @var int
 */
const MAX_REQUEST_AGE = 300; // 5 minutes.

/**
 * How far ahead of this server's clock FreeScout's may be, in seconds.
 *
 * @var int
 */
const MAX_CLOCK_SKEW = 10;

/**
 * Retrieves the incoming payload, and verifies FreeScout signed it for this endpoint.
 *
 * Ends the request with a 403 if it isn't a POST, or verify_request() refuses it.
 *
 * @param string $endpoint File name of the endpoint, e.g. profile.php.
 * @return object
 */
function get_request( string $endpoint ): object {
	static $request = null;

	if ( null !== $request ) {
		return $request;
	}

	$method    = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
	$body      = (string) file_get_contents( 'php://input' );
	$signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FREESCOUT_SIGNATURE'] ?? '' ) );
	$payload   = 'POST' === $method ? verify_request( $body, $signature, $endpoint ) : null;

	if ( ! $payload ) {
		status_header( 403 );
		exit;
	}

	$request = $payload;

	return $request;
}

/**
 * Decodes a request body, if FreeScout signed it for this endpoint, recently, and it wasn't used before.
 *
 * @param string $body      Raw request body.
 * @param string $signature Hex-encoded HMAC-SHA256 of the body.
 * @param string $endpoint  File name of the endpoint, e.g. profile.php.
 * @return object|null The payload, or null if it's refused.
 */
function verify_request( string $body, string $signature, string $endpoint ): ?object {
	$payload = json_decode( $body );

	if ( ! is_object( $payload ) || ! is_valid_signature( $body, $signature ) || ! is_fresh( $payload ) ) {
		return null;
	}

	// Signed with the body, so it can't be sent to another endpoint.
	if ( ( $payload->endpoint ?? '' ) !== $endpoint ) {
		return null;
	}

	// Last, so only signed requests reach the cache.
	if ( ! is_unused_nonce( (string) ( $payload->nonce ?? '' ) ) ) {
		return null;
	}

	return $payload;
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
 * Whether a payload was sent recently.
 *
 * @param object $payload Decoded request payload.
 * @return bool
 */
function is_fresh( object $payload ): bool {
	$age = time() - (int) ( $payload->sent_at ?? 0 );

	// Any further ahead would outlive its nonce.
	return $age >= -MAX_CLOCK_SKEW && $age <= MAX_REQUEST_AGE;
}

/**
 * Whether a request's nonce is well-formed and wasn't seen before; records it.
 *
 * Without a persistent object cache, or while it's down, every nonce looks unused, and only the age check remains.
 *
 * @param string $nonce Hex-encoded random nonce.
 * @return bool
 */
function is_unused_nonce( string $nonce ): bool {
	if ( ! preg_match( '/^[0-9a-f]{32}$/', $nonce ) ) {
		return false;
	}

	// Global, as endpoints load different sites.
	wp_cache_add_global_groups( array( 'freescout-nonces' ) );

	// As long as a request with it could be fresh.
	if ( wp_cache_add( $nonce, 1, 'freescout-nonces', MAX_REQUEST_AGE + MAX_CLOCK_SKEW ) ) {
		return true;
	}

	// add() also fails while the cache is down; only a stored nonce counts as used.
	wp_cache_get( $nonce, 'freescout-nonces', false, $found );

	return ! $found;
}

/**
 * Sends a sidebar panel to FreeScout and ends the request.
 *
 * Content, not markup: WPOrgSidebar's sidebar.js builds the panel from these blocks.
 *
 * @param array $blocks Panel blocks.
 * @param array $extra  Other data for WPOrgSidebar, e.g. the sender's avatar.
 * @return never
 */
function send_panel( array $blocks, array $extra = array() ): never {
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( array_merge( $extra, array( 'blocks' => array_values( $blocks ) ) ) );
	exit;
}

/**
 * The ZIP of a plugin: the latest uploaded for review until it's released, and the latest release otherwise.
 *
 * A plugin in review, or approved but not yet committed to SVN, hasn't been released, so its uploads are only for
 * reviewers; for them, the URL carries the plugin's review info, which the review tools read. Rejected plugins have
 * none.
 *
 * @param \WP_Post $post   Plugin.
 * @param bool     $review Whether it's for a reviewer.
 * @return string Empty if there's no ZIP to download.
 */
function get_plugin_download_url( \WP_Post $post, bool $review ): string {
	if ( 'rejected' === $post->post_status ) {
		return '';
	}

	$url = "https://downloads.wordpress.org/plugin/{$post->post_name}.latest-stable.zip";

	if ( in_array( $post->post_status, array( 'new', 'pending', 'approved' ), true ) ) {
		$attachments = $review ? get_posts(
			array(
				'post_parent'    => $post->ID,
				'post_type'      => 'attachment',
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'posts_per_page' => 1,
			)
		) : array();
		$url         = $attachments ? (string) wp_get_attachment_url( $attachments[0]->ID ) : '';
	}

	if ( $url && $review && class_exists( '\WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review' ) ) {
		$url = \WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Review::append_plugin_review_info_url( $url, $post );
	}

	return $url;
}

/**
 * A plugin's status, as reviewers say it.
 *
 * @param \WP_Post $post   Plugin.
 * @param bool     $review Whether it's for a reviewer, who also sees why a plugin was closed.
 * @return string
 */
function get_plugin_status_label( \WP_Post $post, bool $review ): string {
	switch ( $post->post_status ) {
		case 'new':
		case 'pending':
			return 'In Review';
		case 'closed':
		case 'disabled':
			$label = ucwords( $post->post_status );
			// This is not perfect, but close enough.
			if ( $review && $post->_close_reason ) {
				$label .= ': ' . ucwords( str_replace( '-', ' ', $post->_close_reason ) );
			}

			return $label;
		case 'publish':
			return 'Published';
		default:
			return ucwords( $post->post_status );
	}
}

/**
 * Who's reviewing a plugin: only a review in progress has someone on it, as the assignment stays after it's done.
 *
 * @param \WP_Post $post Plugin.
 * @return string Their name, empty if no one is.
 */
function get_assigned_reviewer( \WP_Post $post ): string {
	if ( ! $post->assigned_reviewer || ! in_array( $post->post_status, array( 'new', 'pending' ), true ) ) {
		return '';
	}

	$reviewer = get_user_by( 'id', (int) $post->assigned_reviewer );
	if ( ! $reviewer ) {
		return '';
	}

	return $reviewer->display_name ? $reviewer->display_name : $reviewer->user_login;
}

/**
 * A conversation's row in WordPress.org's copy of helpdesk emails, which the plugin directory reads.
 *
 * The copy began with HelpScout's emails, so its columns, and the table's name, are HelpScout's; the values are the
 * same for FreeScout's conversations, but for the ID.
 *
 * @param object      $request Webhook payload, with the conversation in `email`; see WPOrgWebhooks' EventPayload.
 * @param object|null $row     The conversation's row so far, if it has one.
 * @param int         $user_id The sender's WordPress.org user ID, 0 if unknown.
 * @return array
 */
function get_email_row( object $request, ?object $row, int $user_id ): array {
	$email   = $request->email ?? new \stdClass();
	$sender  = $email->sender ?? new \stdClass();
	$address = (string) ( $sender->email ?? '' );
	$name    = trim( ( $sender->first_name ?? '' ) . ' ' . ( $sender->last_name ?? '' ) );

	$created = (int) strtotime( (string) ( $email->created_at ?? '' ) );
	$created = $created ? $created : (int) strtotime( (string) ( $row->created ?? '' ) );
	$closed  = (int) strtotime( (string) ( $email->closed_at ?? '' ) );
	$updated = (int) strtotime( (string) ( $email->user_updated_at ?? '' ) );
	$times   = array_filter( array( $created, $updated, $closed ) );

	return array(
		'id'       => (int) ( $request->conversation->id ?? 0 ),
		'number'   => (int) ( $email->number ?? 0 ),
		'user_id'  => $user_id,
		'mailbox'  => get_copy_mailbox_slug( $request ),
		'status'   => (string) ( $email->status ?? '' ),
		'email'    => $address ? ( $name ? "{$name} <{$address}>" : $address ) : (string) ( $row->email ?? '' ),
		'subject'  => (string) ( $email->subject ?? ( $row->subject ?? '' ) ),
		'preview'  => (string) ( $email->preview ?? ( $row->preview ?? '' ) ),
		'created'  => gmdate( 'Y-m-d H:i:s', $created ? $created : time() ),
		'closed'   => $closed ? gmdate( 'Y-m-d H:i:s', $closed ) : '',
		'modified' => gmdate( 'Y-m-d H:i:s', $times ? max( $times ) : time() ),
	);
}

/**
 * A status badge; WPOrgSidebar's stylesheet colors it by tone.
 *
 * @param string $label Badge text.
 * @param string $tone  One of success, warning, error, or neutral.
 * @return array
 */
function badge( string $label, string $tone = 'neutral' ): array {
	return array(
		'label' => $label,
		'tone'  => $tone,
	);
}

/**
 * A link in a panel.
 *
 * @param string $text Link text.
 * @param string $url  URL.
 * @return array
 */
function panel_link( string $text, string $url ): array {
	return array(
		'text' => $text,
		'url'  => $url,
	);
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
 * The slug of the mailbox WordPress.org's copy keeps a conversation in: the one it's in now, which the event carries in
 * `email`, rather than the one the event happened in, which stats count.
 *
 * @param object $request Webhook payload.
 * @return string
 */
function get_copy_mailbox_slug( object $request ): string {
	return sanitize_title( (string) ( $request->email->mailbox->name ?? ( $request->mailbox->name ?? '' ) ) );
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
 * Usually that's the sender, but bounces and Slack notifications are about someone else. Who that is comes from what
 * the sender wrote, so it's only used once an agent asks for it, with `related` in the payload.
 *
 * @param object $request Request payload.
 * @return string The user's email address, or the sender's if no user was found.
 */
function get_user_email_for_email( object $request ): string {
	$user = ! empty( $request->related ) ? get_related_user( $request ) : false;
	if ( ! $user ) {
		$user = get_sender_user( $request );
	}

	return $user ? $user->user_email : (string) ( $request->sender->email ?? '' );
}

/**
 * Gets the sender's WordPress.org user, by any of their addresses.
 *
 * @param object $request Request payload.
 * @return \WP_User|false
 */
function get_sender_user( object $request ): \WP_User|false {
	$sender = $request->sender ?? null;
	$email  = (string) ( $sender->email ?? '' );
	$user   = $email ? get_user_by( 'email', $email ) : false;

	if ( ! $user && ! empty( $sender->emails ) ) {
		$user = get_user_from_emails( array_map( 'strval', (array) $sender->emails ) );
	}

	return $user;
}

/**
 * Gets the WordPress.org user a bounce or Slack notification is about, if that's someone other than the sender.
 *
 * Goes by the sender's address, the subject, and the body, which the sender writes: it can name anyone.
 *
 * @param object $request Request payload.
 * @return \WP_User|false
 */
function get_related_user( object $request ): \WP_User|false {
	$subject = (string) ( $request->conversation->subject ?? '' );
	$email   = (string) ( $request->sender->email ?? '' );
	$sender  = get_sender_user( $request );
	$user    = false;

	// A Slack notification about a member.
	if (
		false !== stripos( $email, 'slack' ) &&
		preg_match( '/(\S+)@chat.wordpress.org/i', $subject, $m )
	) {
		$user = get_user_by( 'slug', $m[1] );
	} elseif (
		// @wordpress.org "users" send notifications, which may bounce.
		( ! $sender || str_ends_with( $sender->user_email, '@wordpress.org' ) ) &&
		$email &&
		is_bounce( $request )
	) {
		$user = get_user_from_bounce( $request );
	}

	// Neither someone at WordPress.org, nor the sender after all.
	if ( ! $user || str_ends_with( $user->user_email, '@wordpress.org' ) || ( $sender && $sender->ID === $user->ID ) ) {
		return false;
	}

	return $user;
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
 * Meta key naming the helpdesk a copied conversation is in, `freescout`; HelpScout's copies have none.
 *
 * @var string
 */
const HELPDESK_META_KEY = 'helpdesk';

/**
 * Meta key of a FreeScout conversation's copy naming a HelpScout conversation whose copy it replaced.
 *
 * HelpScout's webhook leaves alone a HelpScout conversation a copy names, so work done there since doesn't bring its
 * copy back. It goes with the copy that names it.
 *
 * @var string
 */
const REPLACED_HELPSCOUT_META_KEY = 'helpscout_conversation';

/**
 * What WordPress.org's copy does with a conversation's event: it's written, or deleted.
 *
 * @var string
 */
const COPY_DONE = 'done';

/**
 * What WordPress.org's copy does with a conversation's event: nothing yet, as another write held the conversation up,
 * or the write failed; FreeScout sends the event again.
 *
 * @var string
 */
const COPY_RETRY = 'retry';

/**
 * What WordPress.org's copy does with a conversation's event: nothing yet, as it has no copy of the conversation, and
 * the event only carries its newest threads; FreeScout sends it again with all of them.
 *
 * @var string
 */
const COPY_ALL_THREADS = 'all_threads';

/**
 * How long a write to WordPress.org's copy of a conversation waits for another to finish, in seconds.
 *
 * Well under FreeScout's 10-second timeout for an event, so a held-up event is answered, and sent again, rather than
 * written after FreeScout gave up on it, and sent again anyway.
 *
 * @var int
 */
const LOCK_TIMEOUT = 3;

/**
 * Takes the lock on WordPress.org's copy of a conversation, which writes to it hold, one at a time.
 *
 * The copy's meta table has no unique key to keep a pair from being added twice. The lock, and what's read while holding
 * it, come from the primary database, not a replica.
 *
 * @param int $id Conversation ID.
 * @return bool False if another write held it for too long.
 */
function lock_email( int $id ): bool {
	global $wpdb;

	if ( is_callable( array( $wpdb, 'send_reads_to_masters' ) ) ) {
		$wpdb->send_reads_to_masters();
	}

	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', 'wporg_helpscout_' . $id, LOCK_TIMEOUT ) );
}

/**
 * Releases the lock on WordPress.org's copy of a conversation.
 *
 * @param int $id Conversation ID.
 * @return void
 */
function unlock_email( int $id ): void {
	global $wpdb;

	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', 'wporg_helpscout_' . $id ) );
}

/**
 * Deletes conversations from WordPress.org's copy, with their meta, and so the HelpScout conversations they name.
 *
 * @param int[] $ids Conversation IDs; HelpScout's for the copies of its conversations.
 * @return void
 *
 * @throws \RuntimeException If a deletion failed.
 */
function delete_emails( array $ids ): void {
	global $wpdb;

	if ( ! $ids ) {
		return;
	}

	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholders are added for each ID.
	check_write( $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( "{$wpdb->base_prefix}helpscout" ), $ids ) ) ) );
	check_write( $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE helpscout_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( "{$wpdb->base_prefix}helpscout_meta" ), $ids ) ) ) );
	// phpcs:enable
}

/**
 * The copies an event's conversation replaces: one merged into it, and the HelpScout conversations it, or the one
 * merged into it, were imported from.
 *
 * @param object $request Webhook payload.
 * @return array[] FreeScout's IDs, under `freescout`, and HelpScout's, under `helpscout`.
 */
function get_replaced_ids( object $request ): array {
	return array(
		'freescout' => array_values( array_filter( array( (int) ( $request->merged_id ?? 0 ) ) ) ),
		'helpscout' => array_values(
			array_unique(
				array_filter(
					array(
						(int) ( $request->helpscout_id ?? 0 ),
						(int) ( $request->merged_helpscout_id ?? 0 ),
					)
				)
			)
		),
	);
}

/**
 * Whether WordPress.org needs all of a conversation's threads before it copies it: it has no copy of it yet, nor of
 * the HelpScout conversation it was imported from, which would have what the threads mention, and the event carries
 * only the newest.
 *
 * @param object      $request       Webhook payload.
 * @param object|null $row           The conversation's row in the copy, if it has one.
 * @param bool        $has_helpscout Whether the copy has the HelpScout conversation it was imported from.
 * @return bool
 */
function needs_all_threads( object $request, ?object $row, bool $has_helpscout ): bool {
	return ! $row && ! $has_helpscout && empty( $request->email->all_threads );
}

/**
 * What writing a conversation's copy adds to its meta, and which copies it deletes.
 *
 * It takes over what the copies it replaces mention, names the HelpScout conversations it replaces, and adds what its
 * threads mention. A HelpScout conversation it names already was replaced by an earlier event, so it isn't again.
 *
 * @param int     $id        Conversation ID.
 * @param array[] $replaced  The copies it replaces, see get_replaced_ids().
 * @param array[] $all_meta  The meta of it and of the copies it replaces: helpscout_id, meta_key, and meta_value.
 * @param array[] $mentioned The plugins' and themes' slugs its threads and subject mention, by type.
 * @return array Meta to add, as meta_key and meta_value pairs, under `meta`; IDs of the copies to delete, under `delete`.
 */
function plan_email_write( int $id, array $replaced, array $all_meta, array $mentioned ): array {
	$own      = array();
	$by_copy  = array();
	$replaces = array();
	foreach ( $all_meta as $pair ) {
		$key = $pair['meta_key'] . '|' . $pair['meta_value'];
		if ( (int) $pair['helpscout_id'] === $id ) {
			$own[ $key ] = true;
		} else {
			$by_copy[ (int) $pair['helpscout_id'] ][ $key ] = $pair;
		}

		if ( REPLACED_HELPSCOUT_META_KEY === $pair['meta_key'] && (int) $pair['helpscout_id'] === $id ) {
			$replaces[ (int) $pair['meta_value'] ] = true;
		}
	}

	// A HelpScout conversation the copy names was replaced already.
	$helpscout = array_values(
		array_filter(
			$replaced['helpscout'],
			static function ( int $helpscout_id ) use ( $replaces ): bool {
				return ! isset( $replaces[ $helpscout_id ] );
			}
		)
	);
	$delete    = array_merge( $replaced['freescout'], $helpscout );

	$meta = array();
	foreach ( $delete as $copy_id ) {
		foreach ( $by_copy[ $copy_id ] ?? array() as $key => $pair ) {
			$meta[ $key ] = array( $pair['meta_key'], $pair['meta_value'] );
		}
	}

	$meta[ HELPDESK_META_KEY . '|freescout' ] = array( HELPDESK_META_KEY, 'freescout' );
	foreach ( $replaced['helpscout'] as $helpscout_id ) {
		$meta[ REPLACED_HELPSCOUT_META_KEY . '|' . $helpscout_id ] = array( REPLACED_HELPSCOUT_META_KEY, (string) $helpscout_id );
	}

	foreach ( $mentioned as $type => $slugs ) {
		foreach ( $slugs as $slug ) {
			$meta[ $type . '|' . $slug ] = array( (string) $type, (string) $slug );
		}
	}

	return array(
		'meta'   => array_values( array_diff_key( $meta, $own ) ),
		'delete' => $delete,
	);
}

/**
 * What pointing WordPress.org at an imported conversation does to its copy.
 *
 * A conversation merged into another before the switch is sent as the one it went into, `merged`: its HelpScout one's
 * copy is merged into that one's, whose own HelpScout copy may not be renamed yet, so it takes over what that copy
 * mentions, as a merge after the switch does, rather than standing in for it.
 *
 * @param object $copy          The imported conversation: helpscout_id, id, number, delete, and merged.
 * @param bool   $has_helpscout Whether the copy has the HelpScout conversation.
 * @param bool   $has_freescout Whether it has the FreeScout one.
 * @param bool   $names_it      Whether the FreeScout one's copy names the HelpScout one, as it replaced it already.
 * @return string `skip`; `delete` both, as the conversation was deleted, or is spam; `rename` the HelpScout one's copy
 *                into the FreeScout one's; `merge` it into the FreeScout one's copy; or `name` it in that copy.
 */
function plan_copy_replacement( object $copy, bool $has_helpscout, bool $has_freescout, bool $names_it ): string {
	$helpscout_id = (int) ( $copy->helpscout_id ?? 0 );
	$id           = (int) ( $copy->id ?? 0 );
	if ( $helpscout_id <= 0 || $id <= 0 || $helpscout_id === $id ) {
		return 'skip';
	}

	if ( ! empty( $copy->delete ) ) {
		return $has_helpscout || $has_freescout ? 'delete' : 'skip';
	}

	if ( $has_helpscout ) {
		return $has_freescout || ! empty( $copy->merged ) ? 'merge' : 'rename';
	}

	// Its own events wrote the FreeScout one's copy, while HelpScout's was gone: it still keeps HelpScout's webhook out.
	return $has_freescout && ! $names_it ? 'name' : 'skip';
}

/**
 * Runs writes to WordPress.org's copy in a transaction, so none of them are seen, or kept, without the others.
 *
 * @param callable $write Writes; throws if one fails, see check_write().
 * @return void
 *
 * @throws \Throwable What the writes threw, once they're rolled back.
 */
function in_transaction( callable $write ): void {
	global $wpdb;

	check_write( $wpdb->query( 'START TRANSACTION' ) );

	try {
		$write();
	} catch ( \Throwable $e ) {
		$wpdb->query( 'ROLLBACK' );

		throw $e;
	}

	check_write( $wpdb->query( 'COMMIT' ) );
}

/**
 * Throws if a write failed: wpdb returns false instead.
 *
 * @param mixed $result What wpdb returned.
 * @return void
 *
 * @throws \RuntimeException If it failed.
 */
function check_write( mixed $result ): void {
	global $wpdb;

	if ( false === $result ) {
		throw new \RuntimeException( 'Could not write the copy: ' . (string) $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Logged, not shown.
	}
}
