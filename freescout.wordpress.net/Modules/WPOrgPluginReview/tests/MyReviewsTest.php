<?php
/**
 * Tests for the My Reviews folder.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Folder;
use App\Mailbox;
use App\Thread;
use App\User;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\MyReviews;
use Modules\WPOrgPluginReview\Services\Reviewers;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which conversations each section shows, the folder in the mailbox's list, and its page.
 */
final class MyReviewsTest extends TestCase {

	/**
	 * The plugins team's mailbox.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * The reviewer whose reviews are shown.
	 *
	 * @var User
	 */
	private $reviewer;

	/**
	 * Another reviewer.
	 *
	 * @var User
	 */
	private $colleague;

	/**
	 * The user standing in for the Plugin Reviews team, which the Teams module assigns conversations to.
	 *
	 * @var User
	 */
	private $team;

	/**
	 * Registers the module and creates the mailbox and its reviewers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );

		// Routes registered after boot aren't in the name index yet.
		$this->app['router']->getRoutes()->refreshNameLookups();

		$this->mailbox   = $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' );
		$this->reviewer  = $this->create_user();
		$this->colleague = $this->create_user();
		$this->team      = $this->create_user();
	}

	/**
	 * Clears the modules' cache, which outlives the test's database.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\App\Module::clearModulesCache();

		parent::tearDown();
	}

	/**
	 * Each conversation shows in the first section it belongs to: replied to, then mentioned in a note, then with
	 * PRTRN in a note.
	 *
	 * @return void
	 */
	public function test_sections_show_each_conversation_once(): void {
		$replied           = $this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) ) );
		$replied_mentioned = $this->conversation(
			array(
				array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ),
				array( Thread::TYPE_NOTE, $this->colleague, $this->mention( $this->reviewer ) . ' yours. PRTRN' ),
			)
		);
		$mentioned         = $this->conversation( array( array( Thread::TYPE_NOTE, $this->colleague, $this->mention( $this->reviewer ) . ' could you look?' ) ) );
		$mentioned_needs   = $this->conversation(
			array(
				array( Thread::TYPE_NOTE, $this->colleague, 'PRTRN' ),
				array( Thread::TYPE_NOTE, $this->colleague, $this->mention( $this->reviewer ) ),
			)
		);
		$needs             = $this->conversation( array( array( Thread::TYPE_NOTE, $this->colleague, '<p>PRTRN, someone please take over.</p>' ) ) );

		// Their latest replies are at the same time, so the oldest conversation comes first.
		$this->assertSections(
			array(
				'participating'  => array( $replied, $replied_mentioned ),
				'mentioned'      => array( $mentioned, $mentioned_needs ),
				'needs_reviewer' => array( $needs ),
			),
			$this->sections()
		);
	}

	/**
	 * What doesn't count: notes as replies, drafts, mentions and PRTRN in replies, mentions of someone else, and
	 * conversations that are closed, spam, or deleted.
	 *
	 * @return void
	 */
	public function test_sections_leave_out_what_doesnt_count(): void {
		$this->conversation( array( array( Thread::TYPE_NOTE, $this->reviewer, 'Looked at it.' ) ) );
		$this->conversation( array( array( Thread::TYPE_MESSAGE, $this->colleague, $this->mention( $this->reviewer ) . ' PRTRN' ) ) );
		$this->conversation( array( array( Thread::TYPE_NOTE, $this->colleague, $this->mention( $this->colleague ) . ' <b data-mentioned-id="' . $this->reviewer->id . '0">@Someone</b>' ) ) );

		$draft = $this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Not sent. PRTRN' ) ) );
		Thread::where( 'conversation_id', $draft->id )->update( array( 'state' => Thread::STATE_DRAFT ) );

		foreach ( array( Conversation::STATUS_CLOSED, Conversation::STATUS_SPAM ) as $status ) {
			$this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Approved.' ) ), $status );
		}

		$deleted        = $this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Approved.' ) ) );
		$deleted->state = Conversation::STATE_DELETED;
		$deleted->save();

		$this->assertSections( array(), $this->sections() );
	}

	/**
	 * The conversations waiting the longest come first, open or pending.
	 *
	 * @return void
	 */
	public function test_sections_list_the_longest_waiting_first(): void {
		$reply   = array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) );
		$pending = $this->conversation( $reply, Conversation::STATUS_PENDING, '2026-08-03 10:00:00' );
		$newer   = $this->conversation( $reply, Conversation::STATUS_ACTIVE, '2026-08-05 10:00:00' );
		$older   = $this->conversation( $reply, Conversation::STATUS_ACTIVE, '2026-08-01 10:00:00' );

		$this->assertSections( array( 'participating' => array( $older, $pending, $newer ) ), $this->sections() );
	}

	/**
	 * The sections are someone else's when they're chosen.
	 *
	 * @return void
	 */
	public function test_sections_are_for_the_chosen_reviewer(): void {
		$mine   = $this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) ) );
		$theirs = $this->conversation(
			array(
				array( Thread::TYPE_MESSAGE, $this->colleague, 'Review sent.' ),
				array( Thread::TYPE_NOTE, $this->reviewer, $this->mention( $this->reviewer ) ),
			)
		);

		$this->assertSections(
			array( 'participating' => array( $theirs ) ),
			MyReviews::sections( $this->mailbox, $this->colleague, $this->reviewer )
		);
		$this->assertSections(
			array(
				'participating' => array( $mine ),
				'mentioned'     => array( $theirs ),
			),
			$this->sections()
		);
	}

	/**
	 * With the Teams module, only the Plugin Reviews team's conversations are in the sections, and none if Teams has
	 * no such team; without it, the mailbox's open conversations are, whoever they're assigned to.
	 *
	 * @return void
	 */
	public function test_sections_are_the_plugin_reviews_teams(): void {
		$reply      = array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) );
		$teams      = $this->conversation( $reply, Conversation::STATUS_ACTIVE, '2026-08-02 10:00:00' );
		$colleagues = $this->conversation( $reply, Conversation::STATUS_ACTIVE, '2026-08-01 10:00:00', $this->colleague );
		$this->conversation( $reply, Conversation::STATUS_ACTIVE, '2026-08-03 10:00:00', $this->team, $this->create_mailbox( 'Themes', 'themes@wordpress.test' ) );

		$this->answer_teams( array( (int) $this->team->id => 'Plugin Reviews' ) );
		$this->assertSections( array( 'participating' => array( $teams ) ), $this->sections() );

		$this->answer_teams( array( (int) $this->team->id => 'Plugin Security' ) );
		$this->assertSections( array(), $this->sections() );

		\App\Module::clearModulesCache();
		\App\Module::setActive( Reviewers::TEAMS_MODULE, false );
		\App\Module::clearModulesCache();
		$this->assertSections( array( 'participating' => array( $colleagues, $teams ) ), $this->sections() );
	}

	/**
	 * Agents who may only see the conversations assigned to them only see those here too.
	 *
	 * @return void
	 */
	public function test_sections_respect_only_assigned_conversations(): void {
		$viewer              = $this->create_user( User::ROLE_USER );
		$viewer->permissions = array( User::PERM_ONLY_ASSIGNED_TICKETS => true );
		$viewer->save();

		$reply = array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ), array( Thread::TYPE_MESSAGE, $viewer, 'Review sent.' ) );
		$this->conversation( $reply );
		$assigned = $this->conversation( $reply, Conversation::STATUS_ACTIVE, '2026-08-01 10:00:00', $viewer );

		$this->assertSections( array( 'participating' => array( $assigned ) ), MyReviews::sections( $this->mailbox, $this->reviewer, $viewer ) );
		$this->assertSame( 1, MyReviews::participating_count( $this->mailbox, $viewer ) );
	}

	/**
	 * Reviewers get the folder in the plugins team's mailbox, after Unassigned and before Mine, with how many reviews
	 * they're participating in, linked to its page; nobody gets it in other mailboxes.
	 *
	 * @return void
	 */
	public function test_folder_is_added_after_unassigned(): void {
		$this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) ) );
		$unassigned = $this->folder( Folder::TYPE_UNASSIGNED );
		$mine       = $this->folder( Folder::TYPE_MINE );
		$closed     = $this->folder( Folder::TYPE_CLOSED );

		$this->actingAs( $this->reviewer );
		$folders = \Eventy::filter( 'mailbox.folders', collect( array( $unassigned, $mine, $closed ) ), $this->mailbox );

		$this->assertSame( array( Folder::TYPE_UNASSIGNED, MyReviews::FOLDER_TYPE, Folder::TYPE_MINE, Folder::TYPE_CLOSED ), $this->types( $folders ) );

		$folder = $folders->get( 1 );
		$this->assertSame( 'My Reviews', $folder->getTypeName() );
		$this->assertSame( 'tasks', $folder->getTypeIcon() );
		$this->assertSame( 1, (int) $folder->getCount( $folders ) );
		$this->assertSame( route( 'wporgpluginreview.my_reviews', array( 'mailbox_id' => $this->mailbox->id ) ), $folder->url( $this->mailbox->id ) );

		// Without Unassigned, it's before Mine; without either, first.
		$this->assertSame( array( MyReviews::FOLDER_TYPE, Folder::TYPE_MINE, Folder::TYPE_CLOSED ), $this->types( \Eventy::filter( 'mailbox.folders', collect( array( $mine, $closed ) ), $this->mailbox ) ) );
		$this->assertSame( array( MyReviews::FOLDER_TYPE, Folder::TYPE_CLOSED ), $this->types( \Eventy::filter( 'mailbox.folders', collect( array( $closed ) ), $this->mailbox ) ) );

		$themes = $this->create_mailbox( 'Themes', 'themes@wordpress.test' );
		$this->assertSame( array( Folder::TYPE_CLOSED ), $this->types( \Eventy::filter( 'mailbox.folders', collect( array( $closed ) ), $themes ) ) );
	}

	/**
	 * The folder's counts are every reviewer's, kept until a reply, or a change to a conversation, may change them.
	 *
	 * @return void
	 */
	public function test_folder_counts_are_kept_until_they_change(): void {
		$reply = array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) );
		$first = $this->conversation( $reply );
		$this->conversation( array( array( Thread::TYPE_MESSAGE, $this->colleague, 'Review sent.' ) ) );

		$this->assertSame( 1, MyReviews::participating_count( $this->mailbox, $this->reviewer ) );
		$this->assertSame( 1, MyReviews::participating_count( $this->mailbox, $this->colleague ) );
		$this->assertSame( 0, MyReviews::participating_count( $this->mailbox, $this->team ) );

		// A reply written without the hooks isn't counted until the counts are forgotten.
		$second = $this->conversation( array() );
		Thread::query()->insert(
			array(
				'conversation_id'    => $second->id,
				'type'               => Thread::TYPE_MESSAGE,
				'state'              => Thread::STATE_PUBLISHED,
				'source_via'         => Thread::PERSON_USER,
				'source_type'        => Thread::SOURCE_TYPE_WEB,
				'created_by_user_id' => $this->reviewer->id,
				'body'               => 'Review sent.',
			)
		);
		$this->assertSame( 1, MyReviews::participating_count( $this->mailbox, $this->reviewer ) );

		MyReviews::forget_counts( (int) $this->mailbox->id );
		$this->assertSame( 2, MyReviews::participating_count( $this->mailbox, $this->reviewer ) );

		// An agent's reply forgets them.
		$this->conversation( $reply );
		$this->assertSame( 3, MyReviews::participating_count( $this->mailbox, $this->reviewer ) );

		// So does closing a conversation.
		$first->changeStatus( Conversation::STATUS_CLOSED, $this->colleague );
		$this->assertSame( 2, MyReviews::participating_count( $this->mailbox, $this->reviewer ) );
	}

	/**
	 * Members of other teams don't get the folder, or its page.
	 *
	 * @return void
	 */
	public function test_folder_is_only_for_reviewers(): void {
		$this->answer_teams( array(), array( (int) $this->reviewer->id => array( 'Themes' ) ) );

		$this->actingAs( $this->reviewer );
		$this->assertCount( 0, \Eventy::filter( 'mailbox.folders', collect(), $this->mailbox ) );

		$this->get( $this->url() )->assertStatus( 403 );
	}

	/**
	 * The page shows the agent's own reviews by default, with the mailbox's folders.
	 *
	 * @return void
	 */
	public function test_page_shows_the_agents_reviews(): void {
		$mine = $this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) ), Conversation::STATUS_ACTIVE, '2026-08-01 10:00:00', null, null, 'Review of <My Plugin>' );
		$this->conversation( array( array( Thread::TYPE_MESSAGE, $this->colleague, 'Review sent.' ) ), Conversation::STATUS_ACTIVE, '2026-08-01 10:00:00', null, null, 'Review of Their Plugin' );

		$response = $this->actingAs( $this->reviewer )->get( $this->url() );
		$response->assertStatus( 200 );
		$html = $response->getContent();

		$this->assertStringContainsString( 'Reviews I’m participating in', $html );
		$this->assertStringContainsString( 'Review of &lt;My Plugin&gt;', $html );
		$this->assertStringContainsString( $mine->url(), $html );
		$this->assertStringNotContainsString( 'Review of Their Plugin', $html );
		$this->assertStringContainsString( 'data-folder_id="' . MyReviews::FOLDER_ID . '"', $html );
		$this->assertStringContainsString( '<title>(1) My Reviews - Plugins', $html );
		$this->assertStringContainsString( '>Threads</th>', $html );
		$this->assertStringNotContainsString( '>Number</th>', $html );
	}

	/**
	 * Reviews someone is participating in can be selected, for FreeScout's bulk actions; the others can't.
	 *
	 * @return void
	 */
	public function test_page_lets_participating_reviews_be_changed_together(): void {
		$mine      = $this->conversation( array( array( Thread::TYPE_MESSAGE, $this->reviewer, 'Review sent.' ) ) );
		$mentioned = $this->conversation( array( array( Thread::TYPE_NOTE, $this->colleague, $this->mention( $this->reviewer ) ) ) );

		$html = $this->actingAs( $this->reviewer )->get( $this->url() )->getContent();

		$this->assertStringContainsString( 'id="conversations-bulk-actions"', $html );
		$this->assertStringContainsString( 'id="cb-' . $mine->id . '"', $html );
		$this->assertStringNotContainsString( 'id="cb-' . $mentioned->id . '"', $html );
		$this->assertSame( 1, substr_count( $html, 'class="toggle-all' ) );
	}

	/**
	 * Choosing another reviewer shows theirs; someone who isn't a reviewer can't be chosen.
	 *
	 * @return void
	 */
	public function test_page_shows_the_chosen_reviewers_reviews(): void {
		$this->conversation( array( array( Thread::TYPE_MESSAGE, $this->colleague, 'Review sent.' ) ), Conversation::STATUS_ACTIVE, '2026-08-01 10:00:00', null, null, 'Review of Their Plugin' );
		$outsider = $this->create_user();
		$this->answer_teams( array( (int) $this->team->id => 'Plugin Reviews' ), array( (int) $outsider->id => array( 'Themes' ) ) );

		$response = $this->actingAs( $this->reviewer )->get( $this->url() . '?user=' . $this->colleague->id );
		$response->assertStatus( 200 );
		$html = $response->getContent();

		$this->assertStringContainsString( e( 'Reviews ' . $this->colleague->getFullName() . ' is participating in' ), $html );
		$this->assertStringContainsString( 'Review of Their Plugin', $html );

		$html = $this->actingAs( $this->reviewer )->get( $this->url() . '?user=' . $outsider->id )->getContent();

		$this->assertStringContainsString( 'Reviews I’m participating in', $html );
		$this->assertStringNotContainsString( 'Review of Their Plugin', $html );
	}

	/**
	 * Other mailboxes have no My Reviews page.
	 *
	 * @return void
	 */
	public function test_page_is_only_for_the_plugins_mailbox(): void {
		$themes = $this->create_mailbox( 'Themes', 'themes@wordpress.test' );

		$this->actingAs( $this->reviewer )
			->get( route( 'wporgpluginreview.my_reviews', array( 'mailbox_id' => $themes->id ) ) )
			->assertStatus( 404 );
	}

	/**
	 * Creates an open conversation in the plugins team's mailbox, assigned to the team, with a sender's first message.
	 *
	 * @param array        $threads    Type, author, and body of each thread after the sender's.
	 * @param int          $status     Conversation status.
	 * @param string       $replied_at The latest reply's time.
	 * @param User|null    $assignee   Assignee; the team if null.
	 * @param Mailbox|null $mailbox    Mailbox; the plugins team's if null.
	 * @param string       $subject    Subject.
	 * @return Conversation
	 */
	private function conversation( array $threads, int $status = Conversation::STATUS_ACTIVE, string $replied_at = '2026-08-01 10:00:00', ?User $assignee = null, ?Mailbox $mailbox = null, string $subject = 'Review in Progress' ): Conversation {
		$conversation = $this->create_conversation( $mailbox ?? $this->mailbox, $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, '<p>My plugin.</p>', null, '2026-07-30 10:00:00' );

		foreach ( $threads as list( $type, $user, $body ) ) {
			$this->create_thread( $conversation, $type, $body, $user, '2026-07-31 10:00:00' );
		}

		// Set after the threads, which core's observer would update them from.
		$conversation->status        = $status;
		$conversation->user_id       = ( $assignee ?? $this->team )->id;
		$conversation->subject       = $subject;
		$conversation->last_reply_at = $replied_at;
		$conversation->save();

		return $conversation;
	}

	/**
	 * A folder of a type, as the mailbox's folder list has them.
	 *
	 * @param int $type Folder type.
	 * @return Folder
	 */
	private function folder( int $type ): Folder {
		$folder       = new Folder();
		$folder->type = $type;

		return $folder;
	}

	/**
	 * The types of folders, in order.
	 *
	 * @param mixed $folders Folders.
	 * @return int[]
	 */
	private function types( mixed $folders ): array {
		return array_map(
			static function ( Folder $folder ): int {
				return (int) $folder->type;
			},
			array_values( $folders->all() )
		);
	}

	/**
	 * How the Mentions module mentions a user in a note.
	 *
	 * @param User $user User.
	 * @return string
	 */
	private function mention( User $user ): string {
		return '<span><b data-mentioned-id="' . $user->id . '">@' . e( $user->first_name ) . '</b>&nbsp;</span>';
	}

	/**
	 * The reviewer's own sections.
	 *
	 * @return array
	 */
	private function sections(): array {
		return MyReviews::sections( $this->mailbox, $this->reviewer, $this->reviewer );
	}

	/**
	 * The My Reviews page of the plugins team's mailbox.
	 *
	 * @return string
	 */
	private function url(): string {
		return route( 'wporgpluginreview.my_reviews', array( 'mailbox_id' => $this->mailbox->id ) );
	}

	/**
	 * Asserts which conversations each section has, in order.
	 *
	 * @param array $expected Conversations by section; sections left out are empty.
	 * @param array $sections Sections, from MyReviews::sections().
	 * @return void
	 */
	private function assertSections( array $expected, array $sections ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Like PHPUnit's own assertions.
		$ids = static function ( array $conversations ): array {
			return array_map(
				static function ( Conversation $conversation ): int {
					return (int) $conversation->id;
				},
				$conversations
			);
		};

		$this->assertSame( MyReviews::SECTIONS, array_keys( $sections ) );
		foreach ( MyReviews::SECTIONS as $section ) {
			$this->assertSame( $ids( $expected[ $section ] ?? array() ), $ids( $sections[ $section ] ), $section );
		}
	}

	/**
	 * Switches the Teams module on, and answers for it.
	 *
	 * @param array $teams   The teams' names, by ID.
	 * @param array $members The names of users' teams, by user ID; others are members of Plugin Reviews.
	 * @return void
	 */
	private function answer_teams( array $teams, array $members = array() ): void {
		\App\Module::clearModulesCache();
		\App\Module::setActive( Reviewers::TEAMS_MODULE, true );
		\App\Module::clearModulesCache();

		$this->app->instance(
			Reviewers::class,
			new class( $teams, $members ) extends Reviewers {
				/**
				 * The teams' names, by ID.
				 *
				 * @var array
				 */
				private $teams;

				/**
				 * The names of users' teams, by user ID.
				 *
				 * @var array
				 */
				private $members;

				/**
				 * Constructor.
				 *
				 * @param array $teams   The teams' names, by ID.
				 * @param array $members The names of users' teams, by user ID.
				 */
				public function __construct( array $teams, array $members ) {
					$this->teams   = $teams;
					$this->members = $members;
				}

				/**
				 * The names of the teams a user is a member of.
				 *
				 * @param User $user User.
				 * @return string[]
				 */
				protected function team_names( User $user ): array {
					return $this->members[ (int) $user->id ] ?? array( Reviewers::REVIEW_TEAM );
				}

				/**
				 * The teams' names, by ID.
				 *
				 * @return string[]
				 */
				protected function teams(): array {
					return $this->teams;
				}
			}
		);
	}
}
