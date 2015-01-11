<?php

require dirname( dirname( dirname( __DIR__ ) ) ) . '/slack/autoload.php';

class Test_Slack_Trac_Bot extends PHPUnit_Framework_TestCase {
	function test_commit_parse() {
		$post_data = $this->mock_post_data( '[1234] r12345 r897-core https://core.trac.wordpress.org/changeset/11223' );
		$receiving = new \Dotorg\Slack\Trac\Bot( $post_data );
		$expected = array(
			'core' => array(
				'commit' => array(
					array(
						'trac' => '',
						'id' => '1234',
					),
					array(
					//	'trac' => '',
						'id' => '12345',
					),
					array(
						'id' => '897',
						'trac' => 'core',
					),
					array(
						'trac' => 'core',
						'id' => '11223',
						'url' => true
					)
				)
			)
		);
		$this->assertSame( $expected, $receiving->parse() );
	}

	function test_ticket_parse() {
		$post_data = $this->mock_post_data( '#1234, #12345, https://core.trac.wordpress.org/ticket/11223' );
		$receiving = new \Dotorg\Slack\Trac\Bot( $post_data );
		$expected = array(
			'core' => array(
				'ticket' => array(
					array( 'id' => '1234' ),
					array( 'id' => '12345' ),
					array( 'trac' => 'core', 'id' => '11223', 'url' => true ),
				)
			)
		);
		$this->assertSame( $expected, $receiving->parse() );
	}

	function mock_post_data( $text ) {
		return array(
			'team_domain' => 'wordpress',
			'channel_name' => 'bots',
			'user_name' => 'nacin',
			'text' => $text,
		);
	}
}

/*
$_POST['text'] = "
	(#1234,
	(#1235-core,
	(#1236-bb,

	(#glotpress12,
	(#meta123,

	(r1234,
	(r1238-buddypress,
	(r1200-bb,

	([1234],
	([core1234],
	([wp1234],

	([1234-core],
	([1234-wp],
";*/

