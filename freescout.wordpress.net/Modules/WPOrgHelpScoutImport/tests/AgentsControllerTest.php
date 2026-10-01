<?php
/**
 * Tests for the Agents page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\User;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use App\Thread;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Entities\Person;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\Client;
use Psr\Http\Message\RequestInterface;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers the list of HelpScout users, choosing FreeScout users, and creating them from WordPress.org accounts.
 */
final class AgentsControllerTest extends ImportTestCase {

	/**
	 * Administrator using the page.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * WordPress.org accounts account.php knows, by username.
	 *
	 * @var array[]
	 */
	private $accounts = array();

	/**
	 * Has HelpScout list two mailboxes, three people, and a team, and logs an administrator in.
	 *
	 * Ada matches by email, Cy has a FreeScout namesake, and Bo has neither.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();
		$this->helpscout->on(
			'GET',
			'v2/mailboxes',
			self::list(
				'mailboxes',
				array(
					array(
						'id'   => 77,
						'name' => 'Photos',
					),
					array(
						'id'   => 78,
						'name' => 'Themes',
					),
				)
			)
		);

		$ada  = self::person( 55, 'Ada', 'Agent', 'agent@example.org' );
		$bo   = self::person( 56, 'Bo', 'Gone', 'bo@example.org' );
		$cy   = self::person( 57, 'Cy', 'Namesake', 'cy@helpscout.example' );
		$team = array(
			'id'        => 90,
			'type'      => 'team',
			'firstName' => 'A8C Legal',
			'lastName'  => '',
		);
		$this->helpscout->on( 'GET', 'v2/users', self::list( 'users', array( $ada, $bo, $cy, $team ) ) );
		$this->helpscout->only_mailbox_users( 77, self::list( 'users', array( $ada, $bo, $team ) ) );
		$this->helpscout->only_mailbox_users( 78, self::list( 'users', array( $ada, $cy ) ) );

		factory( User::class )->create(
			array(
				'first_name' => 'Cy',
				'last_name'  => 'Namesake',
				'email'      => 'cy@wordpress.example',
			)
		);

		$this->admin = $this->create_user( User::ROLE_ADMIN );
		$this->actingAs( $this->admin );
	}

	/**
	 * The page lists people, not teams, says who each is credited to, and warns about those credited to the robot.
	 *
	 * @return void
	 */
	public function test_page_lists_people_and_who_theyre_credited_to(): void {
		$page = $this->get( route( 'wporghelpscoutimport.agents' ) )->getContent();

		$this->assertStringContainsString( 'FreeScout users are never created from HelpScout.', $page );
		$this->assertStringContainsString( '2 of 3 HelpScout users have no FreeScout user.', $page );
		$this->assertStringContainsString( 'Ada Agent <small class="text-help">(same email)</small>', $page );
		$this->assertMatchesRegularExpression( '#<tr id="agent-56"\s+class="danger"#', $page );
		$this->assertMatchesRegularExpression( '#<tr id="agent-57"\s+class="warning"#', $page );
		$this->assertStringContainsString( 'Suggested: Cy Namesake, with the same name.', $page );
		$this->assertStringContainsString( 'Photos, Themes', $page );
		$this->assertStringNotContainsString( 'id="agent-90"', $page );
		$this->assertStringContainsString( 'A8C Legal', $page );
	}

	/**
	 * Matching someone after an import credits their imported replies and notes to them; clearing it undoes that.
	 *
	 * @return void
	 */
	public function test_matching_later_credits_whats_imported(): void {
		$this->agent->email = 'ada@wordpress.example';
		$this->agent->save();
		( new Importer( app( HelpScout::class ), new People() ) )->import( $this->conversation(), $this->mailbox );

		$reply = static function (): Thread {
			return Thread::query()->findOrFail( ImportedThread::query()->where( 'helpscout_id', 2002 )->value( 'thread_id' ) );
		};
		$robot = User::query()->where( 'email', People::ROBOT_EMAIL )->value( 'id' );
		$this->assertSame( (int) $robot, (int) $reply()->created_by_user_id );

		$this->post( route( 'wporghelpscoutimport.agents.save' ), array( 'agents' => array( 55 => array( 'user_id' => $this->agent->id ) ) ) );
		$this->assertSame( (int) $this->agent->id, (int) $reply()->created_by_user_id );

		$this->post( route( 'wporghelpscoutimport.agents.save' ), array( 'agents' => array( 55 => array( 'user_id' => '' ) ) ) );
		$this->assertSame( (int) $robot, (int) $reply()->created_by_user_id );
	}

	/**
	 * Users deleted from HelpScout that an import met are listed for the whole account, but not for a mailbox.
	 *
	 * @return void
	 */
	public function test_former_users_are_listed(): void {
		Person::query()->create(
			array(
				'helpscout_user_id' => 70,
				'first_name'        => 'Dee',
				'last_name'         => 'Parted',
				'email'             => 'dee@example.org',
			)
		);
		Person::query()->create(
			array(
				'helpscout_user_id' => 55,
				'first_name'        => 'Ada',
				'last_name'         => 'Agent',
				'email'             => 'agent@example.org',
			)
		);

		$page = $this->get( route( 'wporghelpscoutimport.agents' ) )->getContent();
		$this->assertMatchesRegularExpression( '#<tr id="agent-70"[^>]*>\s*<td>\s*Dee Parted<br/>\s*<small>dee@example.org</small><br/>\s*<small class="text-help">No longer in HelpScout</small>#', $page );
		$this->assertSame( 1, substr_count( $page, 'id="agent-55"' ) );

		$this->assertStringNotContainsString( 'id="agent-70"', $this->get( route( 'wporghelpscoutimport.agents', array( 'mailbox' => 77 ) ) )->getContent() );
	}

	/**
	 * A name two FreeScout users share isn't suggested: it could be either.
	 *
	 * @return void
	 */
	public function test_shared_name_isnt_suggested(): void {
		factory( User::class )->create(
			array(
				'first_name' => 'Cy',
				'last_name'  => 'Namesake',
				'email'      => 'another-cy@example.org',
			)
		);

		$page = $this->get( route( 'wporghelpscoutimport.agents' ) )->getContent();

		$this->assertStringNotContainsString( 'Suggested: Cy Namesake', $page );
		$this->assertMatchesRegularExpression( '#<tr id="agent-57"\s+class="danger"#', $page );
	}

	/**
	 * The list can show only the people of one mailbox.
	 *
	 * @return void
	 */
	public function test_list_filters_by_mailbox(): void {
		$page = $this->get( route( 'wporghelpscoutimport.agents', array( 'mailbox' => 78 ) ) )->getContent();

		$this->assertStringContainsString( 'id="agent-55"', $page );
		$this->assertStringContainsString( 'id="agent-57"', $page );
		$this->assertStringNotContainsString( 'id="agent-56"', $page );
	}

	/**
	 * Every row is saved at once; none chosen goes back to the email, and robots can't be chosen.
	 *
	 * @return void
	 */
	public function test_choices_are_saved_for_every_row(): void {
		$robot       = $this->create_user();
		$robot->type = User::TYPE_ROBOT;
		$robot->save();
		Agent::query()->create(
			array(
				'helpscout_user_id' => 55,
				'user_id'           => $this->admin->id,
			)
		);

		$this->post(
			route( 'wporghelpscoutimport.agents.save' ),
			array(
				'agents' => array(
					55 => array( 'user_id' => '' ),
					56 => array( 'user_id' => $this->admin->id ),
					57 => array( 'user_id' => $robot->id ),
				),
			)
		)->assertRedirect( route( 'wporghelpscoutimport.agents' ) );

		$this->assertFalse( Agent::query()->where( 'helpscout_user_id', 55 )->exists() );
		$this->assertSame( (int) $this->admin->id, (int) Agent::query()->where( 'helpscout_user_id', 56 )->value( 'user_id' ) );
		$this->assertFalse( Agent::query()->where( 'helpscout_user_id', 57 )->exists() );
	}

	/**
	 * A WordPress.org username creates a user from that account, connected to it, and credits it; disabled when
	 * "Can log in" is unchecked, which leaves it out of the form.
	 *
	 * @return void
	 */
	public function test_username_creates_a_user_from_wordpress_org(): void {
		$this->use_wordpress_org();

		$this->post(
			route( 'wporghelpscoutimport.agents.save' ),
			array(
				'agents' => array(
					56 => array(
						'user_id'  => '',
						'username' => 'bogone',
					),
				),
			)
		)->assertSessionHas( 'flash_success' );

		$user = User::query()->where( 'email', 'bo@wordpress.example' )->firstOrFail();
		$this->assertSame( 'Bo', $user->first_name );
		$this->assertSame( User::STATUS_DISABLED, (int) $user->status );
		$this->assertSame( User::ROLE_USER, (int) $user->role );
		$this->assertTrue( $user->isDummyPassword() );
		$this->assertSame( 'bogone', Account::username_for( (int) $user->id ) );
		$this->assertSame( (int) $user->id, (int) Agent::query()->where( 'helpscout_user_id', 56 )->value( 'user_id' ) );
	}

	/**
	 * Someone who'll work in FreeScout can log in; an account already connected to a user is that user.
	 *
	 * @return void
	 */
	public function test_can_log_in_and_connected_accounts(): void {
		$this->use_wordpress_org();
		Account::connect( (int) $this->admin->id, 'adminuser' );

		$this->assertStringContainsString(
			'name="agents[56][can_log_in]" value="1" checked',
			$this->get( route( 'wporghelpscoutimport.agents' ) )->getContent()
		);

		$this->post(
			route( 'wporghelpscoutimport.agents.save' ),
			array(
				'agents' => array(
					56 => array(
						'username'   => 'bogone',
						'can_log_in' => '1',
					),
					57 => array( 'username' => 'adminuser' ),
				),
			)
		);

		$this->assertSame( User::STATUS_ACTIVE, (int) User::query()->where( 'email', 'bo@wordpress.example' )->value( 'status' ) );
		$this->assertSame( (int) $this->admin->id, (int) Agent::query()->where( 'helpscout_user_id', 57 )->value( 'user_id' ) );
	}

	/**
	 * A username without an account is reported, and changes nothing for that row.
	 *
	 * @return void
	 */
	public function test_unknown_username_is_reported(): void {
		$this->use_wordpress_org();

		$this->post(
			route( 'wporghelpscoutimport.agents.save' ),
			array(
				'agents' => array(
					56 => array(
						'user_id'  => $this->admin->id,
						'username' => 'nobody',
					),
				),
			)
		)->assertSessionHas( 'flash_error', 'nobody: There is no WordPress.org account with that username.' );

		$this->assertFalse( Agent::query()->where( 'helpscout_user_id', 56 )->exists() );
	}

	/**
	 * Without WP.org SSO, nobody is created, and the page doesn't offer it.
	 *
	 * @return void
	 */
	public function test_no_users_are_created_without_wporgsso(): void {
		$count = User::query()->count();

		$this->post( route( 'wporghelpscoutimport.agents.save' ), array( 'agents' => array( 56 => array( 'username' => 'bogone' ) ) ) )
			->assertSessionMissing( 'flash_error' );

		$this->assertSame( $count, User::query()->count() );
		$page = $this->get( route( 'wporghelpscoutimport.agents' ) )->getContent();
		$this->assertStringNotContainsString( 'placeholder="WordPress.org username"', $page );
		$this->assertStringContainsString( 'which needs WP.org SSO to be on', $page );
	}

	/**
	 * Turns WP.org SSO on, with a fake account.php.
	 *
	 * @return void
	 */
	private function use_wordpress_org(): void {
		$this->app->register( \Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider::class );

		$this->accounts = array(
			'bogone'    => array(
				'username'   => 'bogone',
				'first_name' => 'Bo',
				'last_name'  => 'Gone',
				'email'      => 'bo@wordpress.example',
			),
			'adminuser' => array(
				'username'   => 'adminuser',
				'first_name' => 'Admin',
				'last_name'  => 'User',
				'email'      => (string) $this->admin->email,
			),
		);

		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				10,
				function ( RequestInterface $request ): PromiseInterface {
					$username = (string) ( json_decode( (string) $request->getBody(), true )['username'] ?? '' );

					return new FulfilledPromise( new Response( 200, array(), (string) json_encode( array( 'user' => $this->accounts[ $username ] ?? null ) ) ) );
				}
			)
		);
	}

	/**
	 * A HelpScout user.
	 *
	 * @param int    $id    ID.
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @param string $email Email.
	 * @return array
	 */
	private static function person( int $id, string $first, string $last, string $email ): array {
		return array(
			'id'        => $id,
			'type'      => 'user',
			'firstName' => $first,
			'lastName'  => $last,
			'email'     => $email,
		);
	}

	/**
	 * A one-page list, as HelpScout answers it.
	 *
	 * @param string  $key   Key in `_embedded`.
	 * @param array[] $items Items.
	 * @return array
	 */
	private static function list( string $key, array $items ): array {
		return array(
			'_embedded' => array( $key => $items ),
			'page'      => array( 'totalPages' => 1 ),
		);
	}
}
