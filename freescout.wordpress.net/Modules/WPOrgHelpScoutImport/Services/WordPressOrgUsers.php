<?php
/**
 * Creates FreeScout users for HelpScout users from their WordPress.org accounts.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\UserSync;
use Modules\WPOrgSSO\Services\WordPressOrgUser;
use RuntimeException;

/**
 * Like Manage » Users » New User with a WordPress.org username, through WPOrgSSO's own lookup and sync.
 *
 * The user is filled in from the account and connected to it. They get no mailboxes, and no password: they log in
 * through WordPress.org. WPOrgSSO is optional, so callers check available() first.
 */
final class WordPressOrgUsers {

	/**
	 * Whether WPOrgSSO is on to look accounts up, and connect users to them: FreeScout loads active modules' providers.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( WordPressOrgUser::class )
			&& class_exists( Account::class )
			&& (bool) app()->getProviders( \Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider::class );
	}


	/**
	 * The user connected to a WordPress.org account, created if there's none yet.
	 *
	 * @param string $username   WordPress.org username.
	 * @param bool   $can_log_in Whether a new user can log in; one who can't is disabled.
	 * @return User
	 *
	 * @throws RuntimeException With a message for the administrator, if there's no such account, or it can't be used.
	 */
	public static function for_username( string $username, bool $can_log_in ): User {
		$client = Client::from_config();
		if ( ! $client->is_configured() ) {
			throw new RuntimeException( __( 'WordPress.org accounts can’t be looked up until WPORG_API_SECRET is set.' ) );
		}

		try {
			$wporg_user = WordPressOrgUser::find( $username, $client );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgHelpScoutImport] WordPress.org lookup failed: ' . $e->getMessage() );

			throw new RuntimeException( __( 'WordPress.org could not be reached. Please try again in a moment.' ), 0, $e );
		}

		if ( ! $wporg_user ) {
			throw new RuntimeException( __( 'There is no WordPress.org account with that username.' ) );
		}

		$connected = Account::user_for( $wporg_user->username );
		if ( $connected ) {
			return $connected;
		}

		$fields   = $wporg_user->user_fields();
		$existing = '' !== $fields['email'] ? User::query()->where( 'email', $fields['email'] )->first() : null;
		if ( $existing ) {
			throw new RuntimeException( __( 'That WordPress.org account’s email address belongs to :name; connect them on their profile instead.', array( 'name' => $existing->getFullName() ) ) );
		}

		if ( '' === $fields['email'] || User::mailboxEmailExists( $fields['email'] ) ) {
			throw new RuntimeException( __( 'That WordPress.org account’s email address can’t be used for a user.' ) );
		}

		return \DB::transaction(
			static function () use ( $fields, $wporg_user, $can_log_in ): User {
				$user               = new User();
				$user->first_name   = $fields['first_name'];
				$user->last_name    = $fields['last_name'];
				$user->email        = $fields['email'];
				$user->role         = User::ROLE_USER;
				$user->status       = $can_log_in ? User::STATUS_ACTIVE : User::STATUS_DISABLED;
				$user->invite_state = User::INVITE_STATE_ACTIVATED;
				$user->timezone     = config( 'app.timezone' ) ? config( 'app.timezone' ) : User::DEFAULT_TIMEZONE;
				$user->password     = User::getDummyPassword();
				$user->save();

				Account::connect( (int) $user->id, $wporg_user->username );
				UserSync::sync( $user, $wporg_user );

				return $user;
			}
		);
	}
}
