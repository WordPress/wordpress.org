<?php
/**
 * Tests for comparing Akismet's verdicts with what agents marked.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Tests;

use App\Conversation;
use App\Mailbox;
use Modules\WPOrgAkismet\Providers\WPOrgAkismetServiceProvider;
use Modules\WPOrgAkismet\Services\Akismet;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which conversations are counted, and where.
 */
final class ReportCommandTest extends TestCase {

	/**
	 * Mailbox the conversations are in.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Registers the module, and creates a mailbox.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgAkismetServiceProvider::class );
		$this->mailbox = $this->create_mailbox( 'Themes' );
	}

	/**
	 * Each checked conversation is counted by Akismet's verdict and whether it's spam now.
	 *
	 * @return void
	 */
	public function test_counts_verdicts_against_current_status(): void {
		$this->checked( Akismet::SPAM, true, 1 );
		$this->checked( Akismet::SPAM, false, 2 );
		$this->checked( Akismet::HAM, true, 3 );
		$this->checked( Akismet::HAM, false, 4 );
		$this->checked( Akismet::HAM, false, 5 );

		// Too old, and never checked.
		$this->checked( Akismet::SPAM, true, 20 );
		$this->create_conversation( $this->mailbox, $this->create_sender() );

		$this->assertSame(
			array(
				'Spam'     => array( 1, 1 ),
				'Not spam' => array( 1, 2 ),
			),
			$this->report( 14 )
		);
	}

	/**
	 * Creates a conversation with a verdict.
	 *
	 * @param string $verdict  Akismet's verdict.
	 * @param bool   $is_spam  Whether it's marked as spam now.
	 * @param int    $days_ago How long ago it came in.
	 * @return void
	 */
	private function checked( string $verdict, bool $is_spam, int $days_ago ): void {
		$conversation = $this->create_conversation( $this->mailbox, $this->create_sender() );
		$conversation->setMeta( WPOrgAkismetServiceProvider::META, array( 'verdict' => $verdict ) );
		$conversation->status = $is_spam ? Conversation::STATUS_SPAM : Conversation::STATUS_ACTIVE;
		$conversation->save();

		Conversation::where( 'id', $conversation->id )->update( array( 'created_at' => now()->subDays( $days_ago ) ) );
	}

	/**
	 * Runs the command, and reads its table.
	 *
	 * @param int $days Days to look at.
	 * @return array Rows by verdict: counts now marked spam, and now not spam.
	 */
	private function report( int $days ): array {
		\Artisan::call( 'wporgakismet:report', array( '--days' => $days ) );

		preg_match_all( '/^\|\s*(Spam|Not spam)\s*\|\s*(\d+)\s*\|\s*(\d+)\s*\|$/m', \Artisan::output(), $rows, PREG_SET_ORDER );

		$table = array();
		foreach ( $rows as $row ) {
			$table[ $row[1] ] = array( (int) $row[2], (int) $row[3] );
		}

		return $table;
	}
}
