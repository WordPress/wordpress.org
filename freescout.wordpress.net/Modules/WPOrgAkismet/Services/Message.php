<?php
/**
 * What Akismet is told about an email.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Services;

use App\Thread;

/**
 * Describes the email that started a conversation the way Akismet describes a contact form submission.
 */
final class Message {

	/**
	 * Builds the fields Akismet is sent, the same way for checking and for reporting.
	 *
	 * Everything comes from the email itself, not the conversation, whose sender changes with every reply, and whose
	 * subject agents can edit.
	 *
	 * @param Thread $thread  The sender's email that started the conversation.
	 * @param string $subject The conversation's subject when it was checked.
	 * @return array|null Fields, or null without an IP address to send: Akismet requires one.
	 */
	public static function fields( Thread $thread, string $subject ): ?array {
		$ip = self::sender_ip( (string) $thread->headers );
		if ( ! $ip ) {
			return null;
		}

		$sender = $thread->created_by_customer;

		$fields = array(
			'blog'                 => (string) config( 'app.url' ),
			'blog_charset'         => 'UTF-8',
			'user_ip'              => $ip,
			'comment_type'         => 'contact-form',
			'comment_author'       => $sender ? (string) $sender->getFullName() : '',
			'comment_author_email' => (string) $thread->from,
			'comment_content'      => trim( \Helper::htmlToText( (string) $thread->body ) ),
			// Like WordPress.com's support contact form, which sends the subject apart from the message.
			'contact_form_subject' => $subject,
		);

		if ( $thread->created_at ) {
			$fields['comment_date_gmt'] = gmdate( 'c', $thread->created_at->getTimestamp() );
		}

		return $fields;
	}

	/**
	 * Finds the server that handed the email to WordPress.org, in its Received headers.
	 *
	 * Mail servers add a Received header at the top for each hop, so the newest ones are written by WordPress.org's
	 * own servers, and older ones by whoever sent the email, who can write anything there. So the headers are read
	 * from the top, past private addresses and WordPress.org's relays, and the first other server is the sender's.
	 * A header with a sending server that can't be read stops the search: below it, the sender may have written them.
	 *
	 * @param string $headers Raw email headers.
	 * @return string|null Public IP address, or null if there's none.
	 */
	public static function sender_ip( string $headers ): ?string {
		// Unfold continuation lines, so each header is on one line.
		$headers = (string) preg_replace( '/\r?\n[ \t]+/', ' ', $headers );

		preg_match_all( '/^Received:(.*)$/mi', $headers, $received );

		foreach ( $received[1] as $hop ) {
			// The sending server is in the "from" part, before the receiving server's "by".
			$from = preg_split( '/\sby\s/i', $hop, 2 )[0];

			// Local delivery, like "by imap.wordpress.org with LMTP", names no sending server.
			if ( ! preg_match( '/^\s*from\s/i', $from ) ) {
				continue;
			}

			$sender = self::sending_server( $from );
			if ( ! $sender ) {
				\Log::warning( '[WPOrgAkismet] Could not read the sending server in a Received header: ' . trim( $hop ) );

				return null;
			}

			list( $host, $ip ) = $sender;

			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) || self::is_relay( $host ) ) {
				continue;
			}

			return $ip;
		}

		return null;
	}

	/**
	 * Reads the address the receiving server saw the sending server connect from, and its reverse DNS name.
	 *
	 * Only what the receiving server recorded counts, not the name the sender announced (HELO):
	 * Postfix and Sendmail write "from HELO (rDNS [IP])", Exim "from rDNS ([IP] helo=HELO)" or "from [IP] (helo=HELO)".
	 * Without reverse DNS, Sendmail writes "from HELO ([IP])": the address counts, the name doesn't.
	 *
	 * @param string $from The "from" part of a Received header.
	 * @return array|null Reverse DNS name (or '') and IP address, or null if the hop has neither.
	 */
	private static function sending_server( string $from ): ?array {
		$ip_pattern = '(?:IPv6:)?([0-9a-f:.]+)';

		$patterns = array(
			// Postfix and Sendmail.
			'/\(\s*([^\s()\[\]=]+?)\.?\s+\[' . $ip_pattern . '\]/i' => array( 1, 2 ),
			// Exim, with and without a reverse DNS name.
			'/^\s*from\s+([^\s()\[\]]+?)\.?\s+\(\[' . $ip_pattern . '\]\s+helo=/i' => array( 1, 2 ),
			'/^\s*from\s+\[' . $ip_pattern . '\]/i' => array( null, 1 ),
			// Sendmail without reverse DNS, whose name is the HELO.
			'/\(\s*\[' . $ip_pattern . '\]/i'       => array( null, 1 ),
			// Servers that only write the address, like "from example.org (93.184.216.34)".
			'/\((\d{1,3}(?:\.\d{1,3}){3})\)/'       => array( null, 1 ),
		);

		foreach ( $patterns as $pattern => $groups ) {
			if ( preg_match( $pattern, $from, $match ) && filter_var( $match[ $groups[1] ], FILTER_VALIDATE_IP ) ) {
				return array( null === $groups[0] ? '' : strtolower( $match[ $groups[0] ] ), $match[ $groups[1] ] );
			}
		}

		return null;
	}

	/**
	 * Whether a reverse DNS name belongs to one of WordPress.org's own mail servers.
	 *
	 * @param string $host Reverse DNS name.
	 * @return bool
	 */
	private static function is_relay( string $host ): bool {
		foreach ( (array) config( 'wporgakismet.relays', array() ) as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}

		return false;
	}
}
