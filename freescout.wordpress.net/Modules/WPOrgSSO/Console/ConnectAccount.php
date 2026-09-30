<?php
/**
 * Connects a user to a WordPress.org account from the command line.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Console;

use App\User;
use Illuminate\Console\Command;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\UserSync;
use Modules\WPOrgSSO\Services\WordPressOrgUser;

/**
 * For the administrators who have to log in before anyone can connect them in the UI.
 */
final class ConnectAccount extends Command {

	/**
	 * Command signature.
	 *
	 * @var string
	 */
	protected $signature = 'wporgsso:connect {email : FreeScout user email} {username : WordPress.org username} {--force : Switch a user who is connected to another account}';

	/**
	 * Command description.
	 *
	 * @var string
	 */
	protected $description = 'Connects a FreeScout user to a WordPress.org account, so they can log in with it.';

	/**
	 * Runs the command.
	 *
	 * @return int Exit code.
	 */
	public function handle(): int {
		$user = User::query()->where( 'email', (string) $this->argument( 'email' ) )->first();
		if ( ! $user ) {
			$this->error( 'There is no FreeScout user with that email address.' );

			return 1;
		}

		try {
			$wporg_user = WordPressOrgUser::find( (string) $this->argument( 'username' ), Client::from_config() );
		} catch ( \RuntimeException $e ) {
			$this->error( 'WordPress.org could not be reached: ' . $e->getMessage() );

			return 1;
		}

		if ( ! $wporg_user ) {
			$this->error( 'There is no WordPress.org account with that username.' );

			return 1;
		}

		$connected = Account::user_for( $wporg_user->username );
		if ( $connected && (int) $connected->id !== (int) $user->id ) {
			$this->error( 'That WordPress.org account already belongs to ' . $connected->email . '.' );

			return 1;
		}

		$current = Account::for_user( (int) $user->id );
		if ( $current && 0 !== strcasecmp( $current->username, $wporg_user->username ) && ! $this->option( 'force' ) ) {
			$this->error( $user->email . ' is connected to ' . $current->username . '. Use --force to switch it to ' . $wporg_user->username . '.' );

			return 1;
		}

		try {
			Account::connect( (int) $user->id, $wporg_user->username );
		} catch ( \Illuminate\Database\QueryException $e ) {
			// Only if someone connected the same account a moment ago.
			$this->error( 'Could not connect ' . $user->email . ': ' . $wporg_user->username . ' belongs to another user now.' );

			return 1;
		}

		$this->info( $user->email . ' logs in as ' . $wporg_user->username . ' on WordPress.org.' );

		// The profile no longer lets anyone change what the account fills in.
		UserSync::sync( $user, $wporg_user );

		if ( ! $wporg_user->two_factor ) {
			$this->warn( 'That account has no two-factor authentication, which it needs to log in.' );
		}

		if ( $wporg_user->blocked ) {
			$this->warn( 'That account is blocked on WordPress.org, so it can\'t log in.' );
		}

		return 0;
	}
}
