<?php
/**
 * Queued handover of uploaded Message-IDs to the threads imported before they were uploaded.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgFreeScoutImport\Services\MessageIds;

/**
 * Gives imported threads without a Message-ID the one uploaded for them; threads imported later get theirs as they're
 * imported.
 */
final class ApplyMessageIds implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * Gives the threads their Message-IDs.
	 *
	 * @return void
	 */
	public function handle(): void {
		try {
			MessageIds::apply();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgFreeScoutImport] Could not give imported threads their Message-IDs: ' . $e->getMessage() );
		}
	}
}
