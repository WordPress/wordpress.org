<?php
/**
 * Imports a HelpScout mailbox's saved replies into the Saved Replies module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Mailbox;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgHelpScoutImport\Entities\ImportedSavedReply;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Exceptions\ApiError;
use Modules\WPOrgHelpScoutImport\Exceptions\RateLimited;

/**
 * Copies saved replies into the mailbox's, and brings them up to date when imported again.
 *
 * The Saved Replies module is a paid one: without it, there's nothing to import into. Its table is written directly,
 * so this doesn't depend on its classes.
 */
final class SavedReplies {

	/**
	 * Alias of the Saved Replies module.
	 *
	 * @var string
	 */
	public const MODULE = 'savedreplies';

	/**
	 * Imported.
	 *
	 * @var string
	 */
	public const IMPORTED = 'imported';

	/**
	 * Brought up to date with HelpScout's.
	 *
	 * @var string
	 */
	public const UPDATED = 'updated';

	/**
	 * Already up to date.
	 *
	 * @var string
	 */
	public const UNCHANGED = 'unchanged';

	/**
	 * Left as FreeScout has it: changed or deleted there, or FreeScout had one with its name already.
	 *
	 * @var string
	 */
	public const KEPT = 'kept';

	/**
	 * Couldn't be imported; the run keeps their HelpScout IDs, rather than a count, so a run that goes on after waiting
	 * doesn't try them again.
	 *
	 * @var string
	 */
	public const FAILED = 'failed';

	/**
	 * The Saved Replies module's table.
	 *
	 * @var string
	 */
	private const TABLE = 'saved_replies';

	/**
	 * Longest name the Saved Replies module keeps.
	 *
	 * @var int
	 */
	private const NAME_LENGTH = 75;

	/**
	 * HelpScout API client.
	 *
	 * @var HelpScout
	 */
	private $helpscout;

	/**
	 * Importer, which copies their images.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Users, for the robot that's credited with them.
	 *
	 * @var People
	 */
	private $people;

	/**
	 * Constructor.
	 *
	 * @param HelpScout $helpscout HelpScout API client.
	 * @param Importer  $importer  Importer.
	 * @param People    $people    Users.
	 */
	public function __construct( HelpScout $helpscout, Importer $importer, People $people ) {
		$this->helpscout = $helpscout;
		$this->importer  = $importer;
		$this->people    = $people;
	}

	/**
	 * Whether the Saved Replies module is on, so there's somewhere to import them into.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		try {
			return (bool) \App\Module::isActive( self::MODULE ) && Schema::hasTable( self::TABLE );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Imports a HelpScout mailbox's saved replies for email, or brings them up to date, counting them on the run.
	 *
	 * Those the run checked already, before it had to wait for the rate limit, aren't read again.
	 *
	 * @param Run     $run     Run, whose HelpScout mailbox they're from.
	 * @param Mailbox $mailbox FreeScout mailbox to import them into.
	 * @return void
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited; what's done so far is kept.
	 */
	public function import( Run $run, Mailbox $mailbox ): void {
		$helpscout_mailbox_id = (int) $run->helpscout_mailbox_id;
		$counts               = (array) $run->saved_replies;

		try {
			$list = $this->helpscout->saved_replies( $helpscout_mailbox_id );
		} catch ( ApiError $e ) {
			if ( self::holds_for_all( $e ) ) {
				throw $e;
			}

			// The conversations are imported: a list HelpScout won't give doesn't undo that.
			self::fail( $run, 'HelpScout\'s saved replies', $e );

			return;
		}

		foreach ( $list as $listed ) {
			$id       = (int) ( $listed['id'] ?? 0 );
			$imported = $id ? ImportedSavedReply::query()->where( 'helpscout_id', $id )->where( 'mailbox_id', $mailbox->id )->first() : null;
			$failed   = array_map( 'intval', (array) ( $counts[ self::FAILED ] ?? array() ) );
			if ( ! $id || ( $imported && (int) $imported->run_id === (int) $run->id ) || in_array( $id, $failed, true ) ) {
				continue;
			}

			try {
				$result = $this->import_one( $helpscout_mailbox_id, $id, $imported, $mailbox, (int) $run->id );
			} catch ( ApiError $e ) {
				if ( self::holds_for_all( $e ) ) {
					$run->saved_replies = $counts;

					throw $e;
				}

				$result = self::fail( $run, 'HelpScout saved reply ' . $id, $e );
			} catch ( \Throwable $e ) {
				$result = self::fail( $run, 'HelpScout saved reply ' . $id, $e );
			}

			if ( self::FAILED === $result ) {
				$counts[ self::FAILED ] = array_merge( $failed, array( $id ) );
			} elseif ( $result ) {
				$counts[ $result ] = (int) ( $counts[ $result ] ?? 0 ) + 1;
			}
		}

		$run->saved_replies = $counts;
	}

	/**
	 * Imports one saved reply, or brings it up to date.
	 *
	 * @param int                     $helpscout_mailbox_id HelpScout mailbox ID.
	 * @param int                     $id                   HelpScout saved reply ID.
	 * @param ImportedSavedReply|null $imported             Where an earlier import put it.
	 * @param Mailbox                 $mailbox              FreeScout mailbox.
	 * @param int                     $run_id               Run ID.
	 * @return string|null One of the constants; null if HelpScout deleted it since it was listed.
	 *
	 * @throws \Throwable If HelpScout didn't give it (an ApiError), or it couldn't be written; the images it copied are deleted then.
	 */
	private function import_one( int $helpscout_mailbox_id, int $id, ?ImportedSavedReply $imported, Mailbox $mailbox, int $run_id ): ?string {
		$source = $this->helpscout->saved_reply( $helpscout_mailbox_id, $id );
		if ( ! $source ) {
			return null;
		}

		$name        = mb_substr( trim( (string) ( $source['name'] ?? '' ) ), 0, self::NAME_LENGTH );
		$text        = (string) ( $source['text'] ?? '' );
		$source_hash = ImportedSavedReply::hash( $name, $text );
		$existing    = $imported ? \DB::table( self::TABLE )->where( 'id', $imported->saved_reply_id )->first() : null;

		if ( $imported ) {
			// Deleted or changed in FreeScout, or moved to another mailbox: it stays as it is.
			$kept = ! $existing
				|| (int) $existing->mailbox_id !== (int) $mailbox->id
				|| ImportedSavedReply::hash( (string) $existing->name, (string) $existing->text ) !== $imported->written_hash;

			if ( $kept || $source_hash === $imported->source_hash ) {
				$imported->run_id = $run_id;
				$imported->save();

				return $kept ? self::KEPT : self::UNCHANGED;
			}
		} else {
			// FreeScout has one by that name already; names are unique in a mailbox. It's kept as FreeScout's, unless
			// it's another HelpScout saved reply's, whose name was the same once cut to length.
			$same_name      = \DB::table( self::TABLE )->where( 'mailbox_id', $mailbox->id )->where( 'name', $name )->value( 'id' );
			$another_import = $same_name && ImportedSavedReply::query()->where( 'saved_reply_id', $same_name )->whereNotNull( 'written_hash' )->exists();
			if ( $same_name && ! $another_import ) {
				ImportedSavedReply::query()->create(
					array(
						'helpscout_id'   => $id,
						'mailbox_id'     => $mailbox->id,
						'saved_reply_id' => (int) $same_name,
						'source_hash'    => $source_hash,
						'run_id'         => $run_id,
					)
				);

				return self::KEPT;
			}
		}

		$name                           = self::unique_name( $name, (int) $mailbox->id, $existing ? (int) $existing->id : 0 );
		$robot_id                       = (int) $this->people->robot()->id;
		$earlier                        = $imported ? (array) json_decode( (string) $imported->images, true ) : array();
		list( $text, $images, $copies ) = $this->importer->copy_images( \Helper::stripDangerousTags( $text ), $robot_id, $earlier );
		$now                            = Carbon::now();

		try {
			\DB::transaction(
				function () use ( $existing, $id, $name, $text, $source_hash, $copies, $mailbox, $robot_id, $run_id, $now ): void {
					if ( $existing ) {
						\DB::table( self::TABLE )->where( 'id', $existing->id )->update(
							array(
								'name'       => $name,
								'text'       => $text,
								'updated_at' => $now,
							)
						);
						$saved_reply_id = (int) $existing->id;
					} else {
						$saved_reply_id = (int) \DB::table( self::TABLE )->insertGetId(
							array(
								'mailbox_id' => $mailbox->id,
								'name'       => $name,
								'text'       => $text,
								'user_id'    => $robot_id,
								// Last, like the module adds a new one.
								'sort_order' => (int) \DB::table( self::TABLE )->where( 'mailbox_id', $mailbox->id )->max( 'sort_order' ) + 1,
								'created_at' => $now,
								'updated_at' => $now,
							)
						);
					}

					ImportedSavedReply::query()->updateOrCreate(
						array(
							'helpscout_id' => $id,
							'mailbox_id'   => $mailbox->id,
						),
						array(
							'saved_reply_id' => $saved_reply_id,
							'source_hash'    => $source_hash,
							'written_hash'   => ImportedSavedReply::hash( $name, $text ),
							'images'         => $copies ? json_encode( $copies, JSON_UNESCAPED_SLASHES ) : null,
							'run_id'         => $run_id,
						)
					);
				}
			);
		} catch ( \Throwable $e ) {
			// Only the new copies: those made before are still the saved reply's.
			\App\Attachment::deleteForever( $images );

			throw $e;
		}

		return $existing ? self::UPDATED : self::IMPORTED;
	}

	/**
	 * A name no other saved reply in the mailbox has, with a number added if it's taken.
	 *
	 * @param string $name       Name, at most as long as the module keeps.
	 * @param int    $mailbox_id FreeScout mailbox ID.
	 * @param int    $except_id  The saved reply it's for, or 0 for a new one.
	 * @return string
	 */
	private static function unique_name( string $name, int $mailbox_id, int $except_id ): string {
		$candidate = $name;
		$number    = 1;

		while ( self::name_taken( $candidate, $mailbox_id, $except_id ) ) {
			$suffix    = ' (' . ( ++$number ) . ')';
			$candidate = mb_substr( $name, 0, self::NAME_LENGTH - mb_strlen( $suffix ) ) . $suffix;
		}

		return $candidate;
	}

	/**
	 * Whether another saved reply in the mailbox has a name.
	 *
	 * @param string $name       Name.
	 * @param int    $mailbox_id FreeScout mailbox ID.
	 * @param int    $except_id  The saved reply it's for, or 0 for a new one.
	 * @return bool
	 */
	private static function name_taken( string $name, int $mailbox_id, int $except_id ): bool {
		return \DB::table( self::TABLE )->where( 'mailbox_id', $mailbox_id )->where( 'name', $name )->where( 'id', '!=', $except_id )->exists();
	}

	/**
	 * Whether HelpScout's error holds for every request, like the rate limit, rejected credentials, or an outage.
	 *
	 * @param ApiError $e Error.
	 * @return bool
	 */
	private static function holds_for_all( ApiError $e ): bool {
		return $e instanceof RateLimited || 401 === $e->status || 0 === $e->status || $e->status >= 500;
	}

	/**
	 * Logs what couldn't be imported, and keeps its error on the run.
	 *
	 * @param Run        $run  Run.
	 * @param string     $what What it was, like `HelpScout saved reply 123`.
	 * @param \Throwable $e    What went wrong.
	 * @return string FAILED.
	 */
	private static function fail( Run $run, string $what, \Throwable $e ): string {
		$run->last_error = mb_substr( $what . ': ' . Run::describe( $e ), 0, 400 );
		\Log::error( '[WPOrgHelpScoutImport] Could not import ' . $run->last_error );

		return self::FAILED;
	}
}
