<?php
/**
 * Tests for the import page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Jobs\ImportPage;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers who can use the page, starting, pausing, resuming, and importing changes.
 */
final class ImportControllerTest extends ImportTestCase {

	/**
	 * Administrator using the page.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Fakes the queue, has HelpScout list its mailboxes and users, and logs an administrator in.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();
		$this->helpscout->on(
			'GET',
			'v2/mailboxes',
			array(
				'_embedded' => array(
					'mailboxes' => array(
						array(
							'id'    => 77,
							'name'  => 'Photos',
							'email' => 'photos@wordpress.org',
						),
					),
				),
				'page'      => array( 'totalPages' => 1 ),
			)
		);
		$this->helpscout->on(
			'GET',
			'v2/users',
			array(
				'_embedded' => array(
					'users' => array(
						array(
							'id'        => 55,
							'firstName' => 'Ada',
							'lastName'  => 'Agent',
							'email'     => 'agent@example.org',
						),
						array(
							'id'        => 56,
							'firstName' => 'Bo',
							'lastName'  => 'Gone',
							'email'     => 'bo@example.org',
						),
					),
				),
				'page'      => array( 'totalPages' => 1 ),
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
		$this->assertSame( 0, Run::query()->count() );
	}

	/**
	 * The page lists HelpScout's mailboxes, and which agents have no FreeScout user.
	 *
	 * @return void
	 */
	public function test_page_lists_mailboxes_and_missing_agents(): void {
		$response = $this->get( route( 'wporghelpscoutimport.index', array( 'agents' => 77 ) ) );

		$response->assertStatus( 200 );
		$this->assertStringContainsString( 'photos@wordpress.org', $response->getContent() );
		$this->assertStringContainsString( 'Bo Gone &lt;bo@example.org&gt;', $response->getContent() );
		$this->assertStringNotContainsString( 'Ada Agent &lt;agent@example.org&gt;', $response->getContent() );
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
	 * A finished run can be followed by its changes, from a little before it started.
	 *
	 * @return void
	 */
	public function test_changes_follow_a_finished_run(): void {
		$this->post( route( 'wporghelpscoutimport.start' ), $this->start_form() );
		$run = Run::query()->firstOrFail();

		$this->post( route( 'wporghelpscoutimport.changes', array( 'id' => $run->id ) ) )->assertSessionHas( 'flash_error' );

		$run->status     = Run::STATUS_DONE;
		$run->started_at = Carbon::parse( '2026-09-20 12:00:00' );
		$run->save();

		$this->post( route( 'wporghelpscoutimport.changes', array( 'id' => $run->id ) ) );

		$changes = Run::query()->orderByDesc( 'id' )->firstOrFail();
		$this->assertNotSame( (int) $run->id, (int) $changes->id );
		$this->assertSame( '2026-09-20 11:45:00', $changes->since->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( (int) $run->mailbox_id, (int) $changes->mailbox_id );
		$this->assertSame( 77, (int) $changes->helpscout_mailbox_id );
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
		);
	}
}
