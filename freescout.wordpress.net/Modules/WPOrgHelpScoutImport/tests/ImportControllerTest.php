<?php
/**
 * Tests for the import page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Folder;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Jobs\ImportPage;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers who can use the page, starting, pausing, resuming, and importing again.
 */
final class ImportControllerTest extends ImportTestCase {

	/**
	 * Administrator using the page.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Fakes the queue, has HelpScout list its mailbox and its users, Ada and Bo, and logs an administrator in.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();
		$this->answer_directory(
			array(
				self::helpscout_user( 55, 'Ada', 'Agent', 'agent@example.org' ),
				self::helpscout_user( 56, 'Bo', 'Newcomer', 'bo@example.org' ),
			)
		);

		$this->admin = $this->create_user( User::ROLE_ADMIN );
		$this->actingAs( $this->admin );
	}

	/**
	 * Only administrators get the page.
	 *
	 * @return void
	 */
	public function test_only_administrators_get_the_page(): void {
		$this->actingAs( $this->create_user( User::ROLE_USER ) );

		$this->get( route( 'wporghelpscoutimport.index' ) )->assertStatus( 403 );
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() )->assertStatus( 403 );
		$this->post( route( 'wporghelpscoutimport.cancel', array( 'id' => 1 ) ) )->assertStatus( 403 );
		$this->post( route( 'wporghelpscoutimport.retry', array( 'id' => 1 ) ) )->assertStatus( 403 );
		$this->get( route( 'wporghelpscoutimport.agents' ) )->assertStatus( 403 );
		$this->post( route( 'wporghelpscoutimport.agents.save' ), array( 'agents' => array( 56 => 1 ) ) )->assertStatus( 403 );
		$this->assertSame( 0, Run::query()->count() );
		$this->assertSame( 0, Agent::query()->count() );
	}

	/**
	 * The page lists HelpScout's mailboxes, and says users are created for HelpScout's.
	 *
	 * @return void
	 */
	public function test_page_lists_mailboxes_and_explains_users(): void {
		$response = $this->get( route( 'wporghelpscoutimport.index' ) );

		$response->assertStatus( 200 );
		$this->assertStringContainsString( 'Photos', $response->getContent() );
		$this->assertStringContainsString( 'Every HelpScout user who can see the mailbox gets a FreeScout user', $response->getContent() );
		$this->assertStringContainsString( route( 'wporghelpscoutimport.agents' ), $response->getContent() );
	}

	/**
	 * An import that creates users says who first; once confirmed, they're created, with access to the mailbox.
	 *
	 * @return void
	 */
	public function test_new_users_are_confirmed_then_created(): void {
		$form = $this->start_form();
		unset( $form['confirmed'] );

		$this->post( route( 'wporghelpscoutimport.start' ), $form )->assertSessionHas( 'wporghelpscoutimport_confirm' );
		$this->assertSame( 0, Run::query()->count() );
		$this->assertFalse( User::query()->where( 'email', 'bo@example.org' )->exists() );

		$page = $this->get( route( 'wporghelpscoutimport.index' ) )->getContent();
		$this->assertStringContainsString( 'Importing Photos creates 1 FreeScout users, with access to Photos:', $page );
		$this->assertStringContainsString( 'Bo Newcomer &lt;bo@example.org&gt;', $page );

		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() )->assertSessionHas( 'flash_success' );
		$this->assertSame( 1, Run::query()->count() );

		$bo = User::query()->where( 'email', 'bo@example.org' )->firstOrFail();
		$this->assertSame( User::STATUS_ACTIVE, (int) $bo->status );
		$this->assertSame( array( (int) $this->mailbox->id ), $bo->mailboxes()->pluck( 'mailboxes.id' )->map( 'intval' )->all() );
		$this->assertTrue( Folder::query()->where( 'user_id', $bo->id )->where( 'mailbox_id', $this->mailbox->id )->where( 'type', Folder::TYPE_MINE )->exists() );
		$this->assertSame( array( (int) $this->mailbox->id ), $this->agent->mailboxes()->pluck( 'mailboxes.id' )->map( 'intval' )->all() );
	}

	/**
	 * With every HelpScout user credited to a FreeScout user, the import starts right away.
	 *
	 * @return void
	 */
	public function test_matched_users_start_right_away(): void {
		Agent::query()->create(
			array(
				'helpscout_user_id' => 56,
				'user_id'           => $this->admin->id,
			)
		);
		$form = $this->start_form();
		unset( $form['confirmed'] );

		$this->post( route( 'wporghelpscoutimport.start' ), $form );

		$this->assertSame( 1, Run::query()->count() );
		$this->assertFalse( User::query()->where( 'email', 'bo@example.org' )->exists() );
	}

	/**
	 * Starting creates a running run and queues its first page.
	 *
	 * @return void
	 */
	public function test_start_queues_the_first_page(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() )->assertRedirect( route( 'wporghelpscoutimport.index' ) );

		$run = Run::query()->firstOrFail();
		$this->assertSame( Run::STATUS_RUNNING, $run->status );
		$this->assertSame( 'Photos', $run->helpscout_mailbox_name );
		$this->assertSame( (int) $this->admin->id, (int) $run->user_id );
		$this->assertNull( $run->since );
		Queue::assertPushed(
			ImportPage::class,
			static function ( ImportPage $job ) use ( $run ): bool {
				return (int) $run->id === $job->run_id && $run->token === $job->token;
			}
		);
	}

	/**
	 * Only HelpScout's own mailboxes can be imported, one run per FreeScout mailbox at a time.
	 *
	 * @return void
	 */
	public function test_start_refuses_unknown_mailboxes_and_a_second_run(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), array( 'helpscout_mailbox_id' => 99 ) + $this->start_form() );
		$this->assertSame( 0, Run::query()->count() );

		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() )->assertSessionHas( 'flash_error' );
		$this->assertSame( 1, Run::query()->count() );
	}

	/**
	 * Pausing stops the queued job; resuming queues a new one.
	 *
	 * @return void
	 */
	public function test_pause_and_resume(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$run   = Run::query()->firstOrFail();
		$first = $run->token;

		$this->post( route( 'wporghelpscoutimport.pause', array( 'id' => $run->id ) ) );
		$run = $run->fresh();
		$this->assertSame( Run::STATUS_PAUSED, $run->status );
		$this->assertNotSame( $first, $run->token );

		$this->post( route( 'wporghelpscoutimport.resume', array( 'id' => $run->id ) ) );
		$run = $run->fresh();
		$this->assertSame( Run::STATUS_RUNNING, $run->status );
		Queue::assertPushed(
			ImportPage::class,
			static function ( ImportPage $job ) use ( $run ): bool {
				return $run->token === $job->token;
			}
		);
	}

	/**
	 * Importing a mailbox again imports what changed since its last finished import, from a little before it started.
	 *
	 * @return void
	 */
	public function test_importing_again_imports_changes(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$first = Run::query()->firstOrFail();

		$first->status     = Run::STATUS_DONE;
		$first->started_at = Carbon::parse( '2026-09-20 12:00:00' );
		$first->save();

		$older             = $first->replicate();
		$older->started_at = Carbon::parse( '2026-09-10 12:00:00' );
		$older->save();

		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );

		$changes = Run::query()->orderByDesc( 'id' )->firstOrFail();
		$this->assertSame( Run::STATUS_RUNNING, $changes->status );
		$this->assertSame( '2026-09-20 11:45:00', $changes->since->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Only a finished import into the same mailbox counts; anything else imports everything.
	 *
	 * @return void
	 */
	public function test_unfinished_or_other_imports_dont_count(): void {
		$other = $this->create_mailbox( 'Elsewhere' );

		foreach ( array( array( Run::STATUS_FAILED, $this->mailbox->id ), array( Run::STATUS_DONE, $other->id ) ) as $previous ) {
			$run                         = new Run();
			$run->helpscout_mailbox_id   = 77;
			$run->helpscout_mailbox_name = 'Photos';
			$run->mailbox_id             = $previous[1];
			$run->status                 = $previous[0];
			$run->started_at             = Carbon::parse( '2026-09-20 12:00:00' );
			$run->save();
		}

		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );

		$this->assertNull( Run::query()->orderByDesc( 'id' )->firstOrFail()->since );
	}

	/**
	 * The page refreshes while an import runs, but not while it asks to confirm creating users.
	 *
	 * @return void
	 */
	public function test_page_refreshes_while_running_unless_confirming(): void {
		Agent::query()->create(
			array(
				'helpscout_user_id' => 56,
				'user_id'           => $this->admin->id,
			)
		);
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		Agent::query()->where( 'helpscout_user_id', 56 )->delete();

		$this->assertStringContainsString( 'data-wporghelpscoutimport-refresh="30"', $this->get( route( 'wporghelpscoutimport.index' ) )->getContent() );

		$form = array( 'mailbox_id' => $this->create_mailbox( 'Themes' )->id ) + $this->start_form();
		unset( $form['confirmed'] );
		$this->post( route( 'wporghelpscoutimport.start' ), $form );

		$this->assertStringNotContainsString( 'data-wporghelpscoutimport-refresh', $this->get( route( 'wporghelpscoutimport.index' ) )->getContent() );
	}

	/**
	 * An import doesn't start while new FreeScout conversations would take numbers HelpScout uses.
	 *
	 * @return void
	 */
	public function test_import_waits_for_the_next_number(): void {
		$this->helpscout->only( 'GET', 'v2/conversations', self::list( 'conversations', array( array( 'number' => 1126167 ) ) ) );

		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() )->assertSessionHas( 'flash_error' );
		$this->assertSame( 0, Run::query()->count() );

		\Option::set( 'next_ticket', 2000000 );
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$this->assertSame( 1, Run::query()->count() );
	}

	/**
	 * Cancelling stops a run for good, and lets its mailbox take another.
	 *
	 * @return void
	 */
	public function test_cancel_frees_the_mailbox(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$run   = Run::query()->firstOrFail();
		$token = $run->token;

		$this->post( route( 'wporghelpscoutimport.cancel', array( 'id' => $run->id ) ) );

		$run = $run->fresh();
		$this->assertSame( Run::STATUS_CANCELLED, $run->status );
		$this->assertNotSame( $token, $run->token );

		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$this->assertSame( 2, Run::query()->count() );
	}

	/**
	 * A run that hasn't saved progress for a while is shown stalled, and can be resumed.
	 *
	 * @return void
	 */
	public function test_stalled_run_can_be_resumed(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$run = Run::query()->firstOrFail();
		Run::query()->whereKey( $run->id )->update( array( 'updated_at' => Carbon::now()->subHour() ) );

		$this->assertStringContainsString( 'Stalled: no progress since', $this->get( route( 'wporghelpscoutimport.index' ) )->getContent() );

		$this->post( route( 'wporghelpscoutimport.resume', array( 'id' => $run->id ) ) );

		$this->assertNotSame( $run->token, $run->fresh()->token );
		$this->assertFalse( $run->fresh()->is_stalled() );
	}

	/**
	 * Retrying a run's failed conversations starts a run of their own, which doesn't count as the last import.
	 *
	 * @return void
	 */
	public function test_failed_conversations_are_retried(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$first             = Run::query()->firstOrFail();
		$first->status     = Run::STATUS_DONE;
		$first->started_at = Carbon::parse( '2026-09-20 12:00:00' );
		$first->failures   = array(
			1003 => 'HelpScout answered 400.',
			1004 => 'Disk full.',
		);
		$first->save();

		$this->assertStringContainsString( '1003: HelpScout answered 400.', $this->get( route( 'wporghelpscoutimport.index' ) )->getContent() );

		$this->post( route( 'wporghelpscoutimport.retry', array( 'id' => $first->id ) ) )->assertSessionHas( 'flash_success' );

		$retry = Run::query()->orderByDesc( 'id' )->firstOrFail();
		$this->assertSame( array( 1003, 1004 ), $retry->retry_ids );
		$this->assertSame( 2, (int) $retry->total );
		Queue::assertPushed(
			ImportPage::class,
			static function ( ImportPage $job ) use ( $retry ): bool {
				return (int) $retry->id === $job->run_id;
			}
		);

		$retry->status     = Run::STATUS_DONE;
		$retry->started_at = Carbon::parse( '2026-09-25 12:00:00' );
		$retry->save();
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$this->assertSame( '2026-09-20 11:45:00', Run::query()->orderByDesc( 'id' )->firstOrFail()->since->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * "Everything again" imports everything, even after a finished import.
	 *
	 * @return void
	 */
	public function test_everything_again_ignores_the_last_import(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		Run::query()->update( array( 'status' => Run::STATUS_DONE ) );

		$this->post( route( 'wporghelpscoutimport.start' ), array( 'everything' => 1 ) + $this->start_form() );

		$this->assertNull( Run::query()->orderByDesc( 'id' )->firstOrFail()->since );
	}

	/**
	 * The page warns while FreeScout shows IDs, or would give new conversations numbers HelpScout uses.
	 *
	 * @return void
	 */
	public function test_numbering_is_checked(): void {
		$this->helpscout->only( 'GET', 'v2/conversations', self::list( 'conversations', array( array( 'number' => 1126167 ) ) ) );
		config( array( 'app.custom_number' => false ) );

		$page = $this->get( route( 'wporghelpscoutimport.index' ) )->getContent();
		$this->assertStringContainsString( 'FreeScout shows its internal IDs instead of conversation numbers', $page );
		$this->assertStringContainsString( 'HelpScout’s highest is 1,126,167', $page );

		config( array( 'app.custom_number' => true ) );
		\Option::set( 'next_ticket', 2000000 );

		$page = $this->get( route( 'wporghelpscoutimport.index' ) )->getContent();
		$this->assertStringNotContainsString( 'FreeScout shows its internal IDs', $page );
		$this->assertStringNotContainsString( 'HelpScout’s highest', $page );
	}

	/**
	 * Administrators find the page under Manage.
	 *
	 * @return void
	 */
	public function test_menu_item_for_administrators(): void {
		$this->assertStringContainsString(
			route( 'wporghelpscoutimport.index' ) . '">HelpScout Import</a>',
			$this->get( route( 'wporghelpscoutimport.index' ) )->getContent()
		);

		$this->actingAs( $this->create_user( User::ROLE_USER ) );
		ob_start();
		\Eventy::action( 'menu.manage.append' );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The start form for HelpScout's Photos mailbox into the test mailbox.
	 *
	 * @return array
	 */
	private function start_form(): array {
		return array(
			'helpscout_mailbox_id' => 77,
			'mailbox_id'           => $this->mailbox->id,
			'confirmed'            => 1,
		);
	}
}
