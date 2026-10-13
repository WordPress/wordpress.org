<?php
/**
 * Points WordPress.org's copies of HelpScout's conversations at the conversations imported from them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Conversation;
use App\Option;
use App\Thread;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Jobs\ReplaceCopies;
use Modules\WPOrgWebhooks\Services\Client;

/**
 * WordPress.org keeps a copy of conversations, which the plugin directory links to, and replies to uploads in. Until a
 * mailbox switches to FreeScout, the copies of its conversations are HelpScout's; switching sends WordPress.org which
 * FreeScout conversation each was imported as, in batches, on the queue, and which were deleted or are spam since, whose
 * copies go.
 *
 * Until then, imported conversations' events don't carry them for the copy, so WordPress.org goes on pointing at
 * HelpScout; after, they do, with the HelpScout ID whose copy they replace, as do conversations imported later.
 */
final class Copies {

	/**
	 * Option keeping a mailbox's switch, followed by its ID: status, how far it got, and when.
	 *
	 * @var string
	 */
	private const OPTION = 'wporghelpscoutimport_copies_';

	/**
	 * Status of a switch being sent.
	 *
	 * @var string
	 */
	public const STATUS_RUNNING = 'running';

	/**
	 * Status of a switch sent in full.
	 *
	 * @var string
	 */
	public const STATUS_DONE = 'done';

	/**
	 * Status of a switch WordPress.org didn't take, which can carry on where it stopped.
	 *
	 * @var string
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * A mailbox's switch.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return array|null Status, after_id (the last imported conversation sent), sent, since (only conversations imported
	 *                    since then; null for all), started_at, finished_at, error, and pending, when imports asked for a
	 *                    send while one ran: the since of the send after it. Null if it hasn't switched.
	 */
	public static function get( int $mailbox_id ): ?array {
		// Not from the cache, which saving doesn't update, and the queue's jobs change.
		$state = Option::get( self::OPTION . $mailbox_id, null, true, false );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Saves a mailbox's switch.
	 *
	 * @param int   $mailbox_id FreeScout mailbox ID.
	 * @param array $state      Its state, see get().
	 * @return void
	 */
	public static function save( int $mailbox_id, array $state ): void {
		Option::set( self::OPTION . $mailbox_id, $state );
	}

	/**
	 * Whether WordPress.org can be sent copies at all: WPOrgWebhooks signs the requests.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( Client::class ) && Client::from_config()->is_configured();
	}

	/**
	 * Starts sending a mailbox's imported conversations to WordPress.org, or carries on where a failed send stopped.
	 *
	 * Under a lock on its state, so a press of the button and an import finishing at once don't both start sending.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return bool False if it's already running.
	 */
	public static function start( int $mailbox_id ): bool {
		$started = self::change(
			$mailbox_id,
			static function ( ?array $state ): ?array {
				if ( $state && self::STATUS_RUNNING === $state['status'] ) {
					return null;
				}

				return array(
					'status'      => self::STATUS_RUNNING,
					'after_id'    => (int) ( $state['after_id'] ?? 0 ),
					'sent'        => (int) ( $state['sent'] ?? 0 ),
					'since'       => $state['since'] ?? null,
					'started_at'  => (string) ( $state['started_at'] ?? Carbon::now()->toDateTimeString() ),
					'finished_at' => null,
					'error'       => '',
				) + array_intersect_key( (array) $state, array( 'pending' => true ) );
			}
		);

		if ( $started ) {
			ReplaceCopies::dispatch( $mailbox_id );
		}

		return (bool) $started;
	}

	/**
	 * Sends what an import added, or imported again, since a mailbox switched, if it has. Never throws: it runs as an
	 * import finishes.
	 *
	 * Imported again, a conversation's copy may be HelpScout's again: HelpScout's webhook writes it while anyone works on
	 * it there. While a send runs, the conversations are sent after it, from the start, as they may be behind it; a
	 * send that failed is started again, from the start, with them, so neither its conversations nor these are left out.
	 *
	 * @param int    $mailbox_id FreeScout mailbox ID.
	 * @param string $since      When the import started.
	 * @return void
	 */
	public static function catch_up( int $mailbox_id, string $since ): void {
		try {
			if ( ! self::get( $mailbox_id ) || ! self::available() ) {
				return;
			}

			$restarted = false;
			self::change(
				$mailbox_id,
				static function ( ?array $state ) use ( $since, &$restarted ): ?array {
					if ( ! $state ) {
						return null;
					}

					if ( self::STATUS_RUNNING === $state['status'] ) {
						$state['pending'] = array_key_exists( 'pending', $state ) ? self::wider( $state['pending'], $since ) : $since;

						return $state;
					}

					// A failed send hadn't sent everything since its own start; done, it had.
					$wider = self::STATUS_FAILED === $state['status'] ? self::wider( $state['since'] ?? null, $since ) : $since;
					if ( array_key_exists( 'pending', $state ) ) {
						$wider = self::wider( $state['pending'], $wider );
					}

					$restarted = true;

					return self::restart( $state, $wider );
				}
			);

			if ( $restarted ) {
				ReplaceCopies::dispatch( $mailbox_id );
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgHelpScoutImport] Could not point WordPress.org at mailbox ' . $mailbox_id . '\'s new conversations: ' . $e->getMessage() );
		}
	}

	/**
	 * Notes how far a send got.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @param int $after_id   The last imported conversation sent.
	 * @param int $count      How many the batch sent.
	 * @return void
	 */
	public static function advance( int $mailbox_id, int $after_id, int $count ): void {
		self::change(
			$mailbox_id,
			static function ( ?array $state ) use ( $after_id, $count ): ?array {
				if ( ! $state || self::STATUS_RUNNING !== $state['status'] ) {
					return null;
				}

				$state['after_id'] = $after_id;
				$state['sent']     = (int) $state['sent'] + $count;

				return $state;
			}
		);
	}

	/**
	 * Ends a send that has nothing left, or starts the one imports asked for while it ran.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return bool Whether another send started, whose batches are to be queued.
	 */
	public static function finish( int $mailbox_id ): bool {
		$state = self::change(
			$mailbox_id,
			static function ( ?array $state ): ?array {
				if ( ! $state || self::STATUS_RUNNING !== $state['status'] ) {
					return null;
				}

				if ( array_key_exists( 'pending', $state ) ) {
					return self::restart( $state, $state['pending'] );
				}

				$state['status']      = self::STATUS_DONE;
				$state['finished_at'] = Carbon::now()->toDateTimeString();

				return $state;
			}
		);

		return $state && self::STATUS_RUNNING === $state['status'];
	}

	/**
	 * Stops a send WordPress.org didn't take, which can carry on where it stopped.
	 *
	 * @param int    $mailbox_id FreeScout mailbox ID.
	 * @param string $error      What went wrong.
	 * @return void
	 */
	public static function fail( int $mailbox_id, string $error ): void {
		self::change(
			$mailbox_id,
			static function ( ?array $state ) use ( $error ): ?array {
				if ( ! $state || self::STATUS_RUNNING !== $state['status'] ) {
					return null;
				}

				$state['status'] = self::STATUS_FAILED;
				$state['error']  = $error;

				return $state;
			}
		);
	}

	/**
	 * A send started again from the first imported conversation.
	 *
	 * @param array       $state The switch.
	 * @param string|null $since Send the conversations imported, or imported again, since then; null for all.
	 * @return array
	 */
	private static function restart( array $state, ?string $since ): array {
		unset( $state['pending'] );

		return array(
			'status'      => self::STATUS_RUNNING,
			'after_id'    => 0,
			'sent'        => 0,
			'since'       => $since,
			'finished_at' => null,
			'error'       => '',
		) + $state;
	}

	/**
	 * The earlier of two times, which takes in more imported conversations; null, for all of them, takes in any.
	 *
	 * @param string|null $one   A time; null for all.
	 * @param string|null $other Another.
	 * @return string|null
	 */
	private static function wider( ?string $one, ?string $other ): ?string {
		if ( null === $one || null === $other ) {
			return null;
		}

		return Carbon::parse( $one )->lessThan( Carbon::parse( $other ) ) ? $one : $other;
	}

	/**
	 * Changes a mailbox's switch under a lock on it, so the queue's jobs, imports, and the button don't undo each other.
	 *
	 * @param int      $mailbox_id FreeScout mailbox ID.
	 * @param callable $change     Takes the switch, null if it hasn't switched, and returns it changed, or null to leave
	 *                             it.
	 * @return array|null The switch as changed; null if it wasn't.
	 */
	private static function change( int $mailbox_id, callable $change ): ?array {
		$name = self::OPTION . $mailbox_id;

		try {
			Option::query()->firstOrCreate( array( 'name' => $name ), array( 'value' => '' ) );
		} catch ( QueryException $e ) {
			// Created at the same time by another.
			unset( $e );
		}

		return \DB::transaction(
			static function () use ( $name, $change ): ?array {
				$option = Option::query()->where( 'name', $name )->lockForUpdate()->first();
				if ( ! $option ) {
					return null;
				}

				$state   = Option::maybeUnserialize( $option->value );
				$changed = $change( is_array( $state ) ? $state : null );
				if ( null === $changed ) {
					return null;
				}

				$option->value = Option::maybeSerialize( $changed );
				$option->save();

				return $changed;
			}
		);
	}

	/**
	 * Notes the mailboxes of imported conversations about to be deleted for good, so a switch of their mailbox sends
	 * WordPress.org their deletion.
	 *
	 * @param int[] $conversation_ids Conversation IDs.
	 * @return void
	 */
	public static function remember_mailboxes( array $conversation_ids ): void {
		foreach ( array_chunk( array_map( 'intval', $conversation_ids ), 500 ) as $ids ) {
			$by_mailbox = Conversation::query()->whereIn( 'id', $ids )->get( array( 'id', 'mailbox_id' ) )->groupBy( 'mailbox_id' );

			foreach ( $by_mailbox as $mailbox_id => $conversations ) {
				// Not as imported again, which its updated_at says.
				ImportedConversation::query()
					->toBase()
					->whereIn( 'conversation_id', $conversations->pluck( 'id' )->all() )
					->update( array( 'mailbox_id' => (int) $mailbox_id ) );
			}
		}
	}

	/**
	 * Marks a conversation as imported again, for catch_up().
	 *
	 * @param Conversation $conversation Conversation.
	 * @return void
	 */
	public static function touch( Conversation $conversation ): void {
		ImportedConversation::query()->where( 'conversation_id', $conversation->id )->update( array( 'updated_at' => Carbon::now() ) );
	}

	/**
	 * How many conversations were imported into a mailbox.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return int
	 */
	public static function total( int $mailbox_id ): int {
		return self::imported( $mailbox_id )->count();
	}

	/**
	 * The next conversations imported into a mailbox to send, in the order they were imported.
	 *
	 * @param int         $mailbox_id FreeScout mailbox ID.
	 * @param int         $after_id   ID of the last one sent.
	 * @param int         $limit      How many.
	 * @param string|null $since      Only those imported, or imported again, since then.
	 * @return Collection Imported conversations, with their ID, helpscout_id, conversation_id, and their conversation's
	 *                    found_id (null if it was deleted for good), number, state, and status; see request().
	 */
	public static function batch( int $mailbox_id, int $after_id, int $limit, ?string $since = null ): Collection {
		$table = ( new ImportedConversation() )->getTable();

		return self::imported( $mailbox_id )
			->where( $table . '.id', '>', $after_id )
			->when(
				null !== $since,
				static function ( Builder $query ) use ( $table, $since ): Builder {
					return $query->where( $table . '.updated_at', '>=', $since );
				}
			)
			->orderBy( $table . '.id' )
			->limit( $limit )
			->get(
				array(
					$table . '.id',
					$table . '.helpscout_id',
					$table . '.conversation_id',
					'conversations.id as found_id',
					'conversations.number',
					'conversations.state',
					'conversations.status',
				)
			);
	}

	/**
	 * What WordPress.org is told about an imported conversation: which FreeScout conversation replaces the copy of its
	 * HelpScout one, or that the copy goes, as the conversation was deleted, for good or not, or is spam.
	 *
	 * One merged into another, which deletes it, has its HelpScout one's copy merged into the other's instead, which
	 * takes over the plugins and themes it mentions, like a merge after the switch.
	 *
	 * @param ImportedConversation $imported An imported conversation, as batch() reads it.
	 * @return array HelpScout's ID, FreeScout's ID and number, whether to delete the copy, and whether it's merged into
	 *               the conversation that ID names.
	 */
	public static function request( ImportedConversation $imported ): array {
		$request = array(
			'helpscout_id' => (int) $imported->helpscout_id,
			'id'           => (int) $imported->conversation_id,
			'number'       => (int) $imported->number,
			'delete'       => ! $imported->found_id || self::gone( (int) $imported->state, (int) $imported->status ),
			'merged'       => false,
		);

		$into = $imported->found_id && Conversation::STATE_DELETED === (int) $imported->state ? self::merged_into( (int) $imported->conversation_id ) : null;
		if ( $into ) {
			$request = array(
				'id'     => (int) $into->id,
				'number' => (int) $into->number,
				'delete' => self::gone( (int) $into->state, (int) $into->status ),
				'merged' => true,
			) + $request;
		}

		return $request;
	}

	/**
	 * Whether a conversation's copy goes: it's deleted, or spam.
	 *
	 * @param int $state  Its state.
	 * @param int $status Its status.
	 * @return bool
	 */
	private static function gone( int $state, int $status ): bool {
		return Conversation::STATE_DELETED === $state || Conversation::STATUS_SPAM === $status;
	}

	/**
	 * The conversation one deleted by a merge was merged into, following it on if that one was merged away too.
	 *
	 * Core notes the merge on the conversation merged away, in a line item's `META_MERGED_INTO_CONV` meta.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return Conversation|null Null if it wasn't merged away, or the one it went into was deleted for good.
	 */
	private static function merged_into( int $conversation_id ): ?Conversation {
		$into = null;

		// Few hops: conversations are merged by hand.
		for ( $hops = 0; $hops < 10; $hops++ ) {
			$merges = Thread::query()
				->where( 'conversation_id', $conversation_id )
				->where( 'type', Thread::TYPE_LINEITEM )
				->where( 'action_type', Thread::ACTION_TYPE_MERGED )
				->orderBy( 'id', 'desc' )
				->get();

			$next = 0;
			foreach ( $merges as $merge ) {
				$next = (int) $merge->getMeta( Thread::META_MERGED_INTO_CONV, 0 );
				if ( $next ) {
					break;
				}
			}

			$next_conversation = $next ? Conversation::find( $next ) : null;
			if ( ! $next_conversation ) {
				return $into;
			}

			$into            = $next_conversation;
			$conversation_id = $next;
			if ( Conversation::STATE_DELETED !== (int) $into->state ) {
				return $into;
			}
		}

		return $into;
	}

	/**
	 * The conversations imported into a mailbox: those in it, and those deleted for good from it.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return Builder
	 */
	private static function imported( int $mailbox_id ): Builder {
		$table = ( new ImportedConversation() )->getTable();

		return ImportedConversation::query()
			->leftJoin( 'conversations', 'conversations.id', '=', $table . '.conversation_id' )
			->where(
				static function ( Builder $query ) use ( $table, $mailbox_id ): void {
					$query->where( 'conversations.mailbox_id', $mailbox_id )
						->orWhere(
							static function ( Builder $gone ) use ( $table, $mailbox_id ): void {
								$gone->whereNull( 'conversations.id' )->where( $table . '.mailbox_id', $mailbox_id );
							}
						);
				}
			);
	}

	/**
	 * Whether a conversation's events carry it for WordPress.org's copy: an imported one's only once its mailbox switched.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $mailbox_id      Its mailbox's ID.
	 * @return bool
	 */
	public static function sends( int $conversation_id, int $mailbox_id ): bool {
		return null !== self::get( $mailbox_id )
			|| ! ImportedConversation::query()->where( 'conversation_id', $conversation_id )->exists();
	}
}
