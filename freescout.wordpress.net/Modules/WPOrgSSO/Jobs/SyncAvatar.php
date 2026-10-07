<?php
/**
 * Queued update of a user's photo from their WordPress.org avatar.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Jobs;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\UserSync;

/**
 * Runs on the queue, so a slow Gravatar never holds up a login.
 */
final class SyncAvatar implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * FreeScout user ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Avatar URL.
	 *
	 * @var string
	 */
	public $avatar_url;

	/**
	 * Constructor.
	 *
	 * @param int    $user_id    FreeScout user ID.
	 * @param string $avatar_url Avatar URL.
	 */
	public function __construct( int $user_id, string $avatar_url ) {
		$this->user_id    = $user_id;
		$this->avatar_url = $avatar_url;
	}

	/**
	 * Updates the photo; failures are logged, not retried, since the next login tries again.
	 *
	 * @return void
	 */
	public function handle(): void {
		$user = User::find( $this->user_id );
		if ( ! $user ) {
			return;
		}

		try {
			UserSync::sync_avatar( $user, $this->avatar_url, Client::from_config() );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Could not update the photo of user ' . $user->id . ': ' . $e->getMessage() );
		}
	}
}
