<?php
/**
 * What Akismet is told about an email.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Services;

use App\Conversation;
use App\Thread;

/**
 * Describes the email that started a conversation the way Akismet describes a contact form submission.
 */
final class Message {

	/**
	 * Builds the fields Akismet is sent, the same way for checking and for reporting.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param Thread       $thread       The sender's email that started it.
	 * @return array|null Fields, or null without an IP address to send: Akismet requires one.
	 */
	public static function fields( Conversation $conversation, Thread $thread ): ?array {
		$ip = self::sender_ip( (string) $thread->headers );
		if ( ! $ip ) {
			return null;
		}

		$sender = $conversation->customer;

		$fields = array(
			'blog'                 => (string) config( 'app.url' ),
			'blog_charset'         => 'UTF-8',
			'user_ip'              => $ip,
			'comment_type'         => 'contact-form',
			'comment_author'       => $sender ? (string) $sender->getFullName() : '',
			'comment_author_email' => (string) $conversation->customer_email,
			'comment_content'      => trim( $conversation->subject . "\n\n" . \Helper::htmlToText( (string) $thread->body ) ),
		);

		if ( $thread->created_at ) {
			$fields['comment_date_gmt'] = gmdate( 'c', $thread->created_at->getTimestamp() );
		}

		return $fields;
	}

	/**
	 * Finds the address the email entered the mail system from, in its Received headers.
	 *
	 * Mail servers add a Received header at the top for each hop, so the oldest public address is the furthest from
	 * WordPress.org's own servers. Addresses there are only as trustworthy as the servers that wrote them; Akismet
	 * treats the IP as one signal among many.
	 *
	 * @param string $headers Raw email headers.
	 * @return string|null Public IP address, or null if there's none.
	 */
	public static function sender_ip( string $headers ): ?string {
		// Unfold continuation lines, so each header is on one line.
		$headers = (string) preg_replace( '/\r?\n[ \t]+/', ' ', $headers );

		preg_match_all( '/^Received:(.*)$/mi', $headers, $received );

		foreach ( array_reverse( $received[1] ) as $hop ) {
			// The address of the server that sent it is in the "from" part, before the receiving server's "by".
			$from = preg_split( '/\sby\s/i', $hop, 2 )[0];

			preg_match_all( '/\[(?:IPv6:)?([0-9a-f:.]+)\]|\((\d{1,3}(?:\.\d{1,3}){3})\)/i', $from, $candidates, PREG_SET_ORDER );

			foreach ( $candidates as $candidate ) {
				$ip = '' !== $candidate[1] ? $candidate[1] : ( $candidate[2] ?? '' );

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return null;
	}
}
