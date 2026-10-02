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
