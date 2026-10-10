<?php
/**
 * Adds the mailbox to imported conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which mailbox each imported conversation is in, so pointing WordPress.org at a mailbox's conversations still finds
 * those deleted for good, whose copies go.
 */
class AddMailboxToWporghelpscoutimportConversations extends Migration {

	/**
	 * Table of imported conversations.
	 *
	 * @var string
	 */
	private const TABLE = 'wporghelpscoutimport_conversations';

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( ! Schema::hasTable( self::TABLE ) || Schema::hasColumn( self::TABLE, 'mailbox_id' ) ) {
			return;
		}

		Schema::table(
			self::TABLE,
			static function ( Blueprint $table ): void {
				$table->unsignedInteger( 'mailbox_id' )->nullable()->index();
			}
		);

		// Those imported already; those deleted for good since can't be told anymore.
		foreach ( \DB::table( 'conversations' )->distinct()->pluck( 'mailbox_id' ) as $mailbox_id ) {
			\DB::table( self::TABLE )
				->whereIn(
					'conversation_id',
					static function ( Builder $query ) use ( $mailbox_id ): void {
						$query->select( 'id' )->from( 'conversations' )->where( 'mailbox_id', $mailbox_id );
					}
				)
				->update( array( 'mailbox_id' => (int) $mailbox_id ) );
		}
	}

	/**
	 * Reverts the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		if ( Schema::hasTable( self::TABLE ) && Schema::hasColumn( self::TABLE, 'mailbox_id' ) ) {
			Schema::table(
				self::TABLE,
				static function ( Blueprint $table ): void {
					$table->dropIndex( array( 'mailbox_id' ) );
					$table->dropColumn( 'mailbox_id' );
				}
			);
		}
	}
}
