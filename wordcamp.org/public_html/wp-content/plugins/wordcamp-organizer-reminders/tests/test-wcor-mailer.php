<?php

namespace WordCamp\Organizer_Reminders\Tests;
use WP_UnitTestCase, WP_UnitTest_Factory;
use WCOR_Reminder, WCOR_Mailer;

defined( 'WPINC' ) || die();

/**
 * Class Test_WCOR_Mailer
 *
 * These are intentionally closer to integration tests than unit tests.
 *
 * @group wordcamp-organizer-reminders
 */
class Test_WCOR_Mailer extends WP_UnitTestCase {
	/**
	 * @var int $triggered_reminder_post_id The ID of an Organizer Reminder post which is configured to be sent on a trigger.
	 */
	protected static $triggered_reminder_post_id;

	/**
	 * @var int $wordcamp_dayton_post_id The ID of a WordCamp post for Dayton, Ohio, USA.
	 */
	protected static $wordcamp_dayton_post_id;

	/**
	 * Set up the mocked PHPMailer instance before each test method.
	 */
	public function setUp() {
		parent::setUp();
		reset_phpmailer_instance();
	}

	/**
	 * Create fixtures that are shared by multiple test cases.
	 *
	 * @param WP_UnitTest_Factory $factory The base factory object.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$triggered_reminder_post_id = $factory->post->create(
			array(
				'post_type'    => WCOR_Reminder::AUTOMATED_POST_TYPE_SLUG,
				'post_title'   => '[wordcamp_name] has been added to the final schedule',
				'post_content' => "Huzzah! A new WordCamp is coming soon to [wordcamp_location]! The lead organizer is [lead_organizer_username], and the venue is at:\n\n[venue_address]",
			)
		);

		update_post_meta( self::$triggered_reminder_post_id, 'wcor_send_when',  'wcor_send_trigger'    );
		update_post_meta( self::$triggered_reminder_post_id, 'wcor_send_where', 'wcor_send_organizers' );

		self::$wordcamp_dayton_post_id = $factory->post->create(
			array(
				'post_type'  => 'wordcamp',
				'post_title' => 'WordCamp Dayton',
			)
		);

		update_post_meta( self::$wordcamp_dayton_post_id, 'Location',                       'Dayton, Ohio, USA'                      );
		update_post_meta( self::$wordcamp_dayton_post_id, 'URL',                            'https://2019.dayton.wordcamp.org'       );
		update_post_meta( self::$wordcamp_dayton_post_id, 'E-mail Address',                 'dayton@wordcamp.org'                    );
		update_post_meta( self::$wordcamp_dayton_post_id, 'WordPress.org Username',         'janedoe'                                );
		update_post_meta( self::$wordcamp_dayton_post_id, 'Physical Address',               '3640 Colonel Glenn Hwy, Dayton, OH, US' );
		update_post_meta( self::$wordcamp_dayton_post_id, 'Budget Wrangler Name',           'Sally Smith'                            );
		update_post_meta( self::$wordcamp_dayton_post_id, 'Budget Wrangler E-mail Address', 'sally.smith+trez@gmail.com'             );
	}

	/**
	 * Reset the mocked PHPMailer instance after each test method.
	 */
	public function tearDown() {
		reset_phpmailer_instance();
		parent::tearDown();
	}

	/**
	 * Assert that an email was successfully sent.
	 *
	 * @param string $to      The expected recipient of the message.
	 * @param string $subject The expected subject of the message.
	 * @param string $body    The expected body of the message.
	 * @param bool   $result  The returned value from `wp_mail()`, if available. It defaults to `true` because it
	 *                        isn't always accessible to the testing function.
	 */
	protected function assert_mail_succeeded( $to, $subject, $body, $result = true ) {
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( true, $result );
		$this->assertSame( 0, did_action( 'wp_mail_failed' ) );

		$this->assertSame( $to,      $mailer->get_recipient( 'to' )->address );
		$this->assertSame( $subject, $mailer->get_sent()->subject );
		$this->assertSame( $body,    $mailer->get_sent()->body );
	}

	/**
	 * Test that triggered reminders are sent.
	 *
	 * @covers WCOR_Mailer::send_trigger_added_to_schedule
	 */
	public function test_triggered_message_sent() {
		/** @var WCOR_Mailer $WCOR_Mailer */
		global $WCOR_Mailer;

		update_post_meta( self::$triggered_reminder_post_id, 'wcor_which_trigger', 'wcor_added_to_schedule' );

		$wordcamp = get_post( self::$wordcamp_dayton_post_id );

		$this->assertSame( '', $wordcamp->wcor_sent_email_ids );

		do_action( 'wcpt_added_to_final_schedule', $wordcamp );

		$this->assert_mail_succeeded(
			'dayton@wordcamp.org',
			'WordCamp Dayton has been added to the final schedule',
			"Huzzah! A new WordCamp is coming soon to Dayton, Ohio, USA! The lead organizer is janedoe, and the venue is at:\n\n3640 Colonel Glenn Hwy, Dayton, OH, US\n"
		);

		$this->assertInternalType( 'array', $wordcamp->wcor_sent_email_ids );
		$this->assertContains( self::$triggered_reminder_post_id, $wordcamp->wcor_sent_email_ids );
	}
}
