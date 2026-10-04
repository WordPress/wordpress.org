<?php
/**
 * The paid modules' tables, which the importer writes, without the modules.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests\Support;

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Teams\Providers\TeamsServiceProvider;

require_once __DIR__ . '/TeamsServiceProvider.php';

/**
 * Creates the tables of the Saved Replies, Tags, and Custom Fields modules as their migrations leave them, and drops
 * them again.
 *
 * The modules are paid ones, so they aren't installed for tests. Creating or dropping a table inside a test's
 * transaction would commit it: create them before it starts, and drop them after it's rolled back.
 */
final class PaidModules {

	/**
	 * The modules' tables, by module alias.
	 *
	 * @var string[][]
	 */
	public const TABLES = array(
		'savedreplies' => array( 'saved_replies' ),
		'tags'         => array( 'tags', 'conversation_tag' ),
		'customfields' => array( 'custom_fields', 'conversation_custom_field' ),
	);

	/**
	 * Creates every module's tables, from scratch.
	 *
	 * @return void
	 */
	public static function create(): void {
		self::drop();

		Schema::create(
			'saved_replies',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->integer( 'mailbox_id' );
				$table->string( 'name', 75 );
				$table->longText( 'text' )->nullable();
				$table->integer( 'user_id' );
				$table->timestamps();
				$table->integer( 'sort_order' )->default( 1 );
				$table->unsignedInteger( 'parent_saved_reply_id' )->nullable();
				$table->text( 'attachments' )->nullable();
				$table->boolean( 'global' )->default( false );
				$table->boolean( 'auto_load' )->default( false );
			}
		);

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

		Schema::create(
			'custom_fields',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->integer( 'mailbox_id' );
				$table->string( 'name', 75 );
				$table->unsignedTinyInteger( 'type' )->default( 1 );
				$table->longText( 'options' )->nullable();
				$table->boolean( 'required' )->default( false );
				$table->integer( 'sort_order' )->default( 1 );
				$table->timestamps();
				$table->boolean( 'show_in_list' )->default( false );
			}
		);

		Schema::create(
			'conversation_custom_field',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->integer( 'conversation_id' );
				$table->integer( 'custom_field_id' );
				$table->text( 'value' );
				$table->unique( array( 'conversation_id', 'custom_field_id' ) );
			}
		);
	}

	/**
	 * Drops every module's tables.
	 *
	 * @return void
	 */
	public static function drop(): void {
		foreach ( array_merge( ...array_values( self::TABLES ) ) as $table ) {
			Schema::dropIfExists( $table );
		}
	}

	/**
	 * A team of the Teams module, which keeps them as deleted robot users named "Team".
	 *
	 * @param string $name Team name.
	 * @return User
	 */
	public static function team( string $name ): User {
		$team = factory( User::class )->create(
			array(
				'first_name' => $name,
				'last_name'  => 'Team',
				'email'      => uniqid( 'team-' ) . '@example.org',
				'type'       => User::TYPE_ROBOT,
				'status'     => User::STATUS_DELETED,
			)
		);

		TeamsServiceProvider::$team_ids[] = (int) $team->id;

		return $team;
	}

	/**
	 * Forgets the teams tests made, which the database forgets with them.
	 *
	 * @return void
	 */
	public static function forget_teams(): void {
		TeamsServiceProvider::$team_ids = array();
	}

	/**
	 * Switches a module on or off.
	 *
	 * @param string $alias  Module alias.
	 * @param bool   $active Whether it's on.
	 * @return void
	 */
	public static function switch( string $alias, bool $active ): void {
		\App\Module::clearModulesCache();
		\App\Module::setActive( $alias, $active );
		\App\Module::clearModulesCache();
	}
}
