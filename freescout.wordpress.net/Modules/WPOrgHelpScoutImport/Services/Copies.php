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
use Illuminate\Support\Str;
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
	 * A mailbox's switch.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return array|null Null if it hasn't switched. Keys: switched_at; and of the latest send, run (its token), sent,
	 *                    finished_at (null until it's done), and error (why WordPress.org refused a batch, which ends it).
	 */
	public static function get( int $mailbox_id ): ?array {
		// Bypasses the cache, which other processes' saves don't update.
		$state = Option::get( self::OPTION . $mailbox_id, null, true, false );

		return is_array( $state ) ? $state : null;
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
	 * Switches a mailbox, and sends all of its imported conversations to WordPress.org; again if it switched already,
	 * which stops the send before.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return void
	 */
	public static function start( int $mailbox_id ): void {
		$run = Str::random( 16 );
		self::change(
			$mailbox_id,
			static function ( ?array $state ) use ( $run ): array {
				return array(
					'switched_at' => (string) ( $state['switched_at'] ?? Carbon::now()->toDateTimeString() ),
					'run'         => $run,
					'sent'        => 0,
					'finished_at' => null,
					'error'       => '',
				);
			}
		);

		ReplaceCopies::dispatch( $mailbox_id, $run );
	}

	/**
	 * Sends a switched mailbox's imported conversations again, for what an import added or updated. Never throws: it
	 * runs as an import finishes.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return void
	 */
	public static function send_again( int $mailbox_id ): void {
		try {
			if ( self::get( $mailbox_id ) && self::available() ) {
				self::start( $mailbox_id );
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgHelpScoutImport] Could not point WordPress.org at mailbox ' . $mailbox_id . '\'s new conversations: ' . $e->getMessage() );
		}
	}

	/**
	 * Notes how far a send got, or that it's done, or failed.
	 *
	 * @param int    $mailbox_id FreeScout mailbox ID.
	 * @param string $run        The send's token.
	 * @param array  $changes    Keys of the switch to change: sent, finished_at, or error.
	 * @return bool False if a later send took over.
	 */
	public static function note( int $mailbox_id, string $run, array $changes ): bool {
		return null !== self::change(
			$mailbox_id,
			static function ( ?array $state ) use ( $run, $changes ): ?array {
				return $state && ( $state['run'] ?? '' ) === $run ? $changes + $state : null;
			}
		);
	}

	/**
	 * Changes a mailbox's switch under a row lock, so a send and the next one's start don't undo each other.
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
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @param int $after_id   ID of the last one sent.
	 * @param int $limit      How many.
	 * @return Collection With helpscout_id, conversation_id, and their conversation's number, state, and status.
	 */
	public static function batch( int $mailbox_id, int $after_id, int $limit ): Collection {
		$table = ( new ImportedConversation() )->getTable();

		return self::imported( $mailbox_id )
			->where( $table . '.id', '>', $after_id )
			->orderBy( $table . '.id' )
			->limit( $limit )
			->get(
				array(
					$table . '.id',
					$table . '.helpscout_id',
					$table . '.conversation_id',
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
	 * @return array HelpScout's ID, FreeScout's ID and number, and whether to delete the copy.
	 */
	public static function request( ImportedConversation $imported ): array {
		$request = array(
			'helpscout_id' => (int) $imported->helpscout_id,
			'id'           => (int) $imported->conversation_id,
			'number'       => (int) $imported->number,
			'delete'       => self::gone( (int) $imported->state, (int) $imported->status ),
		);

		$into = Conversation::STATE_DELETED === (int) $imported->state ? self::merged_into( (int) $imported->conversation_id ) : null;
		if ( $into ) {
			$request = array(
				'id'     => (int) $into->id,
				'number' => (int) $into->number,
				'delete' => self::gone( (int) $into->state, (int) $into->status ),
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
	 * The imported conversations in a mailbox.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return Builder
	 */
	private static function imported( int $mailbox_id ): Builder {
		$table = ( new ImportedConversation() )->getTable();

		return ImportedConversation::query()
			->join( 'conversations', 'conversations.id', '=', $table . '.conversation_id' )
			->where( 'conversations.mailbox_id', $mailbox_id );
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
