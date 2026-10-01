<?php
/**
 * Creates the tables for import runs and for what was imported from where.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runs, and HelpScout's IDs for imported conversations and threads, so a run can be repeated without duplicates.
 */
class CreateWporghelpscoutimportTables extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( ! Schema::hasTable( 'wporghelpscoutimport_runs' ) ) {
			Schema::create(
				'wporghelpscoutimport_runs',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_mailbox_id' );
					$table->string( 'helpscout_mailbox_name', 191 );
					$table->unsignedInteger( 'mailbox_id' );
					$table->unsignedInteger( 'user_id' )->nullable();
					$table->string( 'status', 20 );

					// Only conversations HelpScout changed since then; null for everything.
					$table->timestamp( 'since' )->nullable();
					$table->unsignedInteger( 'page' )->default( 1 );
					// Conversations done on that page.
					$table->unsignedInteger( 'position' )->default( 0 );
					$table->unsignedInteger( 'pages' )->nullable();
					$table->unsignedInteger( 'total' )->nullable();
					$table->unsignedInteger( 'imported' )->default( 0 );
					$table->unsignedInteger( 'updated' )->default( 0 );
					$table->unsignedInteger( 'skipped' )->default( 0 );
					$table->unsignedInteger( 'failed' )->default( 0 );
					$table->text( 'last_error' )->nullable();

					// Changes when the run is paused or resumed, so a job queued before stops instead of running twice.
					$table->string( 'token', 32 )->nullable();
					$table->timestamp( 'started_at' )->nullable();
					$table->timestamp( 'finished_at' )->nullable();
					$table->timestamps();

					$table->index( 'mailbox_id' );
				}
			);
		}

		if ( ! Schema::hasTable( 'wporghelpscoutimport_conversations' ) ) {
			Schema::create(
				'wporghelpscoutimport_conversations',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_id' )->unique();
					$table->unsignedBigInteger( 'helpscout_number' )->index();
					$table->unsignedInteger( 'conversation_id' )->unique();

					// Kept until the Tags and Custom Fields modules can take them.
					$table->text( 'tags' )->nullable();
					$table->text( 'custom_fields' )->nullable();
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporghelpscoutimport_threads' ) ) {
			Schema::create(
				'wporghelpscoutimport_threads',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_id' )->unique();
					$table->unsignedInteger( 'thread_id' )->index();
				}
			);
		}
	}

	/**
	 * Reverses the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::dropIfExists( 'wporghelpscoutimport_threads' );
		Schema::dropIfExists( 'wporghelpscoutimport_conversations' );
		Schema::dropIfExists( 'wporghelpscoutimport_runs' );
	}
}
