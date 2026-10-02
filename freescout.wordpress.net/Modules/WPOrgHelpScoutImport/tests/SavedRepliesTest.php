<?php
/**
 * Tests for importing saved replies.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Attachment;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\ImportedSavedReply;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Jobs\ImportPage;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Services\SavedReplies;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;
use Modules\WPOrgHelpScoutImport\Tests\Support\PaidModules;

require_once __DIR__ . '/ImportTestCase.php';
require_once __DIR__ . '/Support/PaidModules.php';

/**
 * Covers SavedReplies, through the job that runs it once a mailbox's list is done.
 */
final class SavedRepliesTest extends ImportTestCase {

	/**
	 * Run under test.
	 *
	 * @var Run
	 */
	private $run;

	/**
	 * Creates the paid modules' tables before the test's transaction starts.
	 *
	 * @return void
	 */
	protected function refreshApplication(): void {
		parent::refreshApplication();

		PaidModules::create();
	}

	/**
	 * Switches the Saved Replies module on, and has HelpScout list no conversations, so the run's list is done.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();
		PaidModules::switch( SavedReplies::MODULE, true );

		// After DatabaseTransactions' rollback, which was registered first.
		$this->beforeApplicationDestroyed( array( PaidModules::class, 'drop' ) );

		$this->helpscout->only(
			'GET',
			'v2/conversations',
			array(
				'_embedded' => array( 'conversations' => array() ),
				'page'      => array(
					'totalPages'    => 0,
					'totalElements' => 0,
				),
			)
		);
		$this->answer_replies(
			array(
				self::reply( 301, 'Welcome', '<p>Hi {%customer.firstName,fallback=there%},</p>' ),
				self::reply( 302, 'Closing', '<p>Thanks!</p>' ),
			)
		);

		$this->run = $this->create_run();
	}

	/**
	 * Leaves the module list as other tests expect it.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\App\Module::clearModulesCache();

		parent::tearDown();
	}

	/**
	 * Once the list is done, the mailbox's saved replies are copied, credited to the robot, and counted.
	 *
	 * @return void
	 */
	public function test_saved_replies_are_imported_once_the_list_is_done(): void {
		$this->handle( $this->run );

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_DONE, $run->status );
		$this->assertSame( array( SavedReplies::IMPORTED => 2 ), $run->saved_replies );

		$replies = \DB::table( 'saved_replies' )->where( 'mailbox_id', $this->mailbox->id )->orderBy( 'sort_order' )->get();
		$this->assertSame( array( 'Welcome', 'Closing' ), $replies->pluck( 'name' )->all() );
		// HelpScout's placeholders are FreeScout's too.
		$this->assertSame( '<p>Hi {%customer.firstName,fallback=there%},</p>', $replies[0]->text );
		$this->assertSame( (int) ( new People() )->robot()->id, (int) $replies[0]->user_id );
		$this->assertLessThan( (int) $replies[1]->sort_order, (int) $replies[0]->sort_order );
	}

	/**
	 * Importing again brings saved replies up to date, unless they were changed or deleted in FreeScout.
	 *
	 * @return void
	 */
	public function test_import_again_updates_only_what_freescout_left_alone(): void {
		$this->answer_replies(
			array(
				self::reply( 301, 'Welcome', '<p>Hi!</p>' ),
				self::reply( 302, 'Closing', '<p>Thanks!</p>' ),
				self::reply( 303, 'Refunds', '<p>No refunds.</p>' ),
			)
		);
		$this->handle( $this->run );

		$this->answer_replies(
			array(
				self::reply( 301, 'Welcome', '<p>Hello!</p>' ),
				self::reply( 302, 'Closing', '<p>Thanks a lot!</p>' ),
				self::reply( 303, 'Refunds', '<p>No refunds.</p>' ),
			)
		);
		$closing = ImportedSavedReply::query()->where( 'helpscout_id', 302 )->firstOrFail();
		\DB::table( 'saved_replies' )->where( 'id', $closing->saved_reply_id )->update( array( 'text' => '<p>Thanks, from FreeScout.</p>' ) );

		$again = $this->create_run();
		$this->handle( $again );

		$this->assertSame(
			array(
				SavedReplies::UPDATED   => 1,
				SavedReplies::KEPT      => 1,
				SavedReplies::UNCHANGED => 1,
			),
			$again->fresh()->saved_replies
		);
		$this->assertSame( '<p>Hello!</p>', $this->text_of( 301 ) );
		$this->assertSame( '<p>Thanks, from FreeScout.</p>', $this->text_of( 302 ) );
		$this->assertSame( 3, \DB::table( 'saved_replies' )->where( 'mailbox_id', $this->mailbox->id )->count() );
	}

	/**
	 * A saved reply FreeScout has one by the same name of already is left to FreeScout's.
	 *
	 * @return void
	 */
	public function test_saved_reply_with_a_name_freescout_has_is_kept(): void {
		\DB::table( 'saved_replies' )->insert(
			array(
				'mailbox_id' => $this->mailbox->id,
				'name'       => 'Closing',
				'text'       => '<p>Ours.</p>',
				'user_id'    => $this->agent->id,
			)
		);

		$this->handle( $this->run );

		$this->assertSame(
			array(
				SavedReplies::IMPORTED => 1,
				SavedReplies::KEPT     => 1,
			),
			$this->run->fresh()->saved_replies
		);
		$this->assertSame( array( '<p>Ours.</p>' ), \DB::table( 'saved_replies' )->where( 'name', 'Closing' )->pluck( 'text' )->all() );

		// It stays FreeScout's when imported again, even once HelpScout's changed.
		$this->answer_replies(
			array(
				self::reply( 301, 'Welcome', '<p>Hi {%customer.firstName,fallback=there%},</p>' ),
				self::reply( 302, 'Closing', '<p>Thanks again!</p>' ),
			)
		);
		$again = $this->create_run();
		$this->handle( $again );

		$this->assertSame(
			array(
				SavedReplies::UNCHANGED => 1,
				SavedReplies::KEPT      => 1,
			),
			$again->fresh()->saved_replies
		);
		$this->assertSame( array( '<p>Ours.</p>' ), \DB::table( 'saved_replies' )->where( 'name', 'Closing' )->pluck( 'text' )->all() );
	}

	/**
	 * Images HelpScout hosts are copied, like images pasted into the editor.
	 *
	 * @return void
	 */
	public function test_images_helpscout_hosts_are_copied(): void {
		$src = 'https://d33v4339jhl8k0.cloudfront.net/inline/83653/abc/def/logo.png';
		$this->answer_replies( array( self::reply( 301, 'Welcome', '<p><img src="' . $src . '"></p>' ) ) );
		$this->helpscout->on( 'GET', 'inline/83653/abc/def/logo.png', new Response( 200, array(), (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg==' ) ) );

		$this->handle( $this->run );

		$image = Attachment::query()->where( 'file_name', 'logo.png' )->firstOrFail();
		$this->assertNull( $image->thread_id );
		$this->assertTrue( (bool) $image->embedded );
		$this->assertSame( '<p><img src="' . $image->url() . '"></p>', $this->text_of( 301 ) );
	}

	/**
	 * A saved reply HelpScout won't give is counted as failed; the run is done regardless.
	 *
	 * @return void
	 */
	public function test_failed_saved_reply_does_not_fail_the_run(): void {
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/302', FakeHelpScout::json( array(), 403 ) );

		$this->handle( $this->run );

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_DONE, $run->status );
		$this->assertSame(
			array(
				SavedReplies::IMPORTED => 1,
				SavedReplies::FAILED   => array( 302 ),
			),
			$run->saved_replies
		);
		$this->assertStringStartsWith( 'HelpScout saved reply 302: ', (string) $run->last_error );
	}

	/**
	 * A failed saved reply isn't tried, or counted, again when the run goes on after waiting for the rate limit.
	 *
	 * @return void
	 */
	public function test_failed_saved_reply_is_counted_once(): void {
		$this->answer_replies(
			array(
				self::reply( 301, 'Welcome', '<p>Hi!</p>' ),
				self::reply( 302, 'Closing', '<p>Thanks!</p>' ),
				self::reply( 303, 'Refunds', '<p>No refunds.</p>' ),
			)
		);
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/302', FakeHelpScout::json( array(), 403 ) );
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/303', FakeHelpScout::json( array(), 429, array( 'X-RateLimit-Retry-After' => '30' ) ) );

		$this->handle( $this->run );
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/303', self::reply( 303, 'Refunds', '<p>No refunds.</p>' ) );
		$this->handle( $this->run );

		$this->assertSame(
			array(
				SavedReplies::IMPORTED => 2,
				SavedReplies::FAILED   => array( 302 ),
			),
			$this->run->fresh()->saved_replies
		);
		$this->assertCount( 1, $this->helpscout->requests_to( 'v2/mailboxes/77/saved-replies/302' ) );
	}

	/**
	 * Saved replies whose names are the same once cut to length, or after one is renamed, get a number.
	 *
	 * @return void
	 */
	public function test_names_the_same_as_other_imports_get_a_number(): void {
		$long = str_repeat( 'a', 75 );
		$this->answer_replies(
			array(
				self::reply( 301, $long . ' one', '<p>One</p>' ),
				self::reply( 302, $long . ' two', '<p>Two</p>' ),
				self::reply( 303, 'Closing', '<p>Thanks!</p>' ),
			)
		);

		$this->handle( $this->run );

		$this->assertSame( array( SavedReplies::IMPORTED => 3 ), $this->run->fresh()->saved_replies );
		$this->assertSame( $long, $this->name_of( 301 ) );
		$this->assertSame( str_repeat( 'a', 71 ) . ' (2)', $this->name_of( 302 ) );
		$this->assertSame( '<p>Two</p>', $this->text_of( 302 ) );

		// Renamed in HelpScout to a name another one has.
		$this->answer_replies(
			array(
				self::reply( 301, $long . ' one', '<p>One</p>' ),
				self::reply( 302, $long . ' two', '<p>Two</p>' ),
				self::reply( 303, $long, '<p>Thanks!</p>' ),
			)
		);
		$again = $this->create_run();
		$this->handle( $again );

		$this->assertSame( str_repeat( 'a', 71 ) . ' (3)', $this->name_of( 303 ) );
		$this->assertSame( 3, \DB::table( 'saved_replies' )->where( 'mailbox_id', $this->mailbox->id )->distinct()->count( 'name' ) );
	}

	/**
	 * Hitting the rate limit part way waits, and goes on with the saved replies not read yet.
	 *
	 * @return void
	 */
	public function test_rate_limit_goes_on_where_it_stopped(): void {
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/302', FakeHelpScout::json( array(), 429, array( 'X-RateLimit-Retry-After' => '30' ) ) );

		$this->handle( $this->run );

		$this->assertSame( Run::STATUS_RUNNING, $this->run->fresh()->status );
		$this->assertSame( array( SavedReplies::IMPORTED => 1 ), $this->run->fresh()->saved_replies );
		Queue::assertPushed( ImportPage::class, 1 );

		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/302', self::reply( 302, 'Closing', '<p>Thanks!</p>' ) );
		$this->handle( $this->run );

		$this->assertSame( Run::STATUS_DONE, $this->run->fresh()->status );
		$this->assertSame( array( SavedReplies::IMPORTED => 2 ), $this->run->fresh()->saved_replies );
		$this->assertCount( 1, $this->helpscout->requests_to( 'v2/mailboxes/77/saved-replies/301' ) );
	}

	/**
	 * Without the Saved Replies module, HelpScout's aren't read.
	 *
	 * @return void
	 */
	public function test_nothing_is_read_without_the_module(): void {
		PaidModules::switch( SavedReplies::MODULE, false );

		$this->handle( $this->run );

		$this->assertSame( Run::STATUS_DONE, $this->run->fresh()->status );
		$this->assertNull( $this->run->fresh()->saved_replies );
		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/mailboxes/77/saved-replies' ) );
	}

	/**
	 * Creates a running run of the Photos mailbox.
	 *
	 * @return Run
	 */
	private function create_run(): Run {
		$run                         = new Run();
		$run->helpscout_mailbox_id   = 77;
		$run->helpscout_mailbox_name = 'Photos';
		$run->mailbox_id             = $this->mailbox->id;
		$run->status                 = Run::STATUS_RUNNING;
		$run->started_at             = Carbon::now();
		$run->renew_token();
		$run->save();

		return $run;
	}

	/**
	 * Runs a run's current job.
	 *
	 * @param Run $run Run.
	 * @return void
	 */
	private function handle( Run $run ): void {
		( new ImportPage( (int) $run->id, (string) $run->fresh()->token ) )->handle();
	}

	/**
	 * Has HelpScout list saved replies, and give each.
	 *
	 * @param array[] $replies Saved replies, with their text.
	 * @return void
	 */
	private function answer_replies( array $replies ): void {
		$listed = array_map(
			static function ( array $reply ): array {
				return array(
					'id'      => $reply['id'],
					'name'    => $reply['name'],
					'preview' => mb_substr( strip_tags( $reply['text'] ), 0, 20 ),
				);
			},
			$replies
		);
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies', FakeHelpScout::json( $listed ) );

		foreach ( $replies as $reply ) {
			$this->helpscout->only( 'GET', 'v2/mailboxes/77/saved-replies/' . $reply['id'], $reply );
		}
	}

	/**
	 * A HelpScout saved reply.
	 *
	 * @param int    $id   ID.
	 * @param string $name Name.
	 * @param string $text HTML.
	 * @return array
	 */
	private static function reply( int $id, string $name, string $text ): array {
		return array(
			'id'       => $id,
			'name'     => $name,
			'text'     => $text,
			'chatText' => strip_tags( $text ),
		);
	}

	/**
	 * The name of the FreeScout saved reply a HelpScout one was imported into.
	 *
	 * @param int $helpscout_id HelpScout saved reply ID.
	 * @return string
	 */
	private function name_of( int $helpscout_id ): string {
		$imported = ImportedSavedReply::query()->where( 'helpscout_id', $helpscout_id )->firstOrFail();

		return (string) \DB::table( 'saved_replies' )->where( 'id', $imported->saved_reply_id )->value( 'name' );
	}

	/**
	 * The text of the FreeScout saved reply a HelpScout one was imported into.
	 *
	 * @param int $helpscout_id HelpScout saved reply ID.
	 * @return string
	 */
	private function text_of( int $helpscout_id ): string {
		$imported = ImportedSavedReply::query()->where( 'helpscout_id', $helpscout_id )->firstOrFail();

		return (string) \DB::table( 'saved_replies' )->where( 'id', $imported->saved_reply_id )->value( 'text' );
	}
}
