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
	 * @var int $timed_reminder_post_id The ID of an Organizer Reminder post which is configured to be sent at a specific time.
	 */
	protected static $timed_reminder_post_id;

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
		/*
		 * Reminders must be created _before_ WordCamps, to avoid triggering the early return in
		 * `timed_email_is_ready_to_send()`. To test that early return, you can modify the
		 * `post_date` inside that specific test function.
		 */
		self::$triggered_reminder_post_id = $factory->post->create(
			array(
				'post_type'    => WCOR_Reminder::AUTOMATED_POST_TYPE_SLUG,
				'post_title'   => '[wordcamp_name] has been added to the final schedule',
				'post_content' => "Huzzah! A new WordCamp is coming soon to [wordcamp_location]! The lead organizer is [lead_organizer_username], and the venue is at:\n\n[venue_address]",
			)
		);

		update_post_meta( self::$triggered_reminder_post_id, 'wcor_send_when',  'wcor_send_trigger'    );
		update_post_meta( self::$triggered_reminder_post_id, 'wcor_send_where', 'wcor_send_organizers' );

		self::$timed_reminder_post_id = $factory->post->create(
			array(
				'post_type'    => WCOR_Reminder::AUTOMATED_POST_TYPE_SLUG,
				'post_title'   => "It's time to submit [wordcamp_name] reimbursement requests",
				'post_content' => "Howdy [budget_wrangler_name], now's the perfect time to request reimbursement for any out of pocket expenses. You can do that at [wordcamp_url]/wp-admin/edit.php?post_type=wcb_reimbursement.",
			)
		);

		update_post_meta( self::$timed_reminder_post_id, 'wcor_send_where', 'wcor_send_budget_wrangler' );

		self::$wordcamp_dayton_post_id = $factory->post->create(
			array(
				'post_type'  => WCPT_POST_TYPE_ID,
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

	/**
	 * Test that timed messages are sent.
	 *
	 * @dataProvider data_timed_messages_sent
	 *
	 * @param string $send_when        The type of schedule when the email is sent (e.g., before the camp).
	 * @param string $send_when_period Which period of time the message is scheduled for (e.g., days before the camp).
	 * @param int    $send_when_days   The number of days before/after the period when the message is scheduled for.
	 * @param string $compare_date     The date that the scheduled message is compared do, in order to determine if
	 *                                 it's ready to be sent (e.g., the start date of the camp when sending before
	 *                                 the camp starts).
	 *
	 * @covers WCOR_Mailer::send_timed_emails
	 */
	public function test_timed_messages_sent( $send_when, $send_when_period, $send_when_days, $compare_date ) {
		/** @var WCOR_Mailer $WCOR_Mailer */
		global $WCOR_Mailer;

		update_post_meta( self::$timed_reminder_post_id, 'wcor_send_when',  $send_when      );
		update_post_meta( self::$timed_reminder_post_id, $send_when_period, $send_when_days );

		if ( in_array( $send_when, array( 'wcor_send_before', 'wcor_send_after' ) ) ) {
			update_post_meta( self::$wordcamp_dayton_post_id, 'Start Date (YYYY-mm-dd)', $compare_date );
		} elseif ( 'wcor_send_after_pending' === $send_when ) {
			update_post_meta( self::$wordcamp_dayton_post_id, '_timestamp_added_to_planning_schedule', $compare_date );
		}

		$wordcamp = get_post( self::$wordcamp_dayton_post_id );

		$this->assertSame( '', $wordcamp->wcor_sent_email_ids );

		do_action( 'wcor_send_timed_emails' );

		$this->assert_mail_succeeded(
			'sally.smith+trez@gmail.com',
			"It's time to submit WordCamp Dayton reimbursement requests",
			"Howdy Sally Smith, now's the perfect time to request reimbursement for any out of pocket expenses. You can do that at https://2019.dayton.wordcamp.org/wp-admin/edit.php?post_type=wcb_reimbursement.\n"
		);

		$this->assertInternalType( 'array', $wordcamp->wcor_sent_email_ids );
		$this->assertContains( self::$timed_reminder_post_id, $wordcamp->wcor_sent_email_ids );
	}

	/**
	 * Provide test cases for test_timed_messages_sent().
	 *
	 * @return array See `test_timed_messages_sent()` for parameter documentation.
	 */
	public function data_timed_messages_sent() {
		return array(
			// Before the camp starts
			array(
				'wcor_send_before',
				'wcor_send_days_before',
				3,
				strtotime( 'now + 3 days' ),
			),

			// After the camp ends
			array(
				'wcor_send_after',
				'wcor_send_days_after',
				3,
				strtotime( 'now - 3 days' )
			),

			// After added to the pending schedule
			array(
				'wcor_send_after_pending',
				'wcor_send_days_after_pending',
				3,
				strtotime( 'now - 3 days' )
			),
		);
	}
}
