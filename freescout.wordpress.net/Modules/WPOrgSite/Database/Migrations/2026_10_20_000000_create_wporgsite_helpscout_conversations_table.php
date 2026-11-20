<?php
/**
 * Creates the table of HelpScout's conversation IDs.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgSite\Services\HelpScoutLinks;

/**
 * The FreeScout conversation each HelpScout conversation was imported as, for HelpScout's links.
 */
class CreateWporgsiteHelpscoutConversationsTable extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( Schema::hasTable( HelpScoutLinks::TABLE ) ) {
			return;
		}

		Schema::create(
			HelpScoutLinks::TABLE,
			static function ( Blueprint $table ): void {
				$table->unsignedBigInteger( 'helpscout_id' )->primary();
				$table->unsignedInteger( 'conversation_id' )->index();
			}
		);

		// Later imports are recorded as the importer saves them.
		HelpScoutLinks::copy_imported();
	}

	/**
	 * Reverts the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::dropIfExists( HelpScoutLinks::TABLE );
	}
}
