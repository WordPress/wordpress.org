<?php
/**
 * The WordPress.org account a FreeScout user is connected to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporgsso_accounts.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $username    WordPress.org username (user_login).
 * @property string|null $avatar_hash SHA-256 of the avatar the user's photo was made from.
 */
final class Account extends Model {

	/**
	 * How long a user's username is cached, in minutes.
	 *
	 * @var int
	 */
	private const USERNAME_CACHE_MINUTES = 60;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporgsso_accounts';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'user_id', 'username', 'avatar_hash' );

	/**
	 * Clears a user's cached username whenever their connection changes.
	 *
	 * Only through models: bulk queries don't fire these events.
	 *
	 * @return void
	 */
	protected static function boot(): void {
		parent::boot();

		$forget = static function ( self $account ): void {
			\Cache::forget( self::username_cache_key( (int) $account->user_id ) );
		};
		self::saved( $forget );
		self::deleted( $forget );
	}

	/**
	 * The WordPress.org username a user is connected to, from the cache; checked on every request.
	 *
	 * Expires after an hour too, in case the table was changed without the model.
	 *
	 * @param int $user_id FreeScout user ID.
	 * @return string Empty if the user isn't connected.
	 */
	public static function username_for( int $user_id ): string {
		return (string) \Cache::remember(
			self::username_cache_key( $user_id ),
			self::USERNAME_CACHE_MINUTES,
			static function () use ( $user_id ): string {
				$account = self::for_user( $user_id );

				return $account ? $account->username : '';
			}
		);
	}

	/**
	 * Cache key of a user's WordPress.org username.
	 *
	 * @param int $user_id FreeScout user ID.
	 * @return string
	 */
	private static function username_cache_key( int $user_id ): string {
		return 'wporgsso.username.' . $user_id;
	}

	/**
	 * Finds the account a user is connected to.
	 *
	 * @param int $user_id FreeScout user ID.
	 * @return self|null
	 */
	public static function for_user( int $user_id ): ?self {
		return self::query()->where( 'user_id', $user_id )->first();
	}

	/**
	 * Finds the user connected to a WordPress.org account.
	 *
	 * @param string $username WordPress.org username.
	 * @return User|null
	 */
	public static function user_for( string $username ): ?User {
		$account = self::query()->where( 'username', $username )->first();

		return $account ? User::find( $account->user_id ) : null;
	}

	/**
	 * Connects a user to a WordPress.org account, replacing any previous connection.
	 *
	 * @param int    $user_id  FreeScout user ID.
	 * @param string $username WordPress.org username.
	 * @return self
	 */
	public static function connect( int $user_id, string $username ): self {
		return self::query()->updateOrCreate( array( 'user_id' => $user_id ), array( 'username' => $username ) );
	}
}
