<?php
/**
 * Serializes conversations for the WordPress.org helpdesk endpoints.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Services;

use App\Conversation;
use App\Customer;
use App\Thread;
use App\User;
use Carbon\Carbon;

/**
 * Builds the sidebar request payload for a conversation.
 *
 * Read by api.wordpress.org/dotorg/freescout/; keep the two in sync.
 */
final class ConversationPayload {

	/**
	 * Maximum number of threads included, newest first.
	 *
	 * @var int
	 */
	public const MAX_THREADS = 50;

	/**
	 * Largest attachment whose content is included, in bytes.
	 *
	 * @var int
	 */
	public const MAX_ATTACHMENT_BYTES = 102400;

	/**
	 * Names for conversation statuses.
	 *
	 * @var array<int, string>
	 */
	private const STATUSES = array(
		Conversation::STATUS_ACTIVE  => 'active',
		Conversation::STATUS_PENDING => 'pending',
		Conversation::STATUS_CLOSED  => 'closed',
		Conversation::STATUS_SPAM    => 'spam',
	);

	/**
	 * Names for thread types.
	 *
	 * @var array<int, string>
	 */
	private const THREAD_TYPES = array(
		Thread::TYPE_CUSTOMER => 'customer',
		Thread::TYPE_MESSAGE  => 'message',
		Thread::TYPE_NOTE     => 'note',
		Thread::TYPE_CHAT     => 'chat',
	);

	/**
	 * Builds the payload for a conversation.
	 *
	 * @param Conversation $conversation Conversation to serialize.
	 * @return array
	 */
	public static function build( Conversation $conversation ): array {
		$mailbox = $conversation->mailbox;

		return array(
			'conversation' => array(
				'id'      => (int) $conversation->id,
				'number'  => (int) $conversation->number,
				'subject' => (string) $conversation->subject,
				'status'  => self::STATUSES[ (int) $conversation->status ] ?? 'active',
				'url'     => $conversation->url(),
			),
			'mailbox'      => array(
				'id'    => (int) $conversation->mailbox_id,
				'name'  => $mailbox ? (string) $mailbox->name : '',
				'email' => $mailbox ? (string) $mailbox->email : '',
			),
			'sender'       => self::sender( $conversation->customer, (string) $conversation->customer_email ),
			'threads'      => self::threads( $conversation ),
		);
	}

	/**
	 * Serializes the sender.
	 *
	 * @param Customer|null $customer Sender, if known.
	 * @param string        $email    Address the conversation is with.
	 * @return array
	 */
	private static function sender( ?Customer $customer, string $email ): array {
		if ( ! $customer ) {
			return array(
				'email'      => $email,
				'emails'     => $email ? array( $email ) : array(),
				'first_name' => '',
				'last_name'  => '',
			);
		}

		return array(
			'email'      => $email ? $email : $customer->getMainEmail(),
			'emails'     => $customer->emails->pluck( 'email' )->values()->all(),
			'first_name' => (string) $customer->first_name,
			'last_name'  => (string) $customer->last_name,
		);
	}

	/**
	 * Serializes the conversation's published threads, newest first.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return array
	 */
	private static function threads( Conversation $conversation ): array {
		$threads = $conversation->threads()
			->where( 'state', Thread::STATE_PUBLISHED )
			->whereIn( 'type', array_keys( self::THREAD_TYPES ) )
			->orderBy( 'created_at', 'desc' )
			->limit( self::MAX_THREADS )
			->with( array( 'attachments', 'created_by_user', 'created_by_customer' ) )
			->get();

		$items = array();
		foreach ( $threads as $thread ) {
			$type = self::THREAD_TYPES[ (int) $thread->type ];

			$items[] = array(
				'id'          => (int) $thread->id,
				'type'        => $type,
				'body'        => (string) $thread->body,
				'created_by'  => $thread->created_by_user_id
					? self::user( $thread->created_by_user )
					: self::customer( $thread->created_by_customer ),
				'created_at'  => self::date( (string) $thread->created_at ),
				'attachments' => 'customer' === $type ? self::attachments( $thread ) : array(),
			);
		}

		return $items;
	}

	/**
	 * Serializes an agent as a thread author.
	 *
	 * @param User|null $user Agent, if any.
	 * @return array|null
	 */
	private static function user( ?User $user ): ?array {
		return $user ? array(
			'type'  => 'user',
			'email' => (string) $user->email,
		) : null;
	}

	/**
	 * Serializes a sender as a thread author.
	 *
	 * @param Customer|null $customer Sender, if any.
	 * @return array|null
	 */
	private static function customer( ?Customer $customer ): ?array {
		return $customer ? array(
			'type'  => 'customer',
			'email' => $customer->getMainEmail(),
		) : null;
	}

	/**
	 * Serializes a thread's attachments.
	 *
	 * Small text-like attachments include their content, as bounces often carry the original recipient only there.
	 *
	 * @param Thread $thread Thread.
	 * @return array
	 */
	private static function attachments( Thread $thread ): array {
		$items = array();

		foreach ( $thread->attachments as $attachment ) {
			$mime_type = (string) $attachment->mime_type;
			$size      = (int) $attachment->size;
			$textual   = (bool) preg_match( '/message|text|rfc/', $mime_type );

			$items[] = array(
				'file_name' => (string) $attachment->file_name,
				'mime_type' => $mime_type,
				'size'      => $size,
				'content'   => $textual && $size <= self::MAX_ATTACHMENT_BYTES ? (string) $attachment->getFileContents() : null,
			);
		}

		return $items;
	}

	/**
	 * Formats a stored timestamp as an ISO 8601 UTC string.
	 *
	 * @param string $date Timestamp in the app timezone, or an empty string.
	 * @return string|null
	 */
	private static function date( string $date ): ?string {
		if ( '' === $date ) {
			return null;
		}

		return Carbon::parse( $date, config( 'app.timezone' ) )->setTimezone( 'UTC' )->format( 'Y-m-d\TH:i:s\Z' );
	}
}
