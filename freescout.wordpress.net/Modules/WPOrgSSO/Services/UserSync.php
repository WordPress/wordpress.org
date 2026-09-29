<?php
/**
 * Keeps FreeScout users in step with their WordPress.org accounts.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Services;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Jobs\SyncAvatar;
use RuntimeException;

/**
 * Copies name, email, and avatar from WordPress.org, when a user is added, reconnected, or logs in.
 */
final class UserSync {

	/**
	 * Updates a user from their WordPress.org account.
	 *
	 * Never fails: what can't be updated is logged and left as it was. The avatar follows on the queue.
	 *
	 * @param User             $user       FreeScout user.
	 * @param WordPressOrgUser $wporg_user Their WordPress.org account.
	 * @return void
	 */
	public static function sync( User $user, WordPressOrgUser $wporg_user ): void {
		try {
			$fields = $wporg_user->user_fields();

			$user->first_name = $fields['first_name'];
			$user->last_name  = $fields['last_name'];

			if ( 0 !== strcasecmp( (string) $user->email, $fields['email'] ) ) {
				if ( self::email_is_free( $fields['email'], (int) $user->id ) ) {
					$user->email = $fields['email'];
				} else {
					\Log::warning( '[WPOrgSSO] Kept ' . $user->email . ': ' . $fields['email'] . ' belongs to another user or a mailbox.' );
				}
			}

			$user->save();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Could not update user ' . $user->id . ': ' . $e->getMessage() );
		}

		if ( '' !== $wporg_user->avatar_url ) {
			SyncAvatar::dispatch( (int) $user->id, $wporg_user->avatar_url );
		}
	}

	/**
	 * Makes the user's photo their avatar; FreeScout doesn't let them upload another.
	 *
	 * @param User   $user       FreeScout user.
	 * @param string $avatar_url Their avatar; Gravatar's silhouette if they have none.
	 * @param Client $client     Client to download it with.
	 * @return void
	 *
	 * @throws RuntimeException If the avatar can't be downloaded or saved.
	 */
	public static function sync_avatar( User $user, string $avatar_url, Client $client ): void {
		$account = Account::for_user( (int) $user->id );
		if ( ! $account ) {
			return;
		}

		$avatar = $client->download( $avatar_url );

		$hash = hash( 'sha256', $avatar['body'] );
		if ( $user->photo_url && $hash === $account->avatar_hash ) {
			return;
		}

		$file = (string) tempnam( sys_get_temp_dir(), 'wporgsso' );
		$size = (int) config( 'app.user_photo_size' );
		try {
			file_put_contents( $file, $avatar['body'] );

			// Twice the size FreeScout shows photos at, so they're sharp on high-density screens.
			config( array( 'app.user_photo_size' => $size * 2 ) );
			$photo_url = $user->savePhoto( $file, $avatar['content_type'] );
		} finally {
			config( array( 'app.user_photo_size' => $size ) );
			unlink( $file );
		}

		if ( ! $photo_url ) {
			throw new RuntimeException( 'FreeScout could not read the avatar.' );
		}

		$user->photo_url = $photo_url;
		$user->save();

		$account->update( array( 'avatar_hash' => $hash ) );
	}

	/**
	 * Whether no other user or mailbox has an email address.
	 *
	 * @param string $email   Email address.
	 * @param int    $user_id User who wants it.
	 * @return bool
	 */
	private static function email_is_free( string $email, int $user_id ): bool {
		return '' !== $email
			&& ! User::query()->where( 'email', $email )->where( 'id', '!=', $user_id )->exists()
			&& ! User::mailboxEmailExists( $email );
	}
}
