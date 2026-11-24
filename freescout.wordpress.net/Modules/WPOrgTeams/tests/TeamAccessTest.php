<?php
/**
 * Tests for access that comes from teams.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Tests;

use App\Mailbox;
use App\User;
use Modules\WPOrgTeams\Providers\WPOrgTeamsServiceProvider;
use Modules\WPOrgTeams\Services\Pending;
use Modules\WPOrgTeams\Services\TeamForm;
use Modules\WPOrgTeams\Services\Teams;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers members' access, the forms that change teams, and the team form's fields.
 */
final class TeamAccessTest extends TestCase {

	/**
	 * Registers the module, with the Teams module on.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		\App\Module::clearModulesCache();
		\App\Module::setActive( Teams::MODULE, true );
		\App\Module::clearModulesCache();

		$this->app->register( WPOrgTeamsServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();
	}

	/**
	 * Forgets the Teams module's state, which the database forgets with the test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\App\Module::clearModulesCache();

		parent::tearDown();
	}

	/**
	 * A member gets exactly their teams' mailboxes and permissions, and keeps the settings of a mailbox they keep.
	 *
	 * @return void
	 */
	public function test_member_gets_exactly_what_their_teams_grant(): void {
		$kept    = $this->create_mailbox( 'Plugins' );
		$granted = $this->create_mailbox( 'Themes' );
		$lost    = $this->create_mailbox( 'Photos' );

		$user = $this->create_user( User::ROLE_USER );
		$user->mailboxes()->sync( array( $kept->id, $lost->id ) );
		$user->mailboxes()->updateExistingPivot( $kept->id, array( 'hide' => true ) );
		$user->permissions = array(
			User::PERM_EDIT_TAGS             => 1,
			User::PERM_ONLY_ASSIGNED_TICKETS => 1,
		);
		$user->save();

		$this->create_team( 'Reviewers', array( (int) $user->id ), array( $kept, $granted ), array( User::PERM_EDIT_USERS ) );
		app( Pending::class )->flush();

		$user = $user->fresh();
		$this->assertSame( array( (int) $kept->id, (int) $granted->id ), $this->mailbox_ids( $user ) );
		$this->assertSame( 1, (int) \DB::table( 'mailbox_user' )->where( 'user_id', $user->id )->where( 'mailbox_id', $kept->id )->value( 'hide' ) );
		$this->assertTrue( $user->hasPermission( User::PERM_EDIT_USERS ) );
		$this->assertFalse( $user->hasPermission( User::PERM_EDIT_TAGS ) );
		$this->assertTrue( $user->hasPermission( User::PERM_ONLY_ASSIGNED_TICKETS ) );
	}

	/**
	 * Someone taken out of a team loses what it granted, when the Teams module saves it.
	 *
	 * @return void
	 */
	public function test_removed_member_loses_access(): void {
		$mailbox = $this->create_mailbox();
		$stays   = $this->create_user( User::ROLE_USER );
		$leaves  = $this->create_user( User::ROLE_USER );
		$team    = $this->create_team( 'Reviewers', array( (int) $stays->id, (int) $leaves->id ), array( $mailbox ), array( User::PERM_EDIT_TAGS ) );
		app( Pending::class )->flush();

		Teams::set_member_ids( $team, array( (int) $stays->id ) );
		$team->save();
		app( Pending::class )->flush();

		$this->assertSame( array( (int) $mailbox->id ), $this->mailbox_ids( $stays->fresh() ) );
		$this->assertSame( array(), $this->mailbox_ids( $leaves->fresh() ) );
		$this->assertFalse( $leaves->fresh()->hasPermission( User::PERM_EDIT_TAGS ) );
	}

	/**
	 * An administrator made a user keeps only their teams' mailboxes, not core's rows for every mailbox.
	 *
	 * @return void
	 */
	public function test_demoted_administrator_gets_their_teams_access(): void {
		$mailbox = $this->create_mailbox();
		$admin   = $this->create_user( User::ROLE_ADMIN );
		$admin->mailboxes()->sync( array( $mailbox->id, $this->create_mailbox( 'Photos' )->id ) );
		$this->create_team( 'Reviewers', array( (int) $admin->id ), array( $mailbox ), array() );
		app( Pending::class )->flush();

		$admin->role = User::ROLE_USER;
		$admin->save();
		app( Pending::class )->flush();

		$this->assertSame( array( (int) $mailbox->id ), $this->mailbox_ids( $admin->fresh() ) );
	}

	/**
	 * Those allowed to manage users through a team add users to the teams they're in, and so give them access; not
	 * to other teams.
	 *
	 * @return void
	 */
	public function test_manager_adds_user_to_own_teams_only(): void {
		$mailbox = $this->create_mailbox();
		$manager = $this->create_user( User::ROLE_USER );
		$this->create_team( 'Managers', array( (int) $manager->id ), array(), array( User::PERM_EDIT_USERS ) );
		$own   = $this->create_team( 'Reviewers', array( (int) $manager->id ), array( $mailbox ), array() );
		$other = $this->create_team( 'Security', array(), array( $this->create_mailbox( 'Security' ) ), array() );
		app( Pending::class )->flush();
		$user = $this->create_user( User::ROLE_USER );

		$this->actingAs( $manager->fresh() )
			->post( route( 'wporgteams.user.save', array( 'id' => $user->id ) ), array( 'wporgteams_teams' => array( $own->id, $other->id ) ) )
			->assertRedirect( route( 'wporgteams.user', array( 'id' => $user->id ) ) );

		$this->assertSame( array( (int) $manager->id, (int) $user->id ), Teams::member_ids( $own->fresh() ) );
		$this->assertSame( array(), Teams::member_ids( $other->fresh() ) );
		$this->assertSame( array( (int) $mailbox->id ), $this->mailbox_ids( $user->fresh() ) );
	}

	/**
	 * Only administrators change administrators' teams, and their own; users who aren't allowed to manage users change
	 * nobody's.
	 *
	 * @dataProvider data_refused_team_changes
	 *
	 * @param bool   $manager Whether the user asking is allowed to manage users.
	 * @param string $whose   Whose teams they change: an administrator's, a user's, or their own.
	 * @return void
	 */
	public function test_refuses_team_change( bool $manager, string $whose ): void {
		$by = $this->create_user( User::ROLE_USER );
		if ( $manager ) {
			$this->create_team( 'Managers', array( (int) $by->id ), array(), array( User::PERM_EDIT_USERS ) );
			app( Pending::class )->flush();
		}
		$team = $this->create_team( 'Reviewers', array(), array(), array() );
		$user = 'self' === $whose ? $by : $this->create_user( 'admin' === $whose ? User::ROLE_ADMIN : User::ROLE_USER );

		$this->actingAs( $by->fresh() )
			->get( route( 'wporgteams.user', array( 'id' => $user->id ) ) )
			->assertStatus( 403 );
		$this->actingAs( $by->fresh() )
			->post( route( 'wporgteams.user.save', array( 'id' => $user->id ) ), array( 'wporgteams_teams' => array( $team->id ) ) )
			->assertStatus( 403 );

		$this->assertSame( array(), Teams::member_ids( $team->fresh() ) );
	}

	/**
	 * Refused team changes.
	 *
	 * @return array
	 */
	public function data_refused_team_changes(): array {
		return array(
			'manager, administrator' => array( true, 'admin' ),
			'manager, themselves'    => array( true, 'self' ),
			'not a manager, user'    => array( false, 'user' ),
		);
	}

	/**
	 * A new user gets the teams the form asked for, and their mailboxes, not the ones the form sent.
	 *
	 * @return void
	 */
	public function test_new_user_gets_teams_instead_of_mailboxes(): void {
		$team_mailbox  = $this->create_mailbox( 'Themes' );
		$other_mailbox = $this->create_mailbox( 'Photos' );
		$team          = $this->create_team( 'Reviewers', array(), array( $team_mailbox ), array() );

		$this->actingAs( $this->create_user( User::ROLE_ADMIN ) )
			->post(
				route( 'users.create' ),
				array(
					'role'             => User::ROLE_USER,
					'first_name'       => 'Rita',
					'email'            => 'rita@example.org',
					'password'         => 'not-used-here',
					'mailboxes'        => array( $other_mailbox->id ),
					'wporgteams_teams' => array( $team->id ),
				)
			)
			->assertRedirect();

		$user = User::query()->where( 'email', 'rita@example.org' )->firstOrFail();
		$this->assertSame( array( (int) $user->id ), Teams::member_ids( $team->fresh() ) );
		$this->assertSame( array( (int) $team_mailbox->id ), $this->mailbox_ids( $user ) );
	}

	/**
	 * Saving a mailbox's Permissions page keeps its teams' members, whoever was checked.
	 *
	 * @return void
	 */
	public function test_mailbox_permissions_keep_team_members(): void {
		$mailbox  = $this->create_mailbox();
		$member   = $this->create_user( User::ROLE_USER );
		$outsider = $this->create_user( User::ROLE_USER );
		$team     = $this->create_team( 'Reviewers', array( (int) $member->id ), array( $mailbox ), array() );

		// The Teams module adds the mailbox's teams to what the page sent.
		$user_ids = \Eventy::filter( 'mailbox.permission_users', array( (int) $outsider->id, (int) $team->id ), $mailbox->id );

		sort( $user_ids );
		$this->assertSame( array( (int) $member->id, (int) $team->id ), $user_ids );
	}

	/**
	 * The permissions go before the form group of the team form's Save button, or nowhere if there's none.
	 *
	 * @dataProvider data_team_form
	 *
	 * @param string      $html     The team form.
	 * @param string|null $expected The form with the fields, or null.
	 * @return void
	 */
	public function test_inserts_permissions_before_save( string $html, ?string $expected ): void {
		$this->assertSame( $expected, TeamForm::insert( $html, '<FIELDS>' ) );
	}

	/**
	 * Team forms.
	 *
	 * @return array
	 */
	public function data_team_form(): array {
		$mailboxes = '<form><div class="form-group margin-bottom-0">mailboxes</div>';
		$save      = '<div class="form-group"><button type="submit" class="btn">Save</button></div></form>';

		return array(
			'save button'    => array( $mailboxes . $save, $mailboxes . '<FIELDS>' . $save ),
			'no save button' => array( $mailboxes . '</form>', null ),
		);
	}

	/**
	 * Creates a team the way the Teams module stores it.
	 *
	 * @param string    $name        Name.
	 * @param int[]     $member_ids  Members' IDs.
	 * @param Mailbox[] $mailboxes   Mailboxes.
	 * @param array     $permissions Permissions it grants.
	 * @return User
	 */
	private function create_team( string $name, array $member_ids, array $mailboxes, array $permissions ): User {
		$team = factory( User::class )->create(
			array(
				'first_name' => $name,
				'last_name'  => Teams::LAST_NAME,
				'email'      => Teams::EMAIL_PREFIX . random_int( 1, PHP_INT_MAX ) . '@example.org',
				'status'     => User::STATUS_DELETED,
				'type'       => User::TYPE_ROBOT,
			)
		);
		$team->mailboxes()->sync( array_map( static fn ( Mailbox $mailbox ): int => (int) $mailbox->id, $mailboxes ) );
		Teams::set_member_ids( $team, $member_ids );
		Teams::set_permissions( $team, array_map( 'strval', $permissions ) );
		$team->save();

		return $team;
	}

	/**
	 * A user's mailbox IDs, in order.
	 *
	 * @param User $user User.
	 * @return int[]
	 */
	private function mailbox_ids( User $user ): array {
		$ids = array_map( 'intval', $user->mailboxes()->pluck( 'mailboxes.id' )->all() );
		sort( $ids );

		return $ids;
	}
}
