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
 * Switches a mailbox's conversations in WordPress.org's copy from HelpScout to FreeScout, in batches on the queue.
 *
 * Until a mailbox switches, its imported conversations' events leave the copy alone, so it keeps pointing at HelpScout.
 */
final class Copies {

	/**
	 * Option name of a mailbox's switch, before the mailbox ID.
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
	 * Status of a switch WordPress.org refused; start() resumes it.
	 *
	 * @var string
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * A mailbox's switch.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return array|null Null if it hasn't switched. Keys: status, after_id (last sent), sent, since (null for all),
	 *                    started_at, finished_at, error, and pending (the since of a send queued behind this one).
	 */
	public static function get( int $mailbox_id ): ?array {
		// Bypasses the cache, which other processes' saves don't update.
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
	 * Whether requests to WordPress.org can be signed, which WPOrgWebhooks does.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( Client::class ) && Client::from_config()->is_configured();
	}

	/**
	 * Starts sending a mailbox's imported conversations to WordPress.org, or resumes a failed send.
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
	 * Sends what an import added or updated, if the mailbox switched. Never throws: it runs as an import finishes.
	 *
	 * Updated conversations are sent again, as HelpScout's webhook may have rewritten their copies. A running send gets
	 * them queued behind it; a failed one restarts with them.
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

					// A failed send still owes its own range; a finished one doesn't.
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
	 * Ends a finished send, or starts the one queued behind it.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return bool Whether a queued send started.
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
	 * Marks a send WordPress.org refused as failed; start() resumes it.
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
	 * @param string|null $since Only conversations imported or updated since then; null for all.
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
	 * The wider of two since values: the earlier time, or null for all.
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
	 * Changes a mailbox's switch under a row lock, so jobs, imports, and the button don't undo each other.
	 *
	 * @param int      $mailbox_id FreeScout mailbox ID.
	 * @param callable $change     Takes the switch, or null, and returns it changed, or null to leave it.
	 * @return array|null The switch as changed; null if it wasn't.
	 */
	private static function change( int $mailbox_id, callable $change ): ?array {
		$name = self::OPTION . $mailbox_id;

		try {
			Option::query()->firstOrCreate( array( 'name' => $name ), array( 'value' => '' ) );
		} catch ( QueryException $e ) {
			// Another process created it first.
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
	 * Notes the mailboxes of imported conversations about to be deleted for good, so a switch still sends their deletion.
	 *
	 * @param int[] $conversation_ids Conversation IDs.
	 * @return void
	 */
	public static function remember_mailboxes( array $conversation_ids ): void {
		foreach ( array_chunk( array_map( 'intval', $conversation_ids ), 500 ) as $ids ) {
			$by_mailbox = Conversation::query()->whereIn( 'id', $ids )->get( array( 'id', 'mailbox_id' ) )->groupBy( 'mailbox_id' );

			foreach ( $by_mailbox as $mailbox_id => $conversations ) {
				// toBase() leaves updated_at alone, which marks updates.
				ImportedConversation::query()
					->toBase()
					->whereIn( 'conversation_id', $conversations->pluck( 'id' )->all() )
					->update( array( 'mailbox_id' => (int) $mailbox_id ) );
			}
		}
	}

	/**
	 * Marks an imported conversation as updated, for catch_up().
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
	 * @param string|null $since      Only those imported or updated since then.
	 * @return Collection With helpscout_id, conversation_id, and their conversation's found_id (null if deleted for
	 *                    good), number, state, and status.
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
	 * What WordPress.org is told about an imported conversation: the FreeScout conversation that replaces its HelpScout
	 * copy, or that the copy goes, as it's deleted or spam.
	 *
	 * One merged away is sent as the conversation it went into, which takes over its copy's plugins and themes.
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
	 * The conversation one was merged into, following further merges.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return Conversation|null Null if it wasn't merged away, or the one it went into was deleted for good.
	 */
	private static function merged_into( int $conversation_id ): ?Conversation {
		$into = null;

		// Merges are manual, so chains are short.
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
	 * Whether a conversation's events carry it for WordPress.org's copy: an imported one's only once its mailbox switches.
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
