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
	 * @param string[] $body Lines between the ticket properties table and the footer.
	 * @return string[] Email lines.
	 */
	private function get_email( array $body ): array {
		return array_merge(
			array(
				'X-Trac-Ticket-URL: https://meta.trac.wordpress.org/ticket/8420#comment:1',
				'Subject: Re: [WordPress.org Meta] #8420: Trac\'s slack integration is gutting messages short.',
				'',
				'#8420: Trac\'s slack integration is gutting messages short.',
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
	 * Data provider for test_last_line_is_kept().
	 *
	 * @return array[]
	 */
	public function data_last_line_is_kept(): array {
		return array(
			'comment'                => array(
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
			'changes only'           => array(
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
			'wrapped attachment'     => array(
				array(
					'Changes (by adamsilverstein):',
					'',
					' * Attachment "Monosnap Site Health - Info ‹ My... Website — WordPress',
					' 2024-07-15 15-14-25.jpg" added.',
				),
				'',
				array(
					'_*attachment:*_ `Monosnap Site Health - Info ‹ My... Website — WordPress 2024-07-15 15-14-25.jpg` added',
				),
			),
			'wrapped CJK attachment' => array(
				array(
					'Changes (by obenland):',
					'',
					' * Attachment "ワードプレスのサイトヘルス情報画面のスクリーンショット二〇二',
					' 四年七月十五日撮影分.png" added.',
				),
				'',
				array(
					'_*attachment:*_ `ワードプレスのサイトヘルス情報画面のスクリーンショット二〇二四年七月十五日撮影分.png` added',
				),
			),
		);
	}
}
