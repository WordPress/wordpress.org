<?php
/**
 * Gives an administrator a password for break-glass logins.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Console;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Password reset emails stay closed with WordPress.org enforced, so break-glass passwords are set on the server.
 */
final class SetPassword extends Command {

	/**
	 * Command signature.
	 *
	 * @var string
	 */
	protected $signature = 'wporgsso:password {email : Administrator email}';

	/**
	 * Command description.
	 *
	 * @var string
	 */
	protected $description = 'Sets a new random password for an administrator, for logins with WPORG_SSO_PASSWORD_LOGIN.';

	/**
	 * Runs the command.
	 *
	 * @return int Exit code.
	 */
	public function handle(): int {
		$user = User::query()->where( 'email', (string) $this->argument( 'email' ) )->first();
		if ( ! $user || ! $user->isAdmin() ) {
			$this->error( 'There is no FreeScout administrator with that email address.' );

			return 1;
		}

		// Core's own random passwords are 8 characters.
		$password = Str::random( 32 );
		$user->setPassword( $password );
		$user->save();

		$this->line( $password );

		return 0;
	}
}
