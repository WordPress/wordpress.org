<?php
/**
 * Creates the tables for import runs and for what was imported from where.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runs, the other FreeScout's IDs for imported conversations, threads, and users, and its emails' Message-IDs.
 */
class CreateWporgfreescoutimportTables extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( ! Schema::hasTable( 'wporgfreescoutimport_runs' ) ) {
			Schema::create(
				'wporgfreescoutimport_runs',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_mailbox_id' );
					$table->string( 'source_mailbox_name', 191 );
					$table->unsignedInteger( 'mailbox_id' );
					$table->unsignedInteger( 'user_id' )->nullable();
					$table->string( 'status', 20 );

					// Only conversations the other FreeScout changed since then; null for everything.
					$table->timestamp( 'since' )->nullable();
					$table->unsignedInteger( 'page' )->default( 1 );
					// Source IDs done on that page, so a page that's tried again doesn't import them twice.
					$table->text( 'page_done' )->nullable();

					// Source IDs the previous page listed; others found there since moved there from later pages.
					$table->text( 'previous_page' )->nullable();
					$table->unsignedInteger( 'pages' )->nullable();
					$table->unsignedInteger( 'total' )->nullable();
					$table->unsignedInteger( 'imported' )->default( 0 );
					$table->unsignedInteger( 'updated' )->default( 0 );
					$table->unsignedInteger( 'skipped' )->default( 0 );
					$table->unsignedInteger( 'failed' )->default( 0 );

					// Skipped conversations by why, and failed ones' errors by source ID, as JSON.
					$table->text( 'skips' )->nullable();
					$table->longText( 'failures' )->nullable();
					$table->text( 'last_error' )->nullable();

					// For a run that retries another's failures: the source IDs to import; null for a mailbox's list.
					$table->longText( 'retry_ids' )->nullable();

					// The conversation being imported, and how often its import started without finishing.
					$table->unsignedInteger( 'attempting' )->nullable();
					$table->unsignedTinyInteger( 'attempts' )->default( 0 );

					// Pages the other FreeScout failed in a row; the run stops after too many.
					$table->unsignedSmallInteger( 'page_failures' )->default( 0 );

					// Changes when the run is paused or resumed, so a job queued before stops instead of running twice.
					$table->string( 'token', 32 )->nullable();
					$table->timestamp( 'started_at' )->nullable();
					$table->timestamp( 'finished_at' )->nullable();
					$table->timestamps();

					$table->index( 'mailbox_id' );
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_conversations' ) ) {
			Schema::create(
				'wporgfreescoutimport_conversations',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_id' )->unique();
					// The number it had there, which people may quote; it gets a new one here.
					$table->unsignedInteger( 'source_number' )->index();
					$table->unsignedInteger( 'conversation_id' )->unique();

					// Source users it was started, assigned, and closed by, so they can be credited to someone else later.
					$table->unsignedInteger( 'creator_id' )->nullable()->index();
					$table->unsignedInteger( 'assignee_id' )->nullable()->index();
					$table->unsignedInteger( 'closer_id' )->nullable()->index();

					// The source's tags the last import gave it, and whether the import added each, as JSON.
					$table->text( 'written_tags' )->nullable();
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_threads' ) ) {
			Schema::create(
				'wporgfreescoutimport_threads',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_id' )->unique();
					$table->unsignedInteger( 'thread_id' )->index();

					// Who wrote a reply or note, and whom the conversation was assigned to then, for crediting later.
					$table->unsignedInteger( 'source_user_id' )->nullable()->index();
					$table->unsignedInteger( 'source_assignee_id' )->nullable()->index();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_users' ) ) {
			Schema::create(
				'wporgfreescoutimport_users',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_user_id' )->unique();
					$table->unsignedInteger( 'user_id' )->index();

					// Whether an import created the user, rather than finding them here.
					$table->boolean( 'created' )->default( false );
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_message_ids' ) ) {
			Schema::create(
				'wporgfreescoutimport_message_ids',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_thread_id' )->unique();
					// As long as core's threads.message_id.
					$table->string( 'message_id', 998 );
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
		Schema::dropIfExists( 'wporgfreescoutimport_message_ids' );
		Schema::dropIfExists( 'wporgfreescoutimport_users' );
		Schema::dropIfExists( 'wporgfreescoutimport_threads' );
		Schema::dropIfExists( 'wporgfreescoutimport_conversations' );
		Schema::dropIfExists( 'wporgfreescoutimport_runs' );
	}
}
