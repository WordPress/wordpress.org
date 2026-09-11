<?php
/**
 * Tests for the notifications box the Trac ticket page fetches from make.wordpress.org.
 *
 * @package trac-notifications
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the note shown to contributors when a ticket comes from someone new.
 */
class WPorg_Trac_Notifications_Test extends WPorg_Trac_Components_TestCase {

	/**
	 * The login of the WordPress.org account that reported the ticket.
	 *
	 * @var string
	 */
	const REPORTER = 'firsttimer';

	/**
	 * The ticket being viewed.
	 *
	 * @var int
	 */
	const TICKET = 50;

	/**
	 * The plugin instance under test, set up as it runs on make.wordpress.org/core.
	 *
	 * @var wporg_trac_notifications
	 */
	protected wporg_trac_notifications $plugin;

	/**
	 * Creates the reporter's account and a plugin instance pointed at Core Trac.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->factory->user->create(
			array(
				'user_login' => self::REPORTER,
				'user_email' => self::REPORTER . '@example.org',
			)
		);

		$class        = new ReflectionClass( wporg_trac_notifications::class );
		$this->plugin = $class->newInstanceWithoutConstructor();
		$class->getProperty( 'trac' )->setValue( $this->plugin, 'core' );
	}

	/**
	 * Renders the note for a ticket as another contributor views it.
	 *
	 * @param string $reporter     The ticket's reporter column, as Trac stores it.
	 * @param int    $ticket_count How many tickets the reporter has, this one included.
	 * @param bool   $comments     Whether the reporter has commented on other tickets.
	 * @return string The rendered note, or an empty string when none is shown.
	 */
	protected function render_note( string $reporter, int $ticket_count = 1, bool $comments = false ): string {
		$tickets = array();
		for ( $i = 1; $i <= $ticket_count; $i++ ) {
			$tickets[] = array(
				'id'         => self::TICKET - $ticket_count + $i,
				'summary'    => 'Ticket ' . $i,
				'type'       => 'defect',
				'status'     => 'new',
				'resolution' => '',
			);
		}

		$ticket = array(
			'id'       => self::TICKET,
			'reporter' => $reporter,
		);
		$meta   = array(
			'get_reporter_last_activity' => array(
				'tickets'  => $tickets,
				'comments' => $comments,
			),
		);

		ob_start();
		$this->plugin->ticket_notes( $ticket, 'viewer', $meta );
		return ob_get_clean();
	}

	/**
	 * Reporter strings that carry markup around a real login.
	 *
	 * `get_user_by()` strips tags from the value it looks up, so each of these
	 * resolves to the reporter's account even though the raw string differs.
	 *
	 * @return array<string, array{string}>
	 */
	public static function marked_up_reporters(): array {
		return array(
			'trailing break'  => array( self::REPORTER . '<br />' ),
			'trailing image'  => array( self::REPORTER . '<img src="x.png">' ),
			'event handler'   => array( self::REPORTER . '<img src=x onerror="doStuff()">' ),
			'wrapped in span' => array( '<span>' . self::REPORTER . '</span>' ),
		);
	}

	/**
	 * Reporter strings that do not resolve to any account.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unresolvable_reporters(): array {
		return array(
			'unknown login'         => array( 'nobody-here' ),
			'text inside the tags'  => array( self::REPORTER . '<b>SECOND</b>' ),
			'quote after the login' => array( self::REPORTER . '"' ),
		);
	}

	/**
	 * A first ticket gets the welcome note with the reporter's avatar and login.
	 */
	public function test_first_ticket_note_welcomes_the_reporter(): void {
		$note = $this->render_note( self::REPORTER );

		$this->assertStringContainsString( '<p class="ticket-note note-new-reporter">', $note );
		$this->assertStringContainsString( "class='avatar", $note, 'The note carries the reporter\'s avatar.' );
		$this->assertStringContainsString( '<strong>Make sure firsttimer receives a warm welcome.</strong><br />It&#8217;s their first ticket!', $note );
	}

	/**
	 * The welcome note says so when the reporter has commented before.
	 */
	public function test_first_ticket_note_mentions_earlier_comments(): void {
		$note = $this->render_note( self::REPORTER, 1, true );

		$this->assertStringContainsString( 'They&#8217;ve commented before, but it&#8217;s their first ticket!', $note );
	}

	/**
	 * A second to fourth ticket lists the reporter's earlier tickets, not the current one.
	 */
	public function test_repeat_ticket_note_links_the_previous_tickets(): void {
		$note = $this->render_note( self::REPORTER, 3 );

		$this->assertStringContainsString( '<strong>This is only firsttimer&#8217;s third ticket!</strong><br />Previously:', $note );
		$this->assertStringContainsString( 'href="https://core.trac.wordpress.org/ticket/48"', $note );
		$this->assertStringContainsString( 'href="https://core.trac.wordpress.org/ticket/49"', $note );
		$this->assertStringNotContainsString( 'ticket/50"', $note, 'The ticket being viewed is not listed as a previous one.' );
	}

	/**
	 * The welcome note names the account the reporter string resolved to.
	 *
	 * @param string $reporter The ticket's reporter column.
	 */
	#[DataProvider( 'marked_up_reporters' )]
	public function test_first_ticket_note_prints_the_login_the_reporter_resolved_to( string $reporter ): void {
		$note = $this->render_note( $reporter );

		$this->assertStringContainsString( '<strong>Make sure firsttimer receives a warm welcome.</strong>', $note );
		$this->assert_note_holds_only_the_login( $note );
	}

	/**
	 * The repeat-ticket note names the account the reporter string resolved to.
	 *
	 * @param string $reporter The ticket's reporter column.
	 */
	#[DataProvider( 'marked_up_reporters' )]
	public function test_repeat_ticket_note_prints_the_login_the_reporter_resolved_to( string $reporter ): void {
		$note = $this->render_note( $reporter, 2 );

		$this->assertStringContainsString( '<strong>This is only firsttimer&#8217;s second ticket!</strong>', $note );
		$this->assert_note_holds_only_the_login( $note );
	}

	/**
	 * No note is shown when the reporter does not resolve to an account.
	 *
	 * @param string $reporter The ticket's reporter column.
	 */
	#[DataProvider( 'unresolvable_reporters' )]
	public function test_no_note_when_the_reporter_is_not_an_account( string $reporter ): void {
		$this->assertSame( '', $this->render_note( $reporter ) );
		$this->assertSame( '', $this->render_note( $reporter, 2 ) );
	}

	/**
	 * Reporters do not see a note about themselves.
	 */
	public function test_no_note_for_the_reporter_viewing_their_own_ticket(): void {
		$ticket = array(
			'id'       => self::TICKET,
			'reporter' => self::REPORTER,
		);

		ob_start();
		$this->plugin->ticket_notes( $ticket, self::REPORTER, array() );

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The note stops once the reporter has five tickets.
	 */
	public function test_no_note_once_the_reporter_has_five_tickets(): void {
		$this->assertSame( '', $this->render_note( self::REPORTER, 5 ) );
	}

	/**
	 * No note is shown when the reporter has no recorded tickets.
	 */
	public function test_no_note_when_the_reporter_has_no_tickets(): void {
		$this->assertSame( '', $this->render_note( self::REPORTER, 0 ) );
	}

	/**
	 * Asserts the note's text names the reporter's login and nothing the reporter string added.
	 *
	 * @param string $note The rendered note.
	 */
	protected function assert_note_holds_only_the_login( string $note ): void {
		preg_match( '#<span class="note">(.*?)</span>#s', $note, $matches );

		$this->assertNotEmpty( $matches, 'The note text is present.' );
		$this->assertSame( 1, preg_match_all( '#<br\s*/?>#i', $matches[1] ), 'The template supplies the only line break in the note.' );
		$this->assertStringNotContainsString( '<img', $matches[1] );
		$this->assertStringNotContainsString( '<span>', $matches[1] );
		// The note names the resolved login, not the reporter string it was handed:
		// an escaped copy of the raw string would leave &lt;/&gt; entities behind.
		$this->assertStringNotContainsString( '&lt;', $matches[1] );
		$this->assertStringNotContainsString( '&gt;', $matches[1] );
		$this->assertSame( 1, substr_count( $note, '<img' ), 'The avatar is the only image in the note.' );
	}
}
