<?php
/**
 * Tests for parsing Trac notification emails into Slack messages.
 *
 * @package WordPressdotorg\Slack\Trac
 */

declare( strict_types = 1 );

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/slack/autoload.php';

/**
 * Tests for Dotorg\Slack\Trac\Comment_Handler.
 */
class Test_Slack_Trac_Comment_Handler extends \PHPUnit\Framework\TestCase {

	/**
	 * Builds a Trac 1.6 notification email, which has no blank line before the footer.
	 *
	 * @param string[]      $body  Lines between the ticket properties table and the footer.
	 * @param string[]|null $table Ticket properties table, including its borders.
	 * @return string[] Email lines.
	 */
	private function get_email( array $body, ?array $table = null ): array {
		return array_merge(
			array(
				'X-Trac-Ticket-URL: https://meta.trac.wordpress.org/ticket/8420#comment:1',
				'Subject: Re: [WordPress.org Meta] #8420: Trac\'s slack integration is gutting messages short.',
				'',
				'#8420: Trac\'s slack integration is gutting messages short.',
			),
			$table ?? array(
				'-----------------------------+---------------------',
				' Reporter:  peterwilsoncc    |       Owner:  (none)',
				'     Type:  defect (bug)     |      Status:  new',
				'Component:  Trac             |  Resolution:',
				'-----------------------------+---------------------',
			),
			$body,
			array(
				'-- ',
				'Ticket URL: <https://meta.trac.wordpress.org/ticket/8420#comment:1>',
				'WordPress.org Meta <https://meta.trac.wordpress.org/>',
				'WordPress.org Site',
			)
		);
	}

	/**
	 * The last line before the footer is kept.
	 *
	 * @dataProvider data_last_line_is_kept
	 *
	 * @param string[] $body    Email body lines.
	 * @param string   $comment Expected comment.
	 * @param string[] $changes Expected changes.
	 */
	public function test_last_line_is_kept( array $body, string $comment, array $changes ): void {
		$handler = new \Dotorg\Slack\Trac\Comment_Handler( new \Dotorg\Slack\Send( '' ), $this->get_email( $body ) );
		$handler->process_message();

		$this->assertSame( $comment, $handler->comment );
		$this->assertSame( $changes, $handler->changes );
	}

	/**
	 * The ticket's component is read from either column of the properties table.
	 */
	public function test_component_is_read(): void {
		$body = array(
			'',
			'Comment (by desrosj):',
			'',
			' Does anyone have any idea why Nacin would make this change?',
		);

		$handler = new \Dotorg\Slack\Trac\Comment_Handler( new \Dotorg\Slack\Send( '' ), $this->get_email( $body ) );
		$handler->process_message();
		$this->assertSame( 'Trac', $handler->component );

		$email = str_replace(
			array( 'Component:  Trac             |  Resolution:', '     Type:  defect (bug)     |      Status:  new' ),
			array( '     Type:  defect (bug)     |  Resolution:', ' Priority:  normal          |   Component:  Build/Test Tools' ),
			$this->get_email( $body )
		);

		$handler = new \Dotorg\Slack\Trac\Comment_Handler( new \Dotorg\Slack\Send( '' ), $email );
		$handler->process_message();
		$this->assertSame( 'Build/Test Tools', $handler->component );
	}

	/**
	 * The component is read from the properties table as Trac formats it.
	 *
	 * @dataProvider data_component_is_read_from_table
	 *
	 * @param string[] $table     Ticket properties table, including its borders.
	 * @param string   $component Expected component.
	 */
	public function test_component_is_read_from_table( array $table, string $component ): void {
		$body = array(
			'',
			'Comment (by desrosj):',
			'',
			' A comment.',
		);

		$handler = new \Dotorg\Slack\Trac\Comment_Handler( new \Dotorg\Slack\Send( '' ), $this->get_email( $body, $table ) );
		$handler->process_message();
		$this->assertSame( $component, $handler->component );
	}

	/**
	 * Data provider for test_component_is_read_from_table().
	 *
	 * The tables are laid out the way Trac 1.6 formats them.
	 *
	 * @return array[]
	 */
	public function data_component_is_read_from_table(): array {
		return array(
			'wrapped onto the next line'      => array(
				array(
					'-------------------------+-------------------------------------------------',
					' Reporter:  someone      |       Owner:  (none)',
					'     Type:  defect       |      Status:  new',
					'  (bug)                  |',
					' Priority:  normal       |   Milestone:  Awaiting Review',
					'Component:  Login and    |     Version:',
					'  Registration           |',
					' Severity:  normal       |  Resolution:',
					' Keywords:  has-patch    |     Focuses:  accessibility, administration,',
					'                         |  javascript, css',
					'-------------------------+-------------------------------------------------',
				),
				'Login and Registration',
			),
			'wrapped after a hyphen'          => array(
				array(
					'-------------------------+-------------------------------------------------',
					' Reporter:  someone      |       Owner:  (none)',
					'Component:  XML-         |     Version:',
					'  RPC                    |',
					' Keywords:  has-patch    |     Focuses:  accessibility, administration,',
					'                         |  javascript, css',
					'-------------------------+-------------------------------------------------',
				),
				'XML-RPC',
			),
			'keywords that look like a field' => array(
				array(
					'-------------------------------------+------------------------------',
					' Reporter:  someone                  |       Owner:  (none)',
					'     Type:  defect (bug)             |      Status:  new',
					' Priority:  normal                   |   Milestone:  Awaiting Review',
					'Component:  General                  |     Version:',
					' Severity:  normal                   |  Resolution:',
					' Keywords:  x | Component: Security  |     Focuses:',
					'-------------------------------------+------------------------------',
				),
				'General',
			),
			'wide characters in another cell' => array(
				array(
					'-----------------------------+---------------------',
					'     Type:  defect (bug)     |      Status:  new',
					' Reporter:  日本語           |   Component:  Trac',
					'-----------------------------+---------------------',
				),
				'Trac',
			),
		);
	}

	/**
	 * Data provider for test_last_line_is_kept().
	 *
	 * @return array[]
	 */
	public function data_last_line_is_kept(): array {
		return array(
			'comment'      => array(
				array(
					'',
					'Comment (by obenland):',
					'',
					' I started such a thread.',
					'',
					' So it was closed.',
				),
				"I started such a thread.\n\nSo it was closed.",
				array(),
			),
			'changes only' => array(
				array(
					'Changes (by obenland):',
					'',
					' * owner:  (none) => obenland',
					' * status:  new => assigned',
				),
				'',
				array(
					'_*owner:*_ (none) => obenland',
					'_*status:*_ new => assigned',
				),
			),
		);
	}
}
