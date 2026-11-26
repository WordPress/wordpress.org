<?php
/**
 * WPOrgTeams service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Providers;

use App\User;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgTeams\Console\Sync;
use Modules\WPOrgTeams\Http\Middleware\TeamAccess;
use Modules\WPOrgTeams\Services\Access;
use Modules\WPOrgTeams\Services\Pending;
use Modules\WPOrgTeams\Services\Teams;

/**
 * Grants mailbox access and user permissions through teams: each member gets what their teams have.
 */
final class WPOrgTeamsServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgteams';

	/**
	 * View namespace of the Teams module's own views, which the module's team form adds to.
	 *
	 * @var string
	 */
	public const TEAMS_VIEWS = 'wporgteams-teams';

	/**
	 * Registers the module's services.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton( Pending::class );
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->loadViewsFrom( __DIR__ . '/../Resources/views', self::ALIAS );
		$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );
		$this->commands( array( Sync::class ) );

		$this->app['router']->pushMiddlewareToGroup( 'web', TeamAccess::class );

		// Once every module booted, so the Teams module's views are there, whichever boots first.
		$this->app->booted(
			function (): void {
				try {
					$this->override_team_form();
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgTeams] Could not add permissions to the team form: ' . $e->getMessage() );
				}
			}
		);

		$this->register_hooks();
	}

	/**
	 * Serves the team form from the module, which renders the Teams module's own and adds the permissions to it.
	 *
	 * @return void
	 */
	private function override_team_form(): void {
		$finder = $this->app['view']->getFinder();
		$hints  = $finder->getHints();
		if ( empty( $hints[ Teams::MODULE ] ) ) {
			// Without the Teams module, there's no form to add to.
			return;
		}

		$finder->addNamespace( self::TEAMS_VIEWS, $hints[ Teams::MODULE ] );
		$finder->prependNamespace( Teams::MODULE, __DIR__ . '/../Resources/views/teams' );
	}

	/**
	 * Registers the Eventy hooks and event listeners.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// A team being saved: by its form, by being deleted, or by a user's Teams page.
		\Event::listen(
			'eloquent.saving: ' . User::class,
			static function ( User $user ): void {
				try {
					self::team_saving( $user );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgTeams] Could not take in the changes to team ' . $user->id . ': ' . $e->getMessage() );
				}
			}
		);

		// An administrator made a user keeps core's rows for every mailbox until their teams' access replaces them.
		\Event::listen(
			'eloquent.saving: ' . User::class,
			static function ( User $user ): void {
				if ( $user->exists && $user->isDirty( 'role' ) && Teams::active() ) {
					app( Pending::class )->add( array( (int) $user->id ) );
				}
			}
		);

		\Event::listen(
			'eloquent.created: ' . User::class,
			static function ( User $user ): void {
				try {
					self::user_created( $user );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgTeams] Could not add user ' . $user->id . ' to their teams: ' . $e->getMessage() );
				}
			}
		);

		// Saves from outside a request the middleware watches, like the command line, are applied at the end.
		$this->app->terminating(
			static function (): void {
				app( Pending::class )->flush();
			}
		);

		// A mailbox's Permissions page can't give or take access: its teams' members have it.
		\Eventy::addFilter(
			'mailbox.permission_users',
			static function ( $user_ids, $mailbox_id ) {
				if ( ! is_array( $user_ids ) || ! Teams::active() ) {
					return $user_ids;
				}

				try {
					return Access::mailbox_user_ids( $user_ids, (int) $mailbox_id );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgTeams] Could not tell who has access to mailbox ' . $mailbox_id . ': ' . $e->getMessage() );

					return $user_ids;
				}
			},
			// After the Teams module, which adds the mailbox's teams.
			30,
			2
		);

		// The script moves it above the users, which it disables; the Teams module lists the teams and their members.
		\Eventy::addAction(
			'mailbox.permissions.before_access_settings',
			static function (): void {
				if ( Teams::active() ) {
					self::render( 'mailbox_permissions', array() );
				}
			},
			5
		);

		\Eventy::addAction(
			'user.profile.menu.after_profile',
			static function ( $user ): void {
				if ( $user instanceof User && Teams::active() && self::can_change_teams( auth()->user(), $user ) ) {
					self::render( 'user_menu', array( 'user' => $user ) );
				}
			}
		);

		\Eventy::addAction(
			'user.create.before_email',
			static function (): void {
				if ( Teams::active() ) {
					self::render( 'create_user', array( 'teams' => Teams::assignable_by( auth()->user() ) ) );
				}
			}
		);

		\Eventy::addFilter(
			'javascripts',
			static function ( array $javascripts ): array {
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/users.js';

				return $javascripts;
			}
		);
	}

	/**
	 * Takes in a team's new permissions, and whose access its save changes.
	 *
	 * @param User $user The user being saved.
	 * @return void
	 */
	private static function team_saving( User $user ): void {
		if ( ! Teams::is_team( $user ) || ! Teams::active() ) {
			return;
		}

		$pending = app( Pending::class );
		if ( null !== $pending->team_permissions && ! Teams::is_deleted( $user ) ) {
			Teams::set_permissions( $user, $pending->team_permissions );
			$pending->team_permissions = null;
		}

		// Its members before and after: those it lost, those it gained, and those whose access it changed.
		$pending->add( array_merge( Teams::ids_from_json( $user->getOriginal( 'emails' ) ), Teams::member_ids( $user ) ) );
	}

	/**
	 * Adds a user the create form made to the teams it asked for.
	 *
	 * @param User $user The new user.
	 * @return void
	 */
	private static function user_created( User $user ): void {
		$pending = app( Pending::class );
		if ( null === $pending->new_user_team_ids || Teams::is_team( $user ) ) {
			return;
		}

		$team_ids                   = $pending->new_user_team_ids;
		$pending->new_user_team_ids = null;

		foreach ( $team_ids as $team_id ) {
			Teams::change_members( (int) $team_id, static fn ( array $member_ids ): array => array_merge( $member_ids, array( (int) $user->id ) ) );
		}
		$pending->add( array( (int) $user->id ) );
	}

	/**
	 * Whether someone may change a user's teams: administrators, and those allowed to manage users who are in a team,
	 * for anyone but administrators and themselves.
	 *
	 * @param User|null $by   Who's asking.
	 * @param User      $user Whose teams.
	 * @return bool
	 */
	public static function can_change_teams( ?User $by, User $user ): bool {
		if ( ! $by || $user->isDeleted() || User::TYPE_ROBOT === (int) $user->type ) {
			return false;
		}

		if ( $by->isAdmin() ) {
			return true;
		}

		// On their own page, they could only leave their teams.
		return $by->hasPermission( User::PERM_EDIT_USERS )
			&& ! $user->isAdmin()
			&& (int) $by->id !== (int) $user->id
			&& Teams::assignable_by( $by )->isNotEmpty();
	}

	/**
	 * Renders a module view, logging instead of failing the page.
	 *
	 * @param string $view View name.
	 * @param array  $data View data.
	 * @return void
	 */
	public static function render( string $view, array $data ): void {
		try {
			echo \View::make( self::ALIAS . '::' . $view, $data )->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Blade escapes the view.
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgTeams] Could not render ' . $view . ': ' . $e->getMessage() );
		}
	}
}
