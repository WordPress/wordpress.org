<?php
/**
 * Tests for posting Trac commits to Slack.
 *
 * @package WordPressdotorg\Slack\Trac
 */

declare( strict_types = 1 );

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/slack/autoload.php';

use Dotorg\Slack\Send;
use Dotorg\Slack\Trac\Commit;
use Dotorg\Slack\Trac\Commit_Handler;
use Dotorg\Slack\Trac\Trac;

/**
 * Tests for Dotorg\Slack\Trac\Commit_Handler.
 */
class Test_Slack_Trac_Commit_Handler extends \PHPUnit\Framework\TestCase {

	/**
	 * A Core commit message with a summary line, a description, props and a ticket reference.
	 *
	 * @var string
	 */
	const LOG = "Media: Add a missing alt attribute.\n\nThe image in the media modal had no alt text.\n\nProps desrosj.\nFixes #12345.";

	/**
	 * Runs a commit through Commit_Handler without svnlook or the Slack webhook.
	 *
	 * @param string $trac          Trac slug.
	 * @param string $changed_files Changed file paths, one per line.
	 * @param string $log           Commit message.
	 * @return string[] Attachment text sent to each channel, keyed by channel.
	 */
	private function run_handler( string $trac, string $changed_files, string $log ): array {
		$sent = new ArrayObject();

		$send = new class( 'https://example.com/webhook', $sent ) extends Send {
			/**
			 * Attachment text sent to each channel, keyed by channel.
			 *
			 * @var ArrayObject
			 */
			private $sent;

			/**
			 * Records sends instead of posting to the webhook.
			 *
			 * @param string      $webhook Webhook URL.
			 * @param ArrayObject $sent    Where to record what is sent.
			 */
			public function __construct( $webhook, ArrayObject $sent ) {
				parent::__construct( $webhook );
				$this->sent = $sent;
			}

			/**
			 * Records the attachment text instead of sending it.
			 *
			 * @param string      $channel Channel name.
			 * @param string|bool $thread  Thread timestamp, or false.
			 */
			public function send( $channel, $thread = false ) {
				$attachments            = $this->get_attachments();
				$this->sent[ $channel ] = $attachments[0]['text'];
			}
		};

		$handler = new class( $send, $trac, '/repo', 1 ) extends Commit_Handler {
			/**
			 * Output for each svnlook subcommand.
			 *
			 * @var string[]
			 */
			public $svnlook_output = array();

			/**
			 * Returns canned output instead of running svnlook.
			 *
			 * @param string $subcommand svnlook subcommand.
			 * @return string
			 */
			protected function svnlook( $subcommand ) {
				return $this->svnlook_output[ $subcommand ];
			}
		};

		$handler->svnlook_output = array(
			'changed' => $changed_files,
			'log'     => $log,
			'author'  => 'desrosj',
			'date'    => '1762732800',
		);
		$handler->run();

		return $sent->getArrayCopy();
	}

	/**
	 * #core gets the commit summary; every other channel gets the full message.
	 */
	public function test_core_commit_summary_only_goes_to_core(): void {
		$sent = $this->run_handler( 'core', 'trunk/src/wp-includes/media.php', self::LOG );

		$this->assertSame(
			array( '#core', '#core-commits', '#core-firehose', '#core-media-commits', '#core-media-firehose' ),
			$this->sorted_keys( $sent )
		);

		$this->assertSame( 'Media: Add a missing alt attribute.', $sent['#core'] );

		foreach ( array( '#core-commits', '#core-firehose', '#core-media-commits', '#core-media-firehose' ) as $channel ) {
			$this->assertStringContainsString( 'Props desrosj.', $sent[ $channel ], "$channel should get the full commit message." );
		}
	}

	/**
	 * Meta posts the full commit message everywhere, including its primary channel.
	 */
	public function test_meta_commit_is_unchanged(): void {
		$sent = $this->run_handler(
			'meta',
			'sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/class-plugin-directory.php',
			"Plugin Directory: Fix a thing.\n\nProps desrosj."
		);

		$this->assertNotEmpty( $sent );
		foreach ( $sent as $channel => $text ) {
			$this->assertStringContainsString( 'Props desrosj.', $text, "$channel should get the full commit message." );
		}
	}

	/**
	 * Only Core's primary channel uses the summary format.
	 */
	public function test_get_commit_format(): void {
		$core = Trac::get( 'core' );

		$this->assertSame( 'summary', $core->get_commit_format( '#core' ) );
		$this->assertSame( 'full', $core->get_commit_format( '#core-commits' ) );
		$this->assertSame( 'full', $core->get_commit_format( '#core-media-commits' ) );
		$this->assertSame( 'full', Trac::get( 'meta' )->get_commit_format( '#meta' ) );
	}

	/**
	 * The summary is the first non-empty line of the commit message.
	 */
	public function test_get_summary(): void {
		$this->assertSame( 'Media: Add a missing alt attribute.', Commit::get_summary( self::LOG ) );
		$this->assertSame( 'Docs: Fix a typo.', Commit::get_summary( "\n  Docs: Fix a typo.  \n\nProps someone." ) );
		$this->assertSame( 'One line.', Commit::get_summary( 'One line.' ) );
		$this->assertSame( '', Commit::get_summary( '' ) );
	}

	/**
	 * Every -newtickets channel gets the same format as #core-newtickets: the full ticket.
	 * Only #core gets the title alone.
	 */
	public function test_newtickets_channels_match_core_newtickets_format(): void {
		$core = Trac::get( 'core' );
		$map  = ( new ReflectionProperty( $core, 'component_channels' ) )->getValue( $core );

		$this->assertSame( 'title', $core->get_ticket_format( '#core' ) );
		$this->assertSame( 'description', $core->get_ticket_format( '#core-newtickets' ) );

		foreach ( array_unique( array_filter( $map ) ) as $channel ) {
			$this->assertSame(
				$core->get_ticket_format( '#core-newtickets' ),
				$core->get_ticket_format( $channel . '-newtickets' ),
				"$channel-newtickets should match #core-newtickets."
			);
		}
	}

	/**
	 * Returns an array's keys, sorted.
	 *
	 * @param array $items Array.
	 * @return string[]
	 */
	private function sorted_keys( array $items ): array {
		$keys = array_keys( $items );
		sort( $keys );
		return $keys;
	}
}
