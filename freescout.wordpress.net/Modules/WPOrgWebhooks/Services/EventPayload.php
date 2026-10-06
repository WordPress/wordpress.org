<?php
/**
 * Serializes conversation events.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Services;

use App\Conversation;
use App\User;
use Modules\WPOrgSSO\Entities\Account;

/**
 * Builds the webhook payload for a conversation event.
 *
 * Read by api.wordpress.org/dotorg/freescout/webhook.php; keep the two in sync.
 */
final class EventPayload {

	/**
	 * Builds the payload.
	 *
	 * @param string       $event        Event name: the FreeScout hook that fired.
	 * @param Conversation $conversation Conversation the event happened on.
	 * @param User|null    $agent        Agent who caused the event, if any.
	 * @return array
	 */
	public static function build( string $event, Conversation $conversation, ?User $agent ): array {
		$mailbox = $conversation->mailbox;

		return array(
			'event'        => $event,
			'conversation' => array(
				'id' => (int) $conversation->id,
			),
			'mailbox'      => array(
				'id'   => (int) $conversation->mailbox_id,
				'name' => $mailbox ? (string) $mailbox->name : '',
			),
			'agent'        => $agent ? array(
				'id'             => (int) $agent->id,
				'email'          => (string) $agent->email,
				'wporg_username' => self::wporg_username( $agent ),
			) : null,
		);
	}

	/**
	 * The WordPress.org account WPOrgSSO connected an agent to, which contributor stats credit.
	 *
	 * @param User $agent Agent.
	 * @return string Username, empty if they aren't connected, or WPOrgSSO isn't installed.
	 */
	private static function wporg_username( User $agent ): string {
		try {
			return class_exists( Account::class ) ? Account::username_for( (int) $agent->id ) : '';
		} catch ( \Throwable $e ) {
			// Its table may not be migrated; the event goes out anyway.
			return '';
		}
	}
}
