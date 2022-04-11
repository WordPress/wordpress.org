<?php

namespace Dotorg\Slack\Props\Tests;
use PHPUnit\Framework\TestCase;
use function Dotorg\Slack\Props\{ run };

/**
 * @group slack
 * @group props
 */
class Test_Props extends TestCase {
	public static function setUpBeforeClass() : void {
		require_once dirname( __DIR__ ) . '/lib.php';
	}

	/**
	 * @covers ::run
	 * @dataProvider data_run
	 * @group unit
	 */
	public function test_run( array $request, $expected ) : void {
		$actual = run( $request, true );

		$this->assertSame( $expected, $actual );
	}

	public function data_run() : array {
		$valid_request = array(
			'user_name' => 'iandunn',
			'text'      => 'kimparsell Thanks for being so welcoming at WordCamp Columbus',
			'command'   => '/props',
			'user_id'   => 'U02QCF502',
			'team_id'   => '1',
		);

		// Can't call show_error() here since data providers run before `setUp()`.
		$usage   = "Please use `/props SLACK_USERNAME MESSAGE` to give props.\n";
		$success = "Your props to @kimparsell have been sent.\n";

		$cases = array(
			'wrong command' => array(
				'request' => array_merge(
					$valid_request,
					array(
						'command' => '/here',
					)
				),

				'expected' => "???\n",
			),

			'empty text' => array(
				'request' => array_merge(
					$valid_request,
					array(
						'text' => '',
					)
				),

				'expected' => $usage,
			),

			'username but no message, or vice versa' => array(
				'request' => array_merge(
					$valid_request,
					array(
						'text' => 'kimparsell',
					)
				),

				'expected' => $usage,
			),

			'username with @' => array(
				'request' => array_merge(
					$valid_request,
					array(
						'text' => '@' . $valid_request['text'],
					)
				),
				'expected' => $success,
			),

			'username without @' => array(
				'request'  => $valid_request,
				'expected' => $success,
			),
		);

		return $cases;
	}
}
