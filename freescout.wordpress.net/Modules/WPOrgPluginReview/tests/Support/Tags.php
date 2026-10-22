<?php
/**
 * The Tags module's tables, which the worklists read, without the module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the Tags module's tables as its migrations leave them, and drops them again.
 *
 * The module is a paid one, so it isn't installed for tests. Creating or dropping a table inside a test's transaction
 * would commit it: create them before it starts, and drop them after it's rolled back.
 */
final class Tags {

	/**
	 * The module's tables.
	 *
	 * @var string[]
	 */
	public const TABLES = array( 'tags', 'conversation_tag' );

	/**
	 * Creates the tables, from scratch.
	 *
	 * @return void
	 */
	public static function create(): void {
		self::drop();

		Schema::create(
			'tags',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->string( 'name', 191 )->unique();
				$table->integer( 'counter' )->default( 0 );
				$table->unsignedTinyInteger( 'color' )->default( 0 );
			}
		);

		Schema::create(
			'conversation_tag',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->integer( 'conversation_id' );
				$table->integer( 'tag_id' );
				$table->unique( array( 'conversation_id', 'tag_id' ) );
			}
		);
	}

	/**
	 * Drops the tables.
	 *
	 * @return void
	 */
	public static function drop(): void {
		foreach ( self::TABLES as $table ) {
			Schema::dropIfExists( $table );
		}
	}
}
