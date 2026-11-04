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
 * it gives stays in sight once newer emails have pushed it down; the note itself stays where it is, dimmed. The choice
 * is a UserChoice.
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
	 * Where the request keeps the notes pinned on it, by their conversation's ID, so their threads are dimmed.
	 *
	 * @var string
	 */
	private const PINNED = 'wporgsite_pinned_notes';

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

		// Threads are timed to the second: an email of the same second came after the note if it came later in the table.
		$pushed_down = $conversation->threads()
			->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) )
			->where( 'state', Thread::STATE_PUBLISHED )
			->where(
				static function ( $query ) use ( $note ): void {
					$query->where( 'created_at', '>', $note->created_at )
						->orWhere(
							static function ( $query ) use ( $note ): void {
								$query->where( 'created_at', $note->created_at )->where( 'id', '>', $note->id );
							}
						);
				}
			)
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
		if ( ! UserChoice::get( $user, self::OPTION ) ) {
			return null;
		}

		$note = self::find( $conversation );
		if ( $note ) {
			$pinned                            = self::pinned();
			$pinned[ (int) $conversation->id ] = (int) $note->id;
			request()->attributes->set( self::PINNED, $pinned );
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
		return ( self::pinned()[ (int) $thread->conversation_id ] ?? null ) === (int) $thread->id;
	}

	/**
	 * The notes pinned on this request, by their conversation's ID.
	 *
	 * @return array<int, int> Note IDs.
	 */
	private static function pinned(): array {
		return (array) request()->attributes->get( self::PINNED, array() );
	}
}
