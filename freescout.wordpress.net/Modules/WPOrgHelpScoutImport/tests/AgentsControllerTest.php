<?php
/**
 * Tests for the Users page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Conversation;
use App\Thread;
use App\User;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Entities\Person;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers the list of HelpScout users and teams, and choosing who they're credited to.
 */
final class AgentsControllerTest extends ImportTestCase {

	/**
	 * Administrator using the page.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Has HelpScout list two mailboxes, three people, and a team, and logs an administrator in.
	 *
	 * Ada matches by email; Bo and Cy have no FreeScout user yet.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();
		$this->helpscout->only(
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

		$ada  = self::helpscout_user( 55, 'Ada', 'Agent', 'agent@example.org' );
		$bo   = self::helpscout_user( 56, 'Bo', 'Newcomer', 'bo@example.org' );
		$cy   = self::helpscout_user( 57, 'Cy', 'Elsewhere', 'cy@helpscout.example' );
		$team = array(
			'id'        => 90,
			'type'      => 'team',
			'firstName' => 'Photo Moderators',
			'lastName'  => '',
		);
		$this->helpscout->only( 'GET', 'v2/users', self::list( 'users', array( $ada, $bo, $cy, $team ) ) );
		$this->helpscout->only_mailbox_users( 77, self::list( 'users', array( $ada, $bo, $team ) ) );
		$this->helpscout->only_mailbox_users( 78, self::list( 'users', array( $ada, $cy ) ) );

		$this->admin = $this->create_user( User::ROLE_ADMIN );
		$this->actingAs( $this->admin );
	}

	/**
	 * The page lists people with their FreeScout users, or that they'll get one, and teams apart.
	 *
	 * @return void
	 */
	public function test_page_lists_people_and_their_users(): void {
		$page = $this->get( route( 'wporghelpscoutimport.agents' ) )->getContent();

		$this->assertMatchesRegularExpression( '#<tr id="agent-55">.*?Ada Agent <small class="text-help">&lt;agent@example.org&gt; \(same email\)</small>#s', $page );
		$this->assertMatchesRegularExpression( '#<tr id="agent-56">.*?Created when their mailbox is imported#s', $page );
		$this->assertStringContainsString( 'Photos, Themes', $page );
		$this->assertStringNotContainsString( 'id="agent-90"', $page );
		$this->assertMatchesRegularExpression( '#<tr id="team-90">\s*<td>Photo Moderators</td>\s*<td>\s*<em>None: imported unassigned</em>#', $page );
	}

	/**
	 * A FreeScout team with a HelpScout team's name is shown as its team, and teams can be chosen.
	 *
	 * @return void
	 */
	public function test_teams_are_matched_by_name_and_can_be_chosen(): void {
		$moderators = $this->create_team( 'Photo Moderators' );
		$other      = $this->create_team( 'Reviewers' );

		$this->assertMatchesRegularExpression( '#<tr id="team-90">.*?Photo Moderators <small class="text-help">\(same name\)</small>#s', $this->get( route( 'wporghelpscoutimport.agents' ) )->getContent() );
		$this->assertSame(
			(int) $moderators->id,
			(int) ( new People() )->team(
				array(
					'id'    => 90,
					'type'  => 'team',
					'first' => 'Photo Moderators',
				)
			)->id
		);

		$this->post( route( 'wporghelpscoutimport.agents.save' ), array( 'teams' => array( 90 => $other->id ) ) );

		$this->assertSame(
			(int) $other->id,
			(int) ( new People() )->team(
				array(
					'id'    => 90,
					'type'  => 'team',
					'first' => 'Photo Moderators',
				)
			)->id
		);
	}

	/**
	 * Choosing someone after an import credits what's imported for them to the user chosen: replies, notes, and closing.
	 *
	 * @return void
	 */
	public function test_choosing_later_credits_whats_imported(): void {
		$this->agent->email = 'ada@wordpress.example';
		$this->agent->save();
		( new Importer( app( HelpScout::class ), new People( app( HelpScout::class ) ) ) )->import( $this->conversation(), $this->mailbox );

		$created = User::query()->where( 'email', 'agent@example.org' )->firstOrFail();
		$reply   = static function (): Thread {
			return Thread::query()->findOrFail( ImportedThread::query()->where( 'helpscout_id', 2002 )->value( 'thread_id' ) );
		};
		$this->assertSame( (int) $created->id, (int) $reply()->created_by_user_id );

		$this->post( route( 'wporghelpscoutimport.agents.save' ), array( 'agents' => array( 55 => $this->agent->id ) ) );

		$conversation = Conversation::query()->findOrFail( ImportedConversation::query()->value( 'conversation_id' ) );
		$this->assertSame( (int) $this->agent->id, (int) $reply()->created_by_user_id );
		$this->assertSame( (int) $this->agent->id, (int) $reply()->user_id );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->closed_by_user_id );
		$this->assertSame( (int) $this->agent->id, (int) Agent::query()->where( 'helpscout_user_id', 55 )->value( 'user_id' ) );
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
		$this->assertMatchesRegularExpression( '#<tr id="agent-70">\s*<td>\s*Dee Parted<br/>\s*<small>dee@example.org</small><br/>\s*<small class="text-help">No longer in HelpScout</small>#', $page );
		$this->assertSame( 1, substr_count( $page, 'id="agent-55"' ) );

		$this->assertStringNotContainsString( 'id="agent-70"', $this->get( route( 'wporghelpscoutimport.agents', array( 'mailbox' => 77 ) ) )->getContent() );
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
	 * Rows left as they are stay as they are; people can't be teams, and teams can't be people.
	 *
	 * @return void
	 */
	public function test_only_rows_changed_to_fitting_users_are_saved(): void {
		$team = $this->create_team( 'Reviewers' );
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
					55 => '',
					56 => $team->id,
					57 => $this->agent->id,
				),
				'teams'  => array( 90 => $this->admin->id ),
			)
		)->assertRedirect( route( 'wporghelpscoutimport.agents' ) );

		$this->assertSame( (int) $this->admin->id, (int) Agent::query()->where( 'helpscout_user_id', 55 )->value( 'user_id' ) );
		$this->assertFalse( Agent::query()->where( 'helpscout_user_id', 56 )->exists() );
		$this->assertSame( (int) $this->agent->id, (int) Agent::query()->where( 'helpscout_user_id', 57 )->value( 'user_id' ) );
		$this->assertFalse( Agent::query()->where( 'helpscout_user_id', 90 )->exists() );
	}

	/**
	 * The CSV lists HelpScout's people, not teams, with their FreeScout users, and a column for WordPress.org usernames.
	 *
	 * @return void
	 */
	public function test_csv_lists_helpscout_users(): void {
		$response = $this->get( route( 'wporghelpscoutimport.agents.export' ) );

		$response->assertStatus( 200 );
		$this->assertStringContainsString( 'attachment; filename="helpscout-users.csv"', (string) $response->headers->get( 'Content-Disposition' ) );

		$lines = explode( "\n", trim( $response->getContent() ) );
		$this->assertSame( 'helpscout_id,first_name,last_name,email,mailboxes,former,freescout_user,wporg_username', $lines[0] );
		$this->assertContains( '55,Ada,Agent,agent@example.org,"Photos; Themes",no,agent@example.org,', $lines );
		$this->assertContains( '56,Bo,Newcomer,bo@example.org,Photos,no,,', $lines );
		$this->assertCount( 4, $lines );
	}

	/**
	 * Checking a filled-in CSV shows what connecting would do, and changes nothing.
	 *
	 * @return void
	 */
	public function test_csv_is_checked_before_connecting(): void {
		$this->use_wordpress_org();

		$page = $this->post( route( 'wporghelpscoutimport.agents.connect' ), array( 'csv' => $this->filled_csv() ) )->getContent();

		$this->assertStringContainsString( 'Connect this FreeScout user', $page );
		$this->assertStringContainsString( 'Create a FreeScout user, connected to it', $page );
		$this->assertStringContainsString( 'There is no WordPress.org account with that username.', $page );
		$this->assertStringContainsString( 'Connect 2 users', $page );
		$this->assertFalse( User::query()->where( 'email', 'bo@wordpress.example' )->exists() );
		$this->assertSame( '', Account::username_for( (int) $this->agent->id ) );
	}

	/**
	 * Connecting creates users without one, connects them, takes their WordPress.org details, and credits them.
	 *
	 * @return void
	 */
	public function test_csv_connects_users(): void {
		$this->use_wordpress_org();

		$this->post(
			route( 'wporghelpscoutimport.agents.connect' ),
			array(
				'csv'   => $this->filled_csv(),
				'apply' => 1,
			)
		)->assertSessionHas( 'flash_success', 'Connected 2 HelpScout users to WordPress.org accounts.' );

		$bo = User::query()->where( 'email', 'bo@wordpress.example' )->firstOrFail();
		$this->assertSame( 'bonew', Account::username_for( (int) $bo->id ) );
		$this->assertSame( User::STATUS_ACTIVE, (int) $bo->status );
		$this->assertSame( (int) $bo->id, (int) Agent::query()->where( 'helpscout_user_id', 56 )->value( 'user_id' ) );

		$this->assertSame( 'adaagent', Account::username_for( (int) $this->agent->id ) );
		$this->assertSame( 'ada@wordpress.example', $this->agent->fresh()->email );
		$this->assertFalse( Agent::query()->where( 'helpscout_user_id', 57 )->exists() );
	}

	/**
	 * Someone whose account is connected to a FreeScout user already is credited to that user.
	 *
	 * @return void
	 */
	public function test_connected_account_is_that_user(): void {
		$this->use_wordpress_org();
		Account::connect( (int) $this->admin->id, 'adminuser' );
		$users = User::query()->count();

		$this->post(
			route( 'wporghelpscoutimport.agents.connect' ),
			array(
				'csv'   => "helpscout_id,wporg_username\n57,adminuser\n",
				'apply' => 1,
			)
		);

		$this->assertSame( (int) $this->admin->id, (int) Agent::query()->where( 'helpscout_user_id', 57 )->value( 'user_id' ) );
		$this->assertSame( $users, User::query()->count() );
	}

	/**
	 * Without WP.org SSO, nothing is connected.
	 *
	 * @return void
	 */
	public function test_connecting_needs_wporgsso(): void {
		$this->post( route( 'wporghelpscoutimport.agents.connect' ), array( 'csv' => $this->filled_csv() ) )->assertSessionHas( 'flash_error' );

		$this->assertFalse( User::query()->where( 'email', 'bo@example.org' )->exists() );
	}

	/**
	 * The CSV, filled in: Ada and Bo have accounts, and Cy's username has none.
	 *
	 * @return string
	 */
	private function filled_csv(): string {
		return "helpscout_id,first_name,last_name,email,mailboxes,former,freescout_user,wporg_username\n"
			. "55,Ada,Agent,agent@example.org,Photos,no,agent@example.org,adaagent\n"
			. "56,Bo,Newcomer,bo@example.org,Photos,no,,BoNew\n"
			. "57,Cy,Elsewhere,cy@helpscout.example,Themes,no,,nobody\n";
	}

	/**
	 * Turns WP.org SSO on, with a fake account.php.
	 *
	 * @return void
	 */
	private function use_wordpress_org(): void {
		$this->app->register( \Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider::class );

		$accounts = array(
			'adaagent'  => array(
				'username'   => 'adaagent',
				'first_name' => 'Ada',
				'last_name'  => 'Agent',
				'email'      => 'ada@wordpress.example',
			),
			'bonew'     => array(
				'username'   => 'bonew',
				'first_name' => 'Bo',
				'last_name'  => 'Newcomer',
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
				static function ( RequestInterface $request ) use ( $accounts ): PromiseInterface {
					$username = strtolower( (string) ( json_decode( (string) $request->getBody(), true )['username'] ?? '' ) );

					return new FulfilledPromise( new Response( 200, array(), (string) json_encode( array( 'user' => $accounts[ $username ] ?? null ) ) ) );
				}
			)
		);
	}

	/**
	 * A FreeScout team, as the Teams module makes them: a robot user.
	 *
	 * @param string $name Team name.
	 * @return User
	 */
	private function create_team( string $name ): User {
		return factory( User::class )->create(
			array(
				'first_name' => $name,
				'last_name'  => '',
				'email'      => uniqid( 'team-' ) . '@example.org',
				'type'       => User::TYPE_ROBOT,
			)
		);
	}
}
