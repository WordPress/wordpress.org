<?php

/**
 * Sends e-mails at time-based intervals and on triggers
 * @package WordCampOrganizerReminders
 */
class WCOR_Mailer {
	public $triggers;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->triggers = array(
			'wcor_added_to_schedule' => array(
				'name'     => 'Added to schedule',
				'actions'  => array(
					array(
						'name'       => 'post_updated',
						'callback'   => 'send_trigger_added_to_schedule',
						'priority'   => 10,
						'parameters' => 2,
					),
				),
			),
		);
		
		add_action( 'wcor_send_timed_emails', array( $this, 'send_timed_emails' ) );
		
		foreach ( $this->triggers as $trigger_id => $trigger ) {
			foreach( $trigger['actions'] as $action ) {
				add_action( $action['name'], array( $this, $action['callback'] ), $action['priority'], $action['parameters'] );
			}
		}
	}

	/**
	 * Schedule cron job when plugin is activated  
	 */
	public function activate() {
		if ( wp_next_scheduled( 'wcor_send_timed_emails' ) === false ) {
			wp_schedule_event(
				current_time( 'timestamp' ),
				'daily',
				'wcor_send_timed_emails'
			);
		}
	}
	
	/**
	 * Clear cron job when plugin is deactivated
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'wcor_send_timed_emails' );
	}

	/**
	 * Wrapper for wp_mail() that customizes the subject, body and headers
	 *
	 * We want to make sure that replies go to support@wordcamp.org, rather than the fake address that WordPress sends from, but 
	 * we don't want to be flagged as spam for forging the From header, so we set the Sender header.
	 * @see http://stackoverflow.com/q/4728393/450127
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $body
	 * @return bool
	 */
	protected function mail( $to, $subject, $body ) {
		$subject = html_entity_decode( strip_tags( $subject ), ENT_QUOTES, 'UTF-8' );
		$body    = html_entity_decode( strip_tags( $body ), ENT_QUOTES, 'UTF-8' );
		$headers = array(
			'From: WordCamp Central <support@wordcamp.org>',
			'Sender: wordpress@' . strtolower( $_SERVER['SERVER_NAME'] )
		);
		
		return wp_mail( $to, 'WordCamp Central Reminder: ' . $subject, $body, $headers );
	}

	/**
	 * Replaces placeholders with a dynamic string
	 *
	 * @param  WP_Post $wordcamp
	 * @param  WP_Post $email
	 * @param  string  $content
	 * @return string
	 */
	protected function replace_placeholders( $wordcamp, $email, $content ) {
		$wordcamp_meta = get_post_custom( $wordcamp->ID );
		$search        = array( '[wordcamp_name]',     '[organizer_name]',                  '[organizer_address]' );
		$replace       = array( $wordcamp->post_title, $wordcamp_meta['Organizer Name'][0], $wordcamp_meta['Mailing Address'][0] );
		
		return str_replace( $search, $replace, $content );
	}

	/**
	 * Send e-mails that are scheduled to go out at a specific time (e.g., 3 days before the camp)
	 */
	public function send_timed_emails() {
		$recent_or_upcoming_wordcamps = get_posts( array(
			'posts_per_page'  => -1,
			'post_type'       => 'wordcamp',
			'meta_query'      => array(
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'value'   => strtotime( 'now - 3 months' ),
					'compare' => '>=',
				),
			),
		) );
		
		$reminder_emails = get_posts( array(
			'posts_per_page' => -1,
			'post_type'      => WCOR_Reminder::POST_TYPE_SLUG,
			'meta_query'     => array(
				array(
					'key'     => 'wcor_send_when',
					'value'   => array( 'wcor_send_before', 'wcor_send_after' ),
					'compare' => 'IN'
				),
			),
		) );
		
		foreach ( $recent_or_upcoming_wordcamps as $wordcamp ) {
			$sent_email_ids = (array) get_post_meta( $wordcamp->ID, 'wcor_sent_email_ids', true );

			foreach ( $reminder_emails as $email ) {
				$send_where = get_post_meta( $email->ID, 'wcor_send_where', true );
				
				if ( 'wcor_send_custom' == $send_where ) {
					$recipient = get_post_meta( $email->ID, 'wcor_send_custom_address', true );
				} else {
					$recipient = get_post_meta( $wordcamp->ID, 'E-mail Address', true );
				}
				
				if ( ! is_email( $recipient ) ) {
					continue;
				}
				
				if ( $this->timed_email_is_ready_to_send( $wordcamp, $email, $sent_email_ids ) ) {
					$subject = $this->replace_placeholders( $wordcamp, $email, $email->post_title );
					$body    = $this->replace_placeholders( $wordcamp, $email, $email->post_content );
					
					if ( $this->mail( $recipient, $subject, $body ) ) {
						$sent_email_ids[] = $email->ID;
						update_post_meta( $wordcamp->ID, 'wcor_sent_email_ids', $sent_email_ids );
					}
					
					sleep( 1 ); // don't send e-mails too fast, or it might increase the risk of being flagged as spam
				}
			}
		}
	}

	/**
	 * Determines if a time-based e-mail is ready to be sent to a WordCamp
	 *
	 * E-mails should be sent if the current date matches the date that the e-mail is scheduled to be sent (e.g., 3 days before the camp starts).
	 *
	 * One exception to that is if a camp is added later than expected (e.g., we start sending e-mails 4 months before the start date, but a camp
	 * isn't scheduled until 2 months before the start). When that happens, we want to send all the e-mails that they've missed.
	 *
	 * An exception to that exception is that we don't want to send e-mails to camps that have already been sent those e-mails manually, before we
	 * started sending them automatically.
	 * @todo This exception will no longer be relevant 3 months after the date hardcoded below, because we only query for camps that started 3 months
	 * before the current date, so it can be removed at that time.
	 *
	 * @todo It'd be nice to have some unit tests for this function, since there are a lot of different cases, but it seems like that might be
	 * hard to do because of having to mock get_post_meta(), current_time(), etc. We could pass that info in, but that doesn't seem very elegant.
	 *       
	 * @param WP_Post $wordcamp
	 * @param WP_Post $email
	 * @param array   $sent_email_ids The IDs of emails that have already been sent to the $wordcamp post
	 * @return bool
	 */
	protected function timed_email_is_ready_to_send( $wordcamp, $email, $sent_email_ids ) {
		$ready      = false;
		$send_when  = get_post_meta( $email->ID, 'wcor_send_when', true );
		$start_date = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );
		$end_date   = get_post_meta( $wordcamp->ID, 'End Date (YYYY-mm-dd)', true );
		
		if ( ! $end_date ) {
			$end_date = $start_date;
		}
		
		if ( ! in_array( $email->ID, $sent_email_ids ) ) {
			if ( 'wcor_send_before' == $send_when ) {
				$days_before = absint( get_post_meta( $email->ID, 'wcor_send_days_before', true ) );
				
				if ( $days_before ) {
					$send_date = $start_date - ( $days_before * DAY_IN_SECONDS );
					
					if ( $send_date <= current_time( 'timestamp' ) ) {
						$ready = true;
					}
				}
			} elseif ( 'wcor_send_after' == $send_when ) {
				$days_after = absint( get_post_meta( $email->ID, 'wcor_send_days_after', true ) );

				if ( $days_after ) {
					$send_date = $end_date + ( $days_after * DAY_IN_SECONDS );
					
					if ( $send_date <= current_time( 'timestamp' ) ) {
						$ready = true;
					}
				}
			}

			if ( $send_date <= strtotime( 'November 3rd, 2013' ) ) {
				// Assume it was already sent manually before this plugin was activated
				$ready = false;
			}
		}
		
		return $ready;
	}

	/**
	 * Sends e-mails hooked to the wcor_added_to_schedule trigger.
	 *
	 * This fires when a WordCamp is added to the schedule (i.e., when they set the start date in their `wordcamp` post).
	 * 
	 * Since Core doesn't support revisions on post meta, we're not actually checking to see if the start date was added during
	 * the current post update, but just that it has a start data. By itself, that would lead to the e-mail being sent every time
	 * the post is updated, but to avoid that we're checking the `wcor_sent_email_id` post meta for the `wordcamp` post to see if
	 * we've already sent this particular e-mail to this WordCamp in the past.
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function send_trigger_added_to_schedule( $post_id, $post ) {
		if ( 'wordcamp' == $post->post_type && 'publish' == $post->post_status ) {
			$start_date     = get_post_meta( $post_id, 'Start Date (YYYY-mm-dd)', true );
			$sent_email_ids = (array) get_post_meta( $post_id, 'wcor_sent_email_ids', true );
		
			if ( $start_date ) {
				$emails = get_posts( array(
					'posts_per_page' => -1,
					'post_type'      => WCOR_Reminder::POST_TYPE_SLUG,
					'meta_query'     => array(
						array(
							'key'    => 'wcor_send_when',
							'value'  => 'wcor_send_trigger',
						),
						array(
							'key'    => 'wcor_which_trigger',
							'value'  => 'wcor_added_to_schedule',
						),
					),
				) );
				
				foreach( $emails as $email ) {
					$send_where = get_post_meta( $email->ID, 'wcor_send_where', true );

					if ( 'wcor_send_custom' == $send_where ) {
						$recipient = get_post_meta( $email->ID, 'wcor_send_custom_address', true );
					} else {
						$recipient = get_post_meta( $post_id, 'E-mail Address', true );
					}
					
					if ( is_email( $recipient ) && ! in_array( $email->ID, $sent_email_ids ) ) {
						$subject = $this->replace_placeholders( $post, $email, $email->post_title );
						$body    = $this->replace_placeholders( $post, $email, $email->post_content );
						
						if ( $this->mail( $recipient, $subject, $body ) ) {
							$sent_email_ids[] = $email->ID;
							update_post_meta( $post_id, 'wcor_sent_email_ids', $sent_email_ids );
						}
					}
				}
			}
		}
	}
}