<?php
/**
 * An import of one HelpScout mailbox into a FreeScout mailbox.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Entities;

use App\Mailbox;
use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporghelpscoutimport_runs.
 *
 * @property int              $id
 * @property int              $helpscout_mailbox_id
 * @property string           $helpscout_mailbox_name
 * @property int              $mailbox_id
 * @property int|null         $user_id     Administrator who started it.
 * @property string           $status      One of the STATUS_ constants.
 * @property \Carbon\Carbon|null $since    Only conversations HelpScout changed since then.
 * @property int              $page        Next page of HelpScout's conversation list.
 * @property int[]|null       $page_done     HelpScout IDs done on that page.
 * @property int[]|null       $previous_page HelpScout IDs the previous page listed.
 * @property int|null         $pages
 * @property int|null         $total
 * @property int              $imported
 * @property int              $updated
 * @property int              $skipped
 * @property int              $failed
 * @property int[]|null       $skips       Skipped conversations, by why.
 * @property string[]|null    $failures    Failed conversations' errors, by HelpScout ID.
 * @property string|null      $last_error
 * @property array|null       $saved_replies Saved replies counted by what happened to them, like `imported`, and failed ones' HelpScout IDs; null if none were read.
 * @property int[]|null       $retry_ids   HelpScout IDs to import again, for a run that retries another's failures.
 * @property int|null         $waiting_on  HelpScout ID of a conversation the rate limit cut off part way.
 * @property int|null         $attempting  HelpScout ID of the conversation being imported.
 * @property int              $attempts    How often its import started.
 * @property int              $page_failures Pages HelpScout failed in a row.
 * @property string|null      $token       Identifies the run's current chain of jobs.
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $finished_at
 */
final class Run extends Model {

	/**
	 * Importing, page by page.
	 *
	 * @var string
	 */
	public const STATUS_RUNNING = 'running';

	/**
	 * Stopped by an administrator; resumes where it stopped.
	 *
	 * @var string
	 */
	public const STATUS_PAUSED = 'paused';

	/**
	 * Every page was imported.
	 *
	 * @var string
	 */
	public const STATUS_DONE = 'done';

	/**
	 * Stopped by an error that a retry won't fix, like rejected credentials.
	 *
	 * @var string
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * Stopped for good by an administrator.
	 *
	 * @var string
	 */
	public const STATUS_CANCELLED = 'cancelled';

	/**
	 * How long a running run can go without saving progress before it's considered stalled, in minutes.
	 *
	 * Its job can have died, like when the queue worker was killed: there's then no job left to go on.
	 *
	 * @var int
	 */
	public const STALLED_MINUTES = 15;

	/**
	 * Most failed conversations kept per run, with their errors.
	 *
	 * @var int
	 */
	public const MAX_FAILURES = 5000;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporghelpscoutimport_runs';

	/**
	 * Attributes cast to dates.
	 *
	 * @var array
	 */
	protected $dates = array( 'since', 'started_at', 'finished_at' );

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected $casts = array(
		'page_done'     => 'array',
		'previous_page' => 'array',
		'skips'         => 'array',
		'failures'      => 'array',
		'retry_ids'     => 'array',
		'saved_replies' => 'array',
	);

	/**
	 * The FreeScout mailbox it imports into.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function mailbox(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
		return $this->belongsTo( Mailbox::class );
	}

	/**
	 * Whether the run is importing, or can go on importing.
	 *
	 * @return bool
	 */
	public function is_open(): bool {
		return in_array( $this->status, array( self::STATUS_RUNNING, self::STATUS_PAUSED ), true );
	}

	/**
	 * Whether the run is running, but hasn't saved progress for a while: its job may have died.
	 *
	 * @return bool
	 */
	public function is_stalled(): bool {
		return self::STATUS_RUNNING === $this->status && $this->updated_at && $this->updated_at->lt( \Carbon\Carbon::now()->subMinutes( self::STALLED_MINUTES ) );
	}

	/**
	 * Whether the run retries another's failed conversations, rather than importing a mailbox's list.
	 *
	 * @return bool
	 */
	public function is_retry(): bool {
		return null !== $this->retry_ids;
	}

	/**
	 * What went wrong, short, and without what a database error's SQL would show of imported data.
	 *
	 * @param \Throwable $e What went wrong.
	 * @return string
	 */
	public static function describe( \Throwable $e ): string {
		$message = $e instanceof \Illuminate\Database\QueryException && $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();

		return mb_substr( $message, 0, 300 );
	}

	/**
	 * Counts a conversation it left out.
	 *
	 * @param string $reason Why, one of Importer's SKIPPED_ constants.
	 * @return void
	 */
	public function add_skip( string $reason ): void {
		$skips            = (array) $this->skips;
		$skips[ $reason ] = (int) ( $skips[ $reason ] ?? 0 ) + 1;
		$this->skips      = $skips;
		++$this->skipped;
	}

	/**
	 * Counts a conversation that failed, and keeps its error.
	 *
	 * @param int    $helpscout_id HelpScout conversation ID.
	 * @param string $error        Error.
	 * @return void
	 */
	public function add_failure( int $helpscout_id, string $error ): void {
		$failures = (array) $this->failures;
		if ( count( $failures ) < self::MAX_FAILURES || isset( $failures[ $helpscout_id ] ) ) {
			$failures[ $helpscout_id ] = mb_substr( $error, 0, 300 );
			$this->failures            = $failures;
		}

		$this->last_error = mb_substr( 'HelpScout conversation ' . $helpscout_id . ': ' . $error, 0, 400 );
		++$this->failed;
	}

	/**
	 * Gives the run a new chain of jobs, so any job queued before stops.
	 *
	 * @return string The new token.
	 */
	public function renew_token(): string {
		$this->token = bin2hex( random_bytes( 16 ) );

		return $this->token;
	}
}
