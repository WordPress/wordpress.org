<?php
/**
 * The latest note of a conversation, pinned to its top.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\Conversation;
use App\Thread;
use App\User;

/**
 * Agents who choose so in their profile see a copy of a conversation's latest note above its threads, so the context
 * it gives stays in sight once newer emails have pushed it down; the note itself stays where it is, dimmed.
 */
final class PinnedNote {

	/**
	 * Where an agent's choice is kept, followed by their user ID. Off, the default, isn't stored.
	 *
	 * @var string
	 */
	public const OPTION = 'wporgsite_pin_note_';

	/**
	 * The profile's field.
	 *
	 * @var string
	 */
	public const FIELD = 'wporgsite_pin_note';

	/**
	 * The notes pinned on this request, by their conversation's ID, so their threads are dimmed.
	 *
	 * @var array<int, int>
	 */
	private static array $pinned = array();

	/**
	 * Whether an agent pins the latest note.
	 *
	 * @param User $user Agent.
	 * @return bool Whether they do.
	 */
	public static function is_enabled( User $user ): bool {
		return UserChoice::get( $user, self::OPTION );
	}

	/**
	 * Sets whether an agent pins the latest note.
	 *
	 * @param User $user    Agent.
	 * @param bool $enabled Whether they do.
	 * @return void
	 */
	public static function set_enabled( User $user, bool $enabled ): void {
		UserChoice::set( $user, self::OPTION, $enabled );
	}

	/**
	 * Forgets an agent's choice, when they're deleted.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function forget( int $user_id ): void {
		UserChoice::forget( $user_id, self::OPTION );
	}

	/**
	 * A conversation's latest note, if an email came after it: core shows the newest threads first, so emails push it
	 * down. One that only status changes or drafts came after is near enough to the top already.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return Thread|null Note.
	 */
	public static function find( Conversation $conversation ): ?Thread {
		$note = $conversation->threads()
			->where( 'type', Thread::TYPE_NOTE )
			->where( 'state', Thread::STATE_PUBLISHED )
			->orderBy( 'created_at', 'desc' )
			->orderBy( 'id', 'desc' )
			->first();

		if ( ! $note instanceof Thread ) {
			return null;
		}

		$pushed_down = $conversation->threads()
			->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) )
			->where( 'state', Thread::STATE_PUBLISHED )
			->where( 'created_at', '>', $note->created_at )
			->exists();

		return $pushed_down ? $note : null;
	}

	/**
	 * The note to pin to a conversation's top for an agent, if they pin notes, and remembers it, to dim it below.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param User         $user         Agent.
	 * @return Thread|null Note.
	 */
	public static function pin( Conversation $conversation, User $user ): ?Thread {
		if ( ! self::is_enabled( $user ) ) {
			return null;
		}

		$note = self::find( $conversation );
		if ( $note ) {
			self::$pinned[ (int) $conversation->id ] = (int) $note->id;
		}

		return $note;
	}

	/**
	 * Whether a thread is the note pinned to its conversation's top on this request.
	 *
	 * @param Thread $thread Thread.
	 * @return bool Whether it is.
	 */
	public static function is_pinned( Thread $thread ): bool {
		return ( self::$pinned[ (int) $thread->conversation_id ] ?? null ) === (int) $thread->id;
	}

	/**
	 * Forgets the notes pinned on this request; for tests, which make several.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$pinned = array();
	}
}
