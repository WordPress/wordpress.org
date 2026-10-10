<?php
/**
 * Links to the helpdesk the plugins team emails authors from.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Tools;

use WP_User;

/**
 * Links to FreeScout once its Plugins mailbox is configured, with `FREESCOUT_PLUGINS_MAILBOX_ID`, and to Help Scout until
 * then, so the switch is a configuration change.
 */
class Helpdesk {

	/**
	 * FreeScout's address, unless `FREESCOUT_URL` says otherwise.
	 *
	 * @var string
	 */
	const FREESCOUT_URL = 'https://freescout.wordpress.net/';

	/**
	 * Help Scout's search.
	 *
	 * @var string
	 */
	const HELPSCOUT_SEARCH_URL = 'https://secure.helpscout.net/search/';

	/**
	 * Help Scout's form for a new conversation in the Plugins mailbox.
	 *
	 * @var string
	 */
	const HELPSCOUT_NEW_URL = 'https://secure.helpscout.net/mailbox/ad3e85554c5bd064/new-ticket/';

	/**
	 * The ID of FreeScout's Plugins mailbox.
	 *
	 * @return int 0 while the plugins team is on Help Scout.
	 */
	public static function freescout_mailbox_id(): int {
		$mailbox_id = defined( 'FREESCOUT_PLUGINS_MAILBOX_ID' ) ? (int) FREESCOUT_PLUGINS_MAILBOX_ID : 0;

		/**
		 * Filters the ID of FreeScout's Plugins mailbox; links go to Help Scout while it's 0.
		 *
		 * @param int $mailbox_id Mailbox ID.
		 */
		return (int) apply_filters( 'wporg_plugins_freescout_mailbox_id', $mailbox_id );
	}

	/**
	 * Whether links go to FreeScout.
	 *
	 * @return bool
	 */
	public static function is_freescout(): bool {
		return self::freescout_mailbox_id() > 0;
	}

	/**
	 * The helpdesk's name.
	 *
	 * @return string
	 */
	public static function name(): string {
		return self::is_freescout() ? 'FreeScout' : 'Help Scout';
	}

	/**
	 * The helpdesk's short name, for links that need little room.
	 *
	 * @return string
	 */
	public static function short_name(): string {
		return self::is_freescout() ? 'FS' : 'HS';
	}

	/**
	 * A search of the Plugins mailbox.
	 *
	 * @param string $term What to search for.
	 * @return string URL.
	 */
	public static function search_url( string $term ): string {
		if ( ! self::is_freescout() ) {
			return self::HELPSCOUT_SEARCH_URL . '?query=mailbox:Plugins%20' . rawurlencode( $term );
		}

		return self::freescout_url() . 'search?' . http_build_query(
			array(
				'q' => $term,
				'f' => array( 'mailbox' => self::freescout_mailbox_id() ),
			)
		);
	}

	/**
	 * The form for a new conversation with a plugin's author, from the Plugins mailbox.
	 *
	 * FreeScout's form can't be given a name, or copies, so there the committers are recipients like the author.
	 *
	 * @param WP_User  $author    The author.
	 * @param string[] $cc_emails The other committers' email addresses.
	 * @param string   $subject   Subject.
	 * @return string URL.
	 */
	public static function new_conversation_url( WP_User $author, array $cc_emails, string $subject ): string {
		if ( ! self::is_freescout() ) {
			// Help Scout wants spaces as + signs, which http_build_query() writes.
			return self::HELPSCOUT_NEW_URL . '?' . http_build_query(
				array(
					'name'    => $author->display_name,
					'email'   => $author->user_email,
					'cc'      => implode( ',', $cc_emails ),
					'subject' => $subject,
				)
			);
		}

		return self::freescout_url() . 'mailbox/' . self::freescout_mailbox_id() . '/new-ticket?' . http_build_query(
			array(
				'to'      => implode( ',', array_merge( array( $author->user_email ), $cc_emails ) ),
				'subject' => $subject,
			)
		);
	}

	/**
	 * A conversation of WordPress.org's copy of helpdesk emails, in its helpdesk.
	 *
	 * The copy holds both helpdesks' conversations while the plugins team moves: FreeScout's webhook marks its own, and
	 * its import replaces the copies of HelpScout's.
	 *
	 * @param int  $id        Conversation ID.
	 * @param int  $number    Conversation number, which HelpScout's links carry.
	 * @param bool $freescout Whether it's FreeScout's.
	 * @return string URL.
	 */
	public static function conversation_url( int $id, int $number, bool $freescout ): string {
		if ( ! $freescout ) {
			return 'https://secure.helpscout.net/conversation/' . $id . '/' . $number;
		}

		return self::freescout_url() . 'conversation/' . $id;
	}

	/**
	 * FreeScout's address, with a trailing slash.
	 *
	 * @return string
	 */
	public static function freescout_url(): string {
		return trailingslashit( defined( 'FREESCOUT_URL' ) && FREESCOUT_URL ? FREESCOUT_URL : self::FREESCOUT_URL );
	}
}
