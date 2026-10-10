<?php
/**
 * Creates the index of review emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgPluginReview\Jobs\IndexReviews;

/**
 * Which threads are review emails, and of what type, from their Review ID lines.
 */
class CreateWporgpluginreviewTables extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( Schema::hasTable( 'wporgpluginreview_reviews' ) ) {
			return;
		}

		Schema::create(
			'wporgpluginreview_reviews',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->unsignedInteger( 'thread_id' )->unique();
				$table->unsignedInteger( 'conversation_id' )->index();
				$table->string( 'type', 50 );
				$table->timestamp( 'reviewed_at' )->nullable();
			}
		);

		/*
		 * The table is created as the module is first switched on, so the review emails FreeScout already has are indexed
		 * on the queue; `wporgpluginreview:index` indexes them again, like after the module was off for a while.
		 */
		try {
			IndexReviews::dispatch();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not queue indexing the review emails: ' . $e->getMessage() );
		}
	}

	/**
	 * Reverts the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::dropIfExists( 'wporgpluginreview_reviews' );
	}
}
