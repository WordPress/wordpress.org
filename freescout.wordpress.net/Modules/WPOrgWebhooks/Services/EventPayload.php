<?php
/**
 * Serializes conversation events.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Services;

use App\Conversation;
use App\Thread;
use App\User;
use Carbon\Carbon;
use Modules\WPOrgSSO\Entities\Account;

/**
 * Builds the webhook payload for a conversation event.
 *
 * Read by api.wordpress.org/dotorg/freescout/webhook.php; keep the two in sync. Carries the event, for contributor
 * stats, and the conversation, for WordPress.org's copy of it.
 */
final class EventPayload {

	/**
	 * Most threads an event carries.
	 *
	 * @var int
	 */
	private const MAX_THREADS = 50;

	/**
	 * Most bytes of thread text an event carries; past it, the oldest threads are cut short or left out.
	 *
	 * @var int
	 */
	private const MAX_TEXT_BYTES = 256 * 1024;

	/**
	 * Events that carry all of a conversation's threads: the copy has none of it yet, or it moved to another mailbox,
	 * which changes what it mentions. See reads_all() for the rest.
	 *
	 * @var string[]
	 */
	private const ALL_THREADS_EVENTS = array(
		'conversation.created_by_customer',
		'conversation.created_by_user',
		'conversation.restored',
		'conversation.moved',
	);

	/**
	 * Events that carry the threads since the previous reply: the reply, and the notes before it. Events not listed carry
	 * none.
	 *
	 * @var string[]
	 */
	private const NEW_THREADS_EVENTS = array(
		'conversation.customer_replied',
		'conversation.user_replied',
	);

	/**
	 * Events that carry only the thread they're about.
	 *
	 * @var string[]
	 */
	private const OWN_THREAD_EVENTS = array( 'conversation.note_added' );

	/**
	 * Statuses, as WordPress.org's copy names them.
	 *
	 * @var string[]
	 */
	private const STATUSES = array(
		Conversation::STATUS_ACTIVE  => 'active',
		Conversation::STATUS_PENDING => 'pending',
		Conversation::STATUS_CLOSED  => 'closed',
		Conversation::STATUS_SPAM    => 'spam',
	);

	/**
	 * Thread types, by name.
	 *
	 * @var string[]
	 */
	private const THREAD_TYPES = array(
		Thread::TYPE_CUSTOMER => 'customer',
		Thread::TYPE_MESSAGE  => 'message',
		Thread::TYPE_NOTE     => 'note',
	);

	/**
	 * Builds the payload.
	 *
	 * The conversation is added when the event is sent; see refresh().
	 *
	 * @param string       $event        Event name: the FreeScout hook that fired.
	 * @param Conversation $conversation Conversation the event happened on.
	 * @param User|null    $agent        Agent who caused the event, if any.
	 * @param array        $extra        Event details, like the ID of a conversation merged into this one.
	 * @return array
	 */
	public static function build( string $event, Conversation $conversation, ?User $agent, array $extra = array() ): array {
		return $extra + array(
			'event'        => $event,
			'conversation' => array(
				'id' => (int) $conversation->id,
			),
			'mailbox'      => self::mailbox( $conversation ),
			'agent'        => $agent ? array(
				'id'             => (int) $agent->id,
				'email'          => (string) $agent->email,
				'wporg_username' => self::wporg_username( $agent ),
			) : null,
		);
	}

	/**
	 * Adds the conversation as it is now: events can be sent late or out of order, and the copy takes what the latest
	 * says. The event's own mailbox stays, for stats.
	 *
	 * @param array $payload The payload; `all_threads` if WordPress.org asked for them.
	 * @return array
	 */
	public static function refresh( array $payload ): array {
		$id           = (int) ( $payload['conversation']['id'] ?? 0 );
		$conversation = Conversation::find( $id );
		$mailbox_id   = $conversation ? (int) $conversation->mailbox_id : (int) ( $payload['mailbox']['id'] ?? 0 );

		/**
		 * Filters whether an event carries the conversation for WordPress.org's copy; it counts for stats either way.
		 *
		 * @param bool              $copy            Whether it does.
		 * @param Conversation|null $conversation    Conversation; null if it was deleted for good since the event.
		 * @param int               $conversation_id Its ID.
		 * @param int               $mailbox_id      Its mailbox's ID; the event's, if it was deleted for good.
		 */
		if ( ! \Eventy::filter( 'wporgwebhooks.copy', true, $conversation, $id, $mailbox_id ) ) {
			unset( $payload['email'] );

			return $payload;
		}

		$helpscout_id = self::helpscout_id( $conversation, $id );
		if ( $helpscout_id && empty( $payload['helpscout_id'] ) ) {
			$payload['helpscout_id'] = $helpscout_id;
		}

		// The conversation merged into this one may have a HelpScout copy too.
		$merged_id = (int) ( $payload['merged_id'] ?? 0 );
		if ( $merged_id && empty( $payload['merged_helpscout_id'] ) ) {
			$merged_helpscout_id = self::helpscout_id( Conversation::find( $merged_id ), $merged_id );
			if ( $merged_helpscout_id ) {
				$payload['merged_helpscout_id'] = $merged_helpscout_id;
			}
		}

		$payload['email'] = $conversation ? self::email( $conversation, $payload ) : array( 'state' => 'deleted' );

		return $payload;
	}

	/**
	 * The ID of the HelpScout conversation a conversation was imported from, whose copy its own replaces.
	 *
	 * @param Conversation|null $conversation    Conversation; null if it was deleted for good.
	 * @param int               $conversation_id Its ID.
	 * @return int 0 if it wasn't imported.
	 */
	private static function helpscout_id( ?Conversation $conversation, int $conversation_id ): int {
		/**
		 * Filters the ID of the HelpScout conversation a conversation was imported from.
		 *
		 * @param int               $helpscout_id    HelpScout's ID; 0 if it wasn't imported.
		 * @param Conversation|null $conversation    Conversation; null if it was deleted for good.
		 * @param int               $conversation_id Its ID.
		 */
		return (int) \Eventy::filter( 'wporgwebhooks.helpscout_id', 0, $conversation, $conversation_id );
	}

	/**
	 * The conversation's mailbox.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return array
	 */
	private static function mailbox( Conversation $conversation ): array {
		$mailbox = $conversation->mailbox;

		return array(
			'id'   => (int) $conversation->mailbox_id,
			'name' => $mailbox ? (string) $mailbox->name : '',
		);
	}

	/**
	 * The conversation, as WordPress.org keeps a copy of it.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param array        $payload      The event's payload.
	 * @return array
	 */
	private static function email( Conversation $conversation, array $payload ): array {
		$customer = $conversation->customer;
		$all      = self::reads_all( $payload );

		return array(
			'mailbox'         => self::mailbox( $conversation ),
			'state'           => Conversation::STATE_DELETED === (int) $conversation->state ? 'deleted' : 'published',
			'number'          => (int) $conversation->number,
			'subject'         => (string) $conversation->subject,
			'status'          => self::STATUSES[ (int) $conversation->status ] ?? 'active',
			'preview'         => (string) $conversation->preview,
			'created_at'      => self::date( $conversation->created_at ),
			'closed_at'       => self::date( $conversation->closed_at ),
			'user_updated_at' => self::date( $conversation->user_updated_at ),
			'sender'          => array(
				'email'      => (string) $conversation->customer_email,
				'emails'     => $customer ? $customer->emails->pluck( 'email' )->values()->all() : array(),
				'first_name' => $customer ? (string) $customer->first_name : '',
				'last_name'  => $customer ? (string) $customer->last_name : '',
			),
			'all_threads'     => $all,
			'threads'         => self::threads( $conversation, $payload, $all ),
		);
	}

	/**
	 * Whether WordPress.org reads all of a conversation's threads after an event.
	 *
	 * @param array $payload The event's payload.
	 * @return bool
	 */
	private static function reads_all( array $payload ): bool {
		return ! empty( $payload['all_threads'] )
			|| ! empty( $payload['unspammed'] )
			|| in_array( (string) ( $payload['event'] ?? '' ), self::ALL_THREADS_EVENTS, true );
	}

	/**
	 * The threads WordPress.org reads after an event, newest first, as text.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param array        $payload      The event's payload: its name, and the reply it's about.
	 * @param bool         $all          Whether WordPress.org reads all of them; see reads_all().
	 * @return array[] Type, and body, as text; see text().
	 */
	private static function threads( Conversation $conversation, array $payload, bool $all ): array {
		$event     = (string) ( $payload['event'] ?? '' );
		$thread_id = (int) ( $payload['thread_id'] ?? 0 );
		$own       = ! $all && in_array( $event, self::OWN_THREAD_EVENTS, true );
		if ( ! $all && ! $own && ! in_array( $event, self::NEW_THREADS_EVENTS, true ) ) {
			return array();
		}

		$query = $conversation->threads()
			->where( 'state', Thread::STATE_PUBLISHED )
			->whereIn( 'type', array_keys( self::THREAD_TYPES ) );

		// A reply's threads end at it, even if more came before the event was sent.
		if ( $own ) {
			$query->where( 'id', $thread_id );
		} elseif ( ! $all && $thread_id ) {
			$query->where( 'id', '<=', $thread_id )->orderBy( 'id', 'desc' );
		} else {
			$query->orderBy( 'created_at', 'desc' )->orderBy( 'id', 'desc' );
		}

		$sent    = array();
		$replies = 0;
		$budget  = self::MAX_TEXT_BYTES;
		foreach ( $query->limit( self::MAX_THREADS )->get( array( 'type', 'body' ) ) as $thread ) {
			$is_reply = Thread::TYPE_NOTE !== (int) $thread->type;
			if ( ( ! $all && $is_reply && ++$replies > 1 ) || $budget <= 0 ) {
				break;
			}

			$text    = mb_strcut( self::text( (string) $thread->body ), 0, $budget, 'UTF-8' );
			$budget -= strlen( $text );
			$sent[]  = array(
				'type' => self::THREAD_TYPES[ (int) $thread->type ],
				'body' => $text,
			);
		}

		return $sent;
	}

	/**
	 * A thread's HTML as text, for finding the plugins and themes it mentions: a line per block, and link addresses on
	 * lines of their own.
	 *
	 * Angle brackets are removed, so stripping tags on WordPress.org can't swallow text.
	 *
	 * @param string $html The thread's body.
	 * @return string
	 */
	public static function text( string $html ): string {
		$html = mb_scrub( $html, 'UTF-8' );
		$html = (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $html );

		preg_match_all( '#\bhref\s*=\s*(["\'])(.*?)\1#is', $html, $links );

		$text = (string) preg_replace( '#<br\b[^>]*>|</(?:p|div|li|tr|h[1-6]|blockquote|pre)\s*>#i', "\n", $html );
		$text = self::decode( strip_tags( $text ) );

		$urls = array();
		foreach ( $links[2] as $url ) {
			$url = trim( self::decode( $url ) );
			if ( '' !== $url && ! str_contains( $text, $url ) ) {
				$urls[ $url ] = true;
			}
		}

		$text = (string) preg_replace( '/[ \t\x{00A0}]+/u', ' ', $text . "\n" . implode( "\n", array_keys( $urls ) ) );

		return trim( (string) preg_replace( '/ *\n\s*/', "\n", $text ) );
	}

	/**
	 * Decodes HTML entities, and takes out angle brackets; see text().
	 *
	 * @param string $text Text with entities.
	 * @return string
	 */
	private static function decode( string $text ): string {
		return str_replace( array( '<', '>' ), ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * A stored time, in UTC, ISO 8601.
	 *
	 * @param mixed $date Time in the app's timezone, as a Carbon instance or a string; empty for none.
	 * @return string|null
	 */
	private static function date( $date ): ?string {
		if ( ! $date ) {
			return null;
		}

		return Carbon::parse( (string) $date, config( 'app.timezone' ) )->setTimezone( 'UTC' )->format( 'Y-m-d\TH:i:s\Z' );
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
