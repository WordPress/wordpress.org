<?php
/**
 * Gives every user what their teams grant.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Console;

use App\Mailbox;
use App\User;
use Illuminate\Console\Command;
use Modules\WPOrgTeams\Services\Access;
use Modules\WPOrgTeams\Services\Teams;

/**
 * Lists, and with --apply makes, the changes that give every user exactly what their teams grant.
 *
 * Saving a team only updates its members. Run this once teams are set up, to switch everyone else over too: users in
 * no team lose their mailboxes and the permissions only they had.
 */
final class Sync extends Command {

	/**
	 * Command signature.
	 *
	 * @var string
	 */
	protected $signature = 'wporgteams:sync {--apply : Make the changes, rather than only list them}';

	/**
	 * Command description.
	 *
	 * @var string
	 */
	protected $description = 'Lists, or with --apply makes, the changes that give every user exactly what their teams grant.';

	/**
	 * Runs the command.
	 *
	 * @return int Exit code.
	 */
	public function handle(): int {
		if ( ! Teams::active() ) {
			$this->error( 'The Teams module is off, so access can’t come from teams.' );

			return 1;
		}

		$apply     = (bool) $this->option( 'apply' );
		$mailboxes = Mailbox::query()->pluck( 'name', 'id' )->all();
		$changed   = 0;

		foreach ( User::nonDeleted()->get()->sortBy( 'email' ) as $user ) {
			if ( ! Access::is_managed( $user ) ) {
				continue;
			}

			$changes = Access::apply( $user, ! $apply );
			if ( ! array_filter( $changes ) ) {
				continue;
			}

			++$changed;
			$this->line( $user->getFullName() . ' <' . $user->email . '>' );
			foreach ( $changes['mailboxes_added'] as $id ) {
				$this->line( '  + mailbox ' . ( $mailboxes[ $id ] ?? $id ) );
			}
			foreach ( $changes['mailboxes_removed'] as $id ) {
				$this->line( '  - mailbox ' . ( $mailboxes[ $id ] ?? $id ) );
			}
			foreach ( $changes['permissions_added'] as $permission ) {
				$this->line( '  + ' . User::getUserPermissionName( $permission ) );
			}
			foreach ( $changes['permissions_removed'] as $permission ) {
				$this->line( '  - ' . User::getUserPermissionName( $permission ) );
			}
		}

		if ( $apply ) {
			$this->info( 'Changed ' . $changed . ' users.' );
		} else {
			$this->info( $changed . ' users would change. Run with --apply to change them.' );
		}

		return 0;
	}
}
