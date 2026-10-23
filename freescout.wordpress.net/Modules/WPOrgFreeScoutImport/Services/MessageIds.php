<?php
/**
 * Keeps the other FreeScout's emails' Message-IDs, which its API doesn't give, and gives them to imported threads.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

use App\Thread;
use Modules\WPOrgFreeScoutImport\Entities\ImportedThread;
use Modules\WPOrgFreeScoutImport\Entities\MessageId;

/**
 * Reads the file bin/export-message-ids.php writes on the other FreeScout: a line per email, its thread ID there and
 * its Message-ID, separated by a comma.
 *
 * A sender who replies to an old email names its Message-ID, which finds the thread it's in, and so the conversation.
 * Senders' emails keep their own. Replies the other FreeScout sent have one it made up from the thread ID and its
 * APP_KEY: core here can't tell it's one of FreeScout's, so it looks it up like a sender's.
 */
final class MessageIds {

	/**
	 * Rows written or threads read at a time.
	 *
	 * @var int
	 */
	private const CHUNK = 500;

	/**
	 * Keeps the Message-IDs in a file, replacing those kept for the same threads.
	 *
	 * @param string $path File, plain or gzipped.
	 * @return int How many were read.
	 *
	 * @throws \RuntimeException If it can't be read.
	 */
	public static function store( string $path ): int {
		$file = gzopen( $path, 'rb' );
		if ( ! $file ) {
			throw new \RuntimeException( 'Could not read the file.' );
		}

		$rows  = array();
		$count = 0;

		try {
			while ( ! gzeof( $file ) ) {
				$line = gzgets( $file );
				if ( false === $line ) {
					break;
				}

				$row = self::parse( $line );
				if ( ! $row ) {
					continue;
				}

				$rows[ $row['source_thread_id'] ] = $row;
				if ( count( $rows ) >= self::CHUNK ) {
					$count += self::write( $rows );
					$rows   = array();
				}
			}

			$count += self::write( $rows );
		} finally {
			gzclose( $file );
		}

		return $count;
	}

	/**
	 * The Message-IDs of source threads.
	 *
	 * @param int[] $source_thread_ids Source thread IDs.
	 * @return string[] Message-IDs, by source thread ID.
	 */
	public static function of( array $source_thread_ids ): array {
		if ( ! $source_thread_ids ) {
			return array();
		}

		return MessageId::query()
			->whereIn( 'source_thread_id', $source_thread_ids )
			->pluck( 'message_id', 'source_thread_id' )
			->all();
	}

	/**
	 * Gives imported threads without a Message-ID the one kept for them, unless another thread has it already.
	 *
	 * @return int How many threads got one.
	 */
	public static function apply(): int {
		$applied = 0;

		ImportedThread::query()
			->join( 'threads', 'threads.id', '=', 'wporgfreescoutimport_threads.thread_id' )
			->join( 'wporgfreescoutimport_message_ids', 'wporgfreescoutimport_message_ids.source_thread_id', '=', 'wporgfreescoutimport_threads.source_id' )
			->whereNull( 'threads.message_id' )
			->whereIn( 'threads.type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) )
			->select( 'wporgfreescoutimport_threads.id', 'wporgfreescoutimport_threads.thread_id', 'wporgfreescoutimport_message_ids.message_id' )
			->chunkById(
				self::CHUNK,
				static function ( \Illuminate\Support\Collection $rows ) use ( &$applied ): void {
					$taken = Thread::query()->whereIn( 'message_id', $rows->pluck( 'message_id' )->all() )->pluck( 'message_id' )->flip()->all();

					foreach ( $rows as $row ) {
						// Unique: an email in two mailboxes keeps its Message-ID where it is.
						if ( isset( $taken[ $row->message_id ] ) ) {
							continue;
						}

						$taken[ $row->message_id ] = true;

						$applied += Thread::query()->whereKey( (int) $row->thread_id )->whereNull( 'message_id' )->toBase()->update( array( 'message_id' => $row->message_id ) );
					}
				},
				'wporgfreescoutimport_threads.id',
				'id'
			);

		return $applied;
	}

	/**
	 * Reads a line of the file.
	 *
	 * @param string $line Line.
	 * @return array|null `source_thread_id` and `message_id`; null if it isn't one.
	 */
	private static function parse( string $line ): ?array {
		$fields = str_getcsv( trim( $line ), ',', '"', '' );
		if ( count( $fields ) !== 2 || ! ctype_digit( (string) $fields[0] ) ) {
			return null;
		}

		$message_id = trim( (string) $fields[1], " \t<>" );
		if ( '' === $message_id || preg_match( '/\s/', $message_id ) ) {
			return null;
		}

		return array(
			'source_thread_id' => (int) $fields[0],
			'message_id'       => mb_substr( $message_id, 0, 998 ),
		);
	}

	/**
	 * Writes rows, replacing those for the same threads.
	 *
	 * @param array[] $rows Rows, by source thread ID.
	 * @return int How many were written.
	 */
	private static function write( array $rows ): int {
		if ( ! $rows ) {
			return 0;
		}

		\DB::transaction(
			static function () use ( $rows ): void {
				MessageId::query()->whereIn( 'source_thread_id', array_keys( $rows ) )->delete();
				MessageId::query()->insert( array_values( $rows ) );
			}
		);

		return count( $rows );
	}
}
