<?php
/**
 * Creates the table connecting FreeScout users to WordPress.org accounts.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One WordPress.org account per user, and one user per account.
 */
class CreateWporgssoAccountsTable extends Migration {

	/**
	 * Runs the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		if ( Schema::hasTable( 'wporgsso_accounts' ) ) {
			return;
		}

		Schema::create(
			'wporgsso_accounts',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->unsignedInteger( 'user_id' )->unique();
				$table->string( 'username', 60 )->unique();

				// The avatar the user's photo was made from, so an unchanged one isn't saved again.
				$table->string( 'avatar_hash', 64 )->nullable();
				$table->timestamps();
			}
		);
	}

	/**
	 * Reverses the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::dropIfExists( 'wporgsso_accounts' );
	}
}
