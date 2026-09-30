<?php
/**
 * Tests for connecting users to WordPress.org accounts.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;

require_once __DIR__ . '/SsoTestCase.php';

/**
 * Covers the user forms, the account lookup, and the connect command.
 */
final class UsersTest extends SsoTestCase {

	/**
	 * Administrator doing the work, logged in through WordPress.org.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Creates the administrator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->admin = $this->create_user( User::ROLE_ADMIN );
		$this->log_in( $this->admin, 'boss' );
	}

	/**
	 * The create form asks for a WordPress.org username.
	 *
	 * @return void
	 */
	public function test_create_form_asks_for_username(): void {
		// Shared by FreeScout's web middleware on a real page.
		\View::share( 'errors', new \Illuminate\Support\ViewErrorBag() );

		ob_start();
		\Eventy::action( 'user.create.before_email' );

		$this->assertStringContainsString( 'name="wporg_username"', (string) ob_get_clean() );
	}

	/**
	 * New users are filled in from, and connected to, their WordPress.org account.
	 *
	 * @return void
	 */
	public function test_creates_user_from_account(): void {
		$this->create( 'Rita' )->assertRedirect();

		$user = User::query()->where( 'email', 'rita@example.org' )->first();

		$this->assertNotNull( $user );
		$this->assertSame( 'Rita', $user->first_name );
		$this->assertSame( 'Reviewer', $user->last_name );
		$this->assertSame( 'rita', Account::for_user( (int) $user->id )->username );
		$this->assertNull( $user->invite_hash );
		$this->assertEquals( User::INVITE_STATE_ACTIVATED, $user->invite_state );
	}

	/**
	 * A username that isn't on WordPress.org creates no user.
	 *
	 * @return void
	 */
	public function test_refuses_unknown_username(): void {
		$this->create( 'nobody' )->assertRedirect( route( 'users.create' ) )->assertSessionHasErrors( 'wporg_username' );

		$this->assertFalse( User::query()->where( 'email', 'forged@example.org' )->exists() );
	}

	/**
	 * A username is required.
	 *
	 * @return void
	 */
	public function test_requires_username(): void {
		$this->create( '' )->assertSessionHasErrors( 'wporg_username' );
	}

	/**
	 * One WordPress.org account can't belong to two users.
	 *
	 * @return void
	 */
	public function test_refuses_account_that_is_already_connected(): void {
		Account::connect( (int) $this->create_user()->id, 'rita' );

		$this->create( 'rita' )->assertSessionHasErrors( 'wporg_username' );
	}

	/**
	 * The lookup previews an account for the create form.
	 *
	 * @return void
	 */
	public function test_lookup_returns_account(): void {
		$data = json_decode( (string) $this->get( route( 'wporgsso.lookup', array( 'username' => 'rita' ) ) )->assertStatus( 200 )->getContent(), true );

		$this->assertSame( 'rita', $data['user']['username'] );
		$this->assertSame( 'rita@example.org', $data['user']['email'] );
		$this->assertNull( $data['connected_to'] );

		$this->get( route( 'wporgsso.lookup', array( 'username' => 'nobody' ) ) )->assertStatus( 404 );
	}

	/**
	 * Agents who may manage users look up accounts too, but only administrators see the private email address.
	 *
	 * @return void
	 */
	public function test_lookup_hides_email_from_non_administrators(): void {
		$manager              = $this->create_user( User::ROLE_USER );
		$manager->permissions = array( User::PERM_EDIT_USERS => true );
		$manager->save();
		$this->log_in( $manager, 'manager' );

		$data = json_decode( (string) $this->get( route( 'wporgsso.lookup', array( 'username' => 'rita' ) ) )->assertStatus( 200 )->getContent(), true );

		$this->assertSame( 'rita', $data['user']['username'] );
		$this->assertArrayNotHasKey( 'email', $data['user'] );
	}

	/**
	 * Only those who can create users can look up accounts.
	 *
	 * @return void
	 */
	public function test_lookup_requires_permission_to_create_users(): void {
		$this->log_in( $this->create_user( User::ROLE_USER ), 'agent' );

		$this->get( route( 'wporgsso.lookup', array( 'username' => 'rita' ) ) )->assertStatus( 403 );
	}

	/**
	 * The profile form can't change what comes from WordPress.org, or the account.
	 *
	 * @return void
	 */
	public function test_profile_keeps_wordpress_org_fields(): void {
		$user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $user->id, 'agent' );
		$email = $user->email;

		$this->save_profile( $user, 'rita' );

		$user->refresh();
		$this->assertSame( $email, $user->email );
		$this->assertNotSame( 'Forged', $user->first_name );
		$this->assertSame( 'Support', $user->job_title );
		$this->assertSame( 'agent', Account::for_user( (int) $user->id )->username );
	}

	/**
	 * The profile saves without the name and email fields, which the browser leaves out when they're disabled.
	 *
	 * @return void
	 */
	public function test_profile_saves_without_wordpress_org_fields(): void {
		$user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $user->id, 'agent' );

		$this->post(
			route( 'users.profile.save', array( 'id' => $user->id ) ),
			array(
				'_token'      => csrf_token(),
				'job_title'   => 'Support',
				'timezone'    => 'UTC',
				'time_format' => User::TIME_FORMAT_24,
			)
		);

		$this->assertFalse( session()->has( 'errors' ) );
		$this->assertSame( 'Support', $user->refresh()->job_title );
	}

	/**
	 * Photos can't be uploaded or deleted; the photo is the avatar.
	 *
	 * @return void
	 */
	public function test_photo_is_the_avatar(): void {
		$user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $user->id, 'agent' );

		$file = (string) tempnam( sys_get_temp_dir(), 'photo' );
		file_put_contents( $file, self::image( 0, 255, 0 ) );

		$this->call(
			'POST',
			route( 'users.profile.save', array( 'id' => $user->id ) ),
			array(
				'_token'      => csrf_token(),
				'timezone'    => 'UTC',
				'time_format' => User::TIME_FORMAT_24,
			),
			array(),
			array( 'photo_url' => new \Illuminate\Http\UploadedFile( $file, 'me.png', 'image/png', null, null, true ) )
		);
		unlink( $file );
		$this->assertEmpty( $user->refresh()->photo_url );

		$this->post(
			route( 'users.ajax' ),
			array(
				'_token'  => csrf_token(),
				'action'  => 'delete_photo',
				'user_id' => $user->id,
			)
		)->assertStatus( 403 );
	}

	/**
	 * The photo refusal doesn't tell others which users are connected; core turns them away.
	 *
	 * @return void
	 */
	public function test_photo_refusal_is_only_for_those_who_may_change_the_user(): void {
		$user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $user->id, 'rita' );
		\Auth::logout();

		$this->post(
			route( 'users.ajax' ),
			array(
				'_token'  => csrf_token(),
				'action'  => 'delete_photo',
				'user_id' => $user->id,
			)
		)->assertRedirect( route( 'login' ) );
	}

	/**
	 * Users who aren't connected keep their profile and photo as they were.
	 *
	 * @return void
	 */
	public function test_unconnected_user_saves_profile_and_deletes_photo(): void {
		$user = $this->create_user( User::ROLE_USER );

		// Like a browser, which sends an empty photo field.
		$this->call(
			'POST',
			route( 'users.profile.save', array( 'id' => $user->id ) ),
			array(
				'_token'      => csrf_token(),
				'first_name'  => 'Renamed',
				'email'       => $user->email,
				'timezone'    => 'UTC',
				'time_format' => User::TIME_FORMAT_24,
			),
			array(),
			array(
				'photo_url' => array(
					'name'     => '',
					'type'     => '',
					'tmp_name' => '',
					'error'    => UPLOAD_ERR_NO_FILE,
					'size'     => 0,
				),
			)
		);

		$this->assertFalse( session()->has( 'errors' ) );
		$this->assertSame( 'Renamed', $user->refresh()->first_name );

		$this->post(
			route( 'users.ajax' ),
			array(
				'_token'  => csrf_token(),
				'action'  => 'delete_photo',
				'user_id' => $user->id,
			)
		)->assertStatus( 200 );
	}

	/**
	 * Administrators connect an existing user on their profile, once.
	 *
	 * @return void
	 */
	public function test_admin_connects_existing_user_on_profile(): void {
		$user = $this->create_user( User::ROLE_USER );

		$this->save_profile( $user, 'Rita' );

		$this->assertFalse( session()->has( 'errors' ) );
		$this->assertSame( 'rita', Account::for_user( (int) $user->id )->username );
		$this->assertSame( 'rita@example.org', $user->refresh()->email );
	}

	/**
	 * Users can't connect themselves.
	 *
	 * @return void
	 */
	public function test_user_cannot_connect_self(): void {
		$user = $this->create_user( User::ROLE_USER );
		$this->actingAs( $user );

		$this->save_profile( $user, 'rita' );

		$this->assertNull( Account::for_user( (int) $user->id ) );
	}

	/**
	 * Agents who can't add users can't look up accounts through the create form either.
	 *
	 * @return void
	 */
	public function test_create_form_does_not_look_up_accounts_for_agents(): void {
		$this->log_in( $this->create_user( User::ROLE_USER ), 'agent' );
		$this->accounts = array();

		$this->create( 'rita' )->assertStatus( 403 );
	}

	/**
	 * Passwords can't be changed.
	 *
	 * @return void
	 */
	public function test_password_page_is_closed(): void {
		$this->get( route( 'users.password', array( 'id' => $this->admin->id ) ) )
			->assertRedirect( route( 'users.profile', array( 'id' => $this->admin->id ) ) );
	}

	/**
	 * Even with the break-glass switch on, administrators can't email users a new password or an invite.
	 *
	 * @return void
	 */
	public function test_password_emails_stay_closed_in_break_glass(): void {
		config( array( 'wporgsso.password_login' => true ) );
		$user = $this->create_user( User::ROLE_USER );

		$this->post(
			route( 'users.ajax' ),
			array(
				'_token'  => csrf_token(),
				'action'  => 'reset_password',
				'user_id' => $user->id,
			)
		)->assertStatus( 403 );
	}

	/**
	 * `wporgsso:password` gives administrators, and only them, a password for break-glass logins.
	 *
	 * @return void
	 */
	public function test_password_command(): void {
		$admin = $this->create_user( User::ROLE_ADMIN );
		$user  = $this->create_user( User::ROLE_USER );

		$this->assertSame( 0, \Artisan::call( 'wporgsso:password', array( 'email' => $admin->email ) ) );
		$this->assertTrue( \Hash::check( trim( \Artisan::output() ), (string) $admin->refresh()->password ) );

		$this->assertSame( 1, \Artisan::call( 'wporgsso:password', array( 'email' => $user->email ) ) );
	}

	/**
	 * Administrators can't email users a new password or an invite.
	 *
	 * @return void
	 */
	public function test_password_emails_are_closed(): void {
		$user = $this->create_user( User::ROLE_USER );

		foreach ( array( 'reset_password', 'send_invite' ) as $action ) {
			$this->post(
				route( 'users.ajax' ),
				array(
					'_token'  => csrf_token(),
					'action'  => $action,
					'user_id' => $user->id,
				)
			)->assertStatus( 403 );
		}
	}

	/**
	 * Deleting a user frees their account.
	 *
	 * @return void
	 */
	public function test_deleting_user_disconnects_account(): void {
		$user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $user->id, 'rita' );

		\Eventy::action( 'user.deleted', $user, $this->admin );

		$this->assertNull( Account::for_user( (int) $user->id ) );
	}

	/**
	 * The first administrators are connected from the command line.
	 *
	 * @return void
	 */
	public function test_connect_command(): void {
		$user = $this->create_user( User::ROLE_ADMIN );

		$this->assertSame(
			0,
			\Artisan::call(
				'wporgsso:connect',
				array(
					'email'    => $user->email,
					'username' => 'Rita',
				)
			)
		);
		$this->assertSame( 'rita', Account::for_user( (int) $user->id )->username );

		// Filled in right away, since the profile no longer lets anyone change it.
		$user->refresh();
		$this->assertSame( 'Rita', $user->first_name );
		$this->assertSame( 'rita@example.org', $user->email );

		$this->assertSame(
			1,
			\Artisan::call(
				'wporgsso:connect',
				array(
					'email'    => $user->email,
					'username' => 'nobody',
				)
			)
		);
	}

	/**
	 * The connect command doesn't switch a connected user to another account without --force.
	 *
	 * @return void
	 */
	public function test_connect_command_needs_force_to_switch(): void {
		$user = $this->create_user( User::ROLE_ADMIN );
		Account::connect( (int) $user->id, 'old-account' );

		$this->assertSame(
			1,
			\Artisan::call(
				'wporgsso:connect',
				array(
					'email'    => $user->email,
					'username' => 'rita',
				)
			)
		);
		$this->assertSame( 'old-account', Account::for_user( (int) $user->id )->username );

		$this->assertSame(
			0,
			\Artisan::call(
				'wporgsso:connect',
				array(
					'email'    => $user->email,
					'username' => 'rita',
					'--force'  => true,
				)
			)
		);
		$this->assertSame( 'rita', Account::for_user( (int) $user->id )->username );
	}

	/**
	 * Before WordPress.org is enforced, new users still get core's password or invite.
	 *
	 * @return void
	 */
	public function test_creates_user_with_invite_before_enforced(): void {
		config( array( 'wporgsso.idp.cert' => '' ) );

		$this->create( 'rita' );

		$user = User::query()->where( 'email', 'rita@example.org' )->first();
		$this->assertNotNull( $user );
		$this->assertNotEquals( User::INVITE_STATE_ACTIVATED, $user->invite_state );
		$this->assertSame( 'rita', Account::for_user( (int) $user->id )->username );
	}

	/**
	 * In break-glass mode, new users get no invite, whose setup link would be refused.
	 *
	 * @return void
	 */
	public function test_break_glass_creates_user_without_invite(): void {
		config( array( 'wporgsso.password_login' => true ) );

		$this->create( 'rita' )->assertRedirect();

		$user = User::query()->where( 'email', 'rita@example.org' )->first();
		$this->assertNotNull( $user );
		$this->assertEquals( User::INVITE_STATE_ACTIVATED, $user->invite_state );
	}

	/**
	 * An account whose email a user already has points to connecting that user, instead of core's email error.
	 *
	 * @return void
	 */
	public function test_create_refuses_account_whose_email_is_taken(): void {
		$existing        = $this->create_user( User::ROLE_USER );
		$existing->email = 'rita@example.org';
		$existing->save();

		$this->create( 'rita' )->assertSessionHasErrors( 'wporg_username' );

		$this->assertSame( 1, User::query()->where( 'email', 'rita@example.org' )->count() );
		$this->assertNull( Account::for_user( (int) $existing->id ) );
	}

	/**
	 * A deleted user's email doesn't point to their profile, which is gone.
	 *
	 * @return void
	 */
	public function test_create_names_deleted_user_whose_email_is_taken(): void {
		$deleted         = $this->create_user( User::ROLE_USER );
		$deleted->email  = 'rita@example.org';
		$deleted->status = User::STATUS_DELETED;
		$deleted->save();

		$this->create( 'rita' )->assertSessionHasErrors( 'wporg_username' );

		$this->assertStringContainsString( 'a deleted user', (string) session( 'errors' )->first( 'wporg_username' ) );
		$this->assertSame( 1, User::query()->where( 'email', 'rita@example.org' )->count() );
	}

	/**
	 * A username sent as an array is refused like a missing one, not with an error page.
	 *
	 * @return void
	 */
	public function test_create_refuses_array_username(): void {
		$this->post(
			'/users/wizard',
			array(
				'_token'         => csrf_token(),
				'role'           => User::ROLE_USER,
				'wporg_username' => array( 'rita' ),
			)
		)->assertSessionHasErrors( 'wporg_username' );
	}

	/**
	 * Logs a user in as if through WordPress.org.
	 *
	 * @param User   $user     User.
	 * @param string $username WordPress.org username to connect them to.
	 * @return void
	 */
	private function log_in( User $user, string $username ): void {
		Account::connect( (int) $user->id, $username );

		$this->actingAs( $user )->withSession(
			array(
				WPOrgSSOServiceProvider::SESSION_USERNAME => $username,
				WPOrgSSOServiceProvider::SESSION_CHECKED_AT => time(),
			)
		);
	}

	/**
	 * Submits the create form; names and email are forged, to prove they come from WordPress.org.
	 *
	 * @param string $username WordPress.org username.
	 * @return \Illuminate\Foundation\Testing\TestResponse
	 */
	private function create( string $username ): \Illuminate\Foundation\Testing\TestResponse {
		return $this->post(
			'/users/wizard',
			array(
				'_token'         => csrf_token(),
				'role'           => User::ROLE_USER,
				'wporg_username' => $username,
				'first_name'     => 'Forged',
				'email'          => 'forged@example.org',
				'send_invite'    => 1,
			)
		);
	}

	/**
	 * Submits the profile form.
	 *
	 * @param User   $user     User being edited.
	 * @param string $username WordPress.org username.
	 * @return \Illuminate\Foundation\Testing\TestResponse
	 */
	private function save_profile( User $user, string $username ): \Illuminate\Foundation\Testing\TestResponse {
		return $this->post(
			route( 'users.profile.save', array( 'id' => $user->id ) ),
			array(
				'_token'         => csrf_token(),
				'first_name'     => 'Forged',
				'email'          => 'forged@example.org',
				'job_title'      => 'Support',
				'timezone'       => 'UTC',
				'time_format'    => User::TIME_FORMAT_24,
				'wporg_username' => $username,
			)
		);
	}
}
