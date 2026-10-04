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
 * Runs, and HelpScout's IDs for imported conversations, threads, custom fields, and saved replies, so a run can be repeated without duplicates.
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
					// HelpScout IDs done on that page, so a page that's tried again doesn't import them twice.
					$table->text( 'page_done' )->nullable();

					// HelpScout IDs the previous page listed; others found there since moved there from later pages.
					$table->text( 'previous_page' )->nullable();
					$table->unsignedInteger( 'pages' )->nullable();
					$table->unsignedInteger( 'total' )->nullable();
					$table->unsignedInteger( 'imported' )->default( 0 );
					$table->unsignedInteger( 'updated' )->default( 0 );
					$table->unsignedInteger( 'skipped' )->default( 0 );
					$table->unsignedInteger( 'failed' )->default( 0 );

					// Skipped conversations by why, and failed ones' errors by HelpScout ID, as JSON.
					$table->text( 'skips' )->nullable();
					$table->longText( 'failures' )->nullable();
					$table->text( 'last_error' )->nullable();

					// Saved replies imported, updated, and kept as FreeScout has them, and failed ones' HelpScout IDs, as JSON.
					$table->text( 'saved_replies' )->nullable();

					// For a run that retries another's failures: the HelpScout IDs to import; null for a mailbox's list.
					$table->longText( 'retry_ids' )->nullable();

					// A conversation the rate limit cut off part way: its next try waits for the limit instead.
					$table->unsignedBigInteger( 'waiting_on' )->nullable();

					// The conversation being imported, and how often its import started: one that keeps stopping the job,
					// like by running out of memory, is counted as failed so the run goes on.
					$table->unsignedBigInteger( 'attempting' )->nullable();
					$table->unsignedTinyInteger( 'attempts' )->default( 0 );

					// Pages HelpScout failed in a row; the run stops after too many.
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

		if ( ! Schema::hasTable( 'wporghelpscoutimport_conversations' ) ) {
			Schema::create(
				'wporghelpscoutimport_conversations',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_id' )->unique();
					$table->unsignedBigInteger( 'helpscout_number' )->index();
					$table->unsignedInteger( 'conversation_id' )->unique();

					// HelpScout users it was started, assigned, and closed by, so they can be credited to someone else later.
					$table->unsignedBigInteger( 'creator_id' )->nullable()->index();
					$table->unsignedBigInteger( 'assignee_id' )->nullable()->index();
					$table->unsignedBigInteger( 'closer_id' )->nullable()->index();

					// Kept until the Tags and Custom Fields modules can take them.
					$table->text( 'tags' )->nullable();
					$table->text( 'custom_fields' )->nullable();

					// What the last import gave the modules, as JSON, so changes on either side are told apart: HelpScout's
					// tags, and whether the import added each; and the values, by custom field ID. Null until it gave any.
					$table->text( 'written_tags' )->nullable();
					$table->text( 'written_values' )->nullable();
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporghelpscoutimport_agents' ) ) {
			Schema::create(
				'wporghelpscoutimport_agents',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_user_id' )->unique();
					$table->unsignedInteger( 'user_id' )->index();

					// Whether an import created the user, rather than finding them in FreeScout.
					$table->boolean( 'created' )->default( false );
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

					// Who wrote a reply or note, so it can be credited to someone else later.
					$table->unsignedBigInteger( 'helpscout_user_id' )->nullable()->index();

					// Whom the conversation was assigned to at the time, a HelpScout user or team.
					$table->unsignedBigInteger( 'helpscout_assignee_id' )->nullable()->index();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporghelpscoutimport_people' ) ) {
			Schema::create(
				'wporghelpscoutimport_people',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_user_id' )->unique();
					$table->string( 'first_name', 100 );
					$table->string( 'last_name', 100 );
					$table->string( 'email', 191 )->nullable();
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporghelpscoutimport_saved_replies' ) ) {
			Schema::create(
				'wporghelpscoutimport_saved_replies',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_id' )->unique();
					$table->unsignedInteger( 'saved_reply_id' )->index();

					// What HelpScout had and what FreeScout was given, so changes on either side are told apart.
					$table->string( 'source_hash', 64 )->nullable();
					$table->string( 'written_hash', 64 )->nullable();

					// The run that last checked it, so a run that's tried again goes on where it stopped.
					$table->unsignedInteger( 'run_id' )->nullable();
					$table->timestamps();
				}
			);
		}

		if ( ! Schema::hasTable( 'wporghelpscoutimport_fields' ) ) {
			Schema::create(
				'wporghelpscoutimport_fields',
				static function ( Blueprint $table ): void {
					$table->increments( 'id' );
					$table->unsignedBigInteger( 'helpscout_field_id' );
					// Custom fields are a mailbox's: a HelpScout field gets one in each mailbox it's imported into.
					$table->unsignedInteger( 'mailbox_id' );
					$table->unsignedInteger( 'custom_field_id' );
					$table->timestamps();

					$table->unique( array( 'helpscout_field_id', 'mailbox_id' ) );
				}
			);
		}
	}
					// The dropdown options HelpScout had, as JSON, so options deleted in FreeScout aren't added again.
					$table->text( 'options' )->nullable();

	/**
	 * Reverses the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::dropIfExists( 'wporghelpscoutimport_fields' );
		Schema::dropIfExists( 'wporghelpscoutimport_saved_replies' );
		Schema::dropIfExists( 'wporghelpscoutimport_people' );
		Schema::dropIfExists( 'wporghelpscoutimport_threads' );
		Schema::dropIfExists( 'wporghelpscoutimport_agents' );
		Schema::dropIfExists( 'wporghelpscoutimport_conversations' );
		Schema::dropIfExists( 'wporghelpscoutimport_runs' );
	}
}
