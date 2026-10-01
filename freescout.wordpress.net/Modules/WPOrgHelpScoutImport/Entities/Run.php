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
 * @property int              $position    Conversations done on that page.
 * @property int|null         $pages
 * @property int|null         $total
 * @property int              $imported
 * @property int              $updated
 * @property int              $skipped
 * @property int              $failed
 * @property string|null      $last_error
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
	 * Gives the run a new chain of jobs, so any job queued before stops.
	 *
	 * @return string The new token.
	 */
	public function renew_token(): string {
		$this->token = bin2hex( random_bytes( 16 ) );

		return $this->token;
	}
}
