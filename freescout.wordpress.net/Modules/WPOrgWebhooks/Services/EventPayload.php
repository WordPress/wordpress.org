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
				'id'    => (int) $agent->id,
				'email' => (string) $agent->email,
			) : null,
		);
	}
}
