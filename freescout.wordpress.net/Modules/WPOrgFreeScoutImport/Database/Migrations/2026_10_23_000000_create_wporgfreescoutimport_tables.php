<?php
/**
 * Creates the tables for the FreeScouts imported from, import runs, and what was imported from where.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sources, runs, each source's IDs for imported conversations, threads, and users, and its emails' Message-IDs.
 *
 * A source's own IDs are `remote_` columns, unique per source: two FreeScouts number their conversations, threads,
 * and users alike.
 */
class CreateWporgfreescoutimportTables extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( ! Schema::hasTable( 'wporgfreescoutimport_sources' ) ) {
			Schema::create(
				'wporgfreescoutimport_sources',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->string( 'name', 191 );
					$table->string( 'url', 191 );
					// Its API & Webhooks module's global API key, encrypted with the app key; null once forgotten.
					$table->text( 'api_key' )->nullable();
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_runs' ) ) {
			Schema::create(
				'wporgfreescoutimport_runs',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_id' )->index();
					$table->unsignedInteger( 'remote_mailbox_id' );
					$table->string( 'remote_mailbox_name', 191 );
					$table->unsignedInteger( 'mailbox_id' );
					$table->unsignedInteger( 'user_id' )->nullable();
					$table->string( 'status', 20 );

					// Only conversations the source changed since then; null for everything.
					$table->timestamp( 'since' )->nullable();
					$table->unsignedInteger( 'page' )->default( 1 );
					// Remote IDs done on that page, so a page that's tried again doesn't import them twice.
					$table->text( 'page_done' )->nullable();

					// Remote IDs the previous page listed; others found there since moved there from later pages.
					$table->text( 'previous_page' )->nullable();
					$table->unsignedInteger( 'pages' )->nullable();
					$table->unsignedInteger( 'total' )->nullable();
					$table->unsignedInteger( 'imported' )->default( 0 );
					$table->unsignedInteger( 'updated' )->default( 0 );
					$table->unsignedInteger( 'skipped' )->default( 0 );
					$table->unsignedInteger( 'failed' )->default( 0 );

					// Skipped conversations by why, and failed ones' errors by remote ID, as JSON.
					$table->text( 'skips' )->nullable();
					$table->longText( 'failures' )->nullable();
					$table->text( 'last_error' )->nullable();

					// For a run that retries another's failures: the remote IDs to import; null for a mailbox's list.
					$table->longText( 'retry_ids' )->nullable();

					// The conversation being imported, and how often its import started without finishing.
					$table->unsignedInteger( 'attempting' )->nullable();
					$table->unsignedTinyInteger( 'attempts' )->default( 0 );

					// Pages the source failed in a row; the run stops after too many.
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
					$table->unsignedInteger( 'source_id' );
					$table->unsignedInteger( 'remote_id' );
					// The number it had there, which people may quote; it gets a new one here.
					$table->unsignedInteger( 'remote_number' );
					$table->unsignedInteger( 'conversation_id' )->unique();

					// Remote users it was started, assigned, and closed by, so they can be credited to someone else later.
					$table->unsignedInteger( 'creator_id' )->nullable();
					$table->unsignedInteger( 'assignee_id' )->nullable();
					$table->unsignedInteger( 'closer_id' )->nullable();

					// The source's tags the last import gave it, and whether the import added each, as JSON.
					$table->text( 'written_tags' )->nullable();
					$table->timestamps();

					// Named, as the names Laravel makes up are longer than MySQL's limit of 64 characters.
					$table->unique( array( 'source_id', 'remote_id' ), 'wporgfreescoutimport_conversations_remote' );
					$table->index( array( 'source_id', 'remote_number' ), 'wporgfreescoutimport_conversations_number' );
					$table->index( array( 'source_id', 'creator_id' ), 'wporgfreescoutimport_conversations_creator' );
					$table->index( array( 'source_id', 'assignee_id' ), 'wporgfreescoutimport_conversations_assignee' );
					$table->index( array( 'source_id', 'closer_id' ), 'wporgfreescoutimport_conversations_closer' );
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_threads' ) ) {
			Schema::create(
				'wporgfreescoutimport_threads',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_id' );
					$table->unsignedInteger( 'remote_id' );
					$table->unsignedInteger( 'thread_id' )->index();

					// Who wrote a reply or note, and whom the conversation was assigned to then, for crediting later.
					$table->unsignedInteger( 'remote_user_id' )->nullable();
					$table->unsignedInteger( 'remote_assignee_id' )->nullable();

					$table->unique( array( 'source_id', 'remote_id' ), 'wporgfreescoutimport_threads_remote' );
					$table->index( array( 'source_id', 'remote_user_id' ), 'wporgfreescoutimport_threads_user' );
					$table->index( array( 'source_id', 'remote_assignee_id' ), 'wporgfreescoutimport_threads_assignee' );
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_users' ) ) {
			Schema::create(
				'wporgfreescoutimport_users',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_id' );
					$table->unsignedInteger( 'remote_user_id' );
					$table->unsignedInteger( 'user_id' )->index();

					// Whether an import created the user, rather than finding them here.
					$table->boolean( 'created' )->default( false );
					$table->timestamps();

					$table->unique( array( 'source_id', 'remote_user_id' ), 'wporgfreescoutimport_users_remote' );
				}
			);
		}

		if ( ! Schema::hasTable( 'wporgfreescoutimport_message_ids' ) ) {
			Schema::create(
				'wporgfreescoutimport_message_ids',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedInteger( 'source_id' );
					$table->unsignedInteger( 'remote_thread_id' );
					// As long as core's threads.message_id.
					$table->string( 'message_id', 998 );

					$table->unique( array( 'source_id', 'remote_thread_id' ), 'wporgfreescoutimport_message_ids_remote' );
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
		Schema::dropIfExists( 'wporgfreescoutimport_sources' );
	}
}
