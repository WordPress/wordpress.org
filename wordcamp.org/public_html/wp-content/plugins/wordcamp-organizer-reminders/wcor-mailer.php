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
				'name'     => 'WordCamp Added to schedule',
				'actions'  => array(
					array(
						'name'       => 'transition_post_status',
						'callback'   => 'send_trigger_added_to_schedule',
						'priority'   => 10,
						'parameters' => 3,
					),
				),
			),

			'wcor_organizer_added_to_central' => array(
				'name'     => 'Lead Organizer Account added to Central',
				'actions'  => array(
					array(
						'name'       => 'wcor_organizer_added_to_central',
						'callback'   => 'send_trigger_organizer_added_to_central',
						'priority'   => 10,
						'parameters' => 1,
					),
				),
			),

			'wcor_wordcamp_site_created' => array(
				'name'     => 'WordCamp website created',
				'actions'  => array(
					array(
						'name'       => 'wcor_wordcamp_site_created',
						'callback'   => 'send_trigger_wordcamp_site_created',
						'priority'   => 10,
						'parameters' => 1,
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
	 * @param array  $headers
	 * @param WP_Post $email
	 * @param WP_Post $wordcamp
	 * @return bool
	 */
	protected function mail( $to, $subject, $body, $headers = array(), $email, $wordcamp ) {
		if ( ! $this->validate_email_addresses( $to ) ) {
			return false;
		}

		$status  = true;
		$subject = $this->replace_placeholders( $wordcamp, $email, $subject );
		$body    = $this->replace_placeholders( $wordcamp, $email, $body );
		$subject = html_entity_decode( strip_tags( $subject ), ENT_QUOTES, 'UTF-8' );
		$body    = html_entity_decode( strip_tags( $body ), ENT_QUOTES, 'UTF-8' );

		$headers = array_merge( $headers, array(
			'From: WordCamp Central <support@wordcamp.org>',
			'Sender: wordpress@' . strtolower( $_SERVER['SERVER_NAME'] ),
			'CC: support@wordcamp.org',
		) );

		if ( is_array( $to ) && $this->send_individual_emails( $email->ID ) ) {
			foreach ( $to as $individual_recipient ) {
				if ( ! wp_mail( $individual_recipient, $subject, $body, $headers ) ) {
					$status = false;
				}
			}
		} else {
			$status = wp_mail( $to, $subject, $body, $headers );
		}

		return $status;
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

		$search = array(
			// The WordCamp
			'[wordcamp_name]',
			'[wordcamp_start_date]',
			'[wordcamp_location]',
			'[wordcamp_url]',
			'[edit_wordcamp_url]',
			'[wordcamp_email]',
			'[wordcamp_twitter]',
			'[wordcamp_hashtag]',
			'[wordcamp_anticipated_attendees]',
			'[multi_event_sponsor_region]',

			// The organizing team
			'[organizer_name]',
			'[lead_organizer_username]',
			'[lead_organizer_email]',
			'[lead_organizer_telephone]',
			'[organizer_address]',
			'[sponsor_wrangler_name]',
			'[sponsor_wrangler_email]',
			'[budget_wrangler_name]',
			'[budget_wrangler_email]',

			// Venue
			'[venue_name]',
			'[venue_address]',
			'[venue_max_capacity]',
			'[venue_available_rooms]',
			'[venue_url]',
			'[venue_contact_info]',
		);

		$replace = array(
			// The WordCamp
			$wordcamp->post_title,
			date( 'l, F jS, Y', $wordcamp_meta['Start Date (YYYY-mm-dd)'][0] ),
			$wordcamp_meta['Location'][0],
			esc_url( $wordcamp_meta['URL'][0] ),
			esc_url( admin_url( 'post.php?post=' . $wordcamp->ID . '&action=edit' ) ),
			$wordcamp_meta['E-mail Address'][0],
			esc_url( 'https://twitter.com/' . $wordcamp_meta['Twitter'][0] ),
			esc_url( 'https://twitter.com/hashtag/' . $wordcamp_meta['WordCamp Hashtag'][0] ),
			absint( $wordcamp_meta['Number of Anticipated Attendees'][0] ),
			get_term( $wordcamp_meta['Multi-Event Sponsor Region'][0], MES_Sponsor::REGIONS_SLUG )->name,

			// The organizing team
			$wordcamp_meta['Organizer Name'][0],
			$wordcamp_meta['WordPress.org Username'][0],
			$wordcamp_meta['Email Address'][0],
			$wordcamp_meta['Telephone'][0],
			$wordcamp_meta['Mailing Address'][0],
			$wordcamp_meta['Sponsor Wrangler Name'][0],
			$wordcamp_meta['Sponsor Wrangler E-mail Address'][0],
			$wordcamp_meta['Budget Wrangler Name'][0],
			$wordcamp_meta['Budget Wrangler E-mail Address'][0],

			// Venue
			$wordcamp_meta['Venue Name'][0],
			$wordcamp_meta['Physical Address'][0],
			$wordcamp_meta['Maximum Capacity'][0],
			$wordcamp_meta['Available Rooms'][0],
			$wordcamp_meta['Website URL'][0],
			$wordcamp_meta['Contact Information'][0],
		);
		
		return str_replace( $search, $replace, $content );
	}

	/**
	 * Retrieve the e-mail address that a Reminder should be sent to.
	 *
	 * @param int $wordcamp_id
	 * @param int $email_id
	 * @return string
	 */
	protected function get_recipient( $wordcamp_id, $email_id ) {
		$send_where = get_post_meta( $email_id, 'wcor_send_where', true );

		if ( 'wcor_send_custom' == $send_where ) {
			$recipient = get_post_meta( $email_id, 'wcor_send_custom_address', true );
		} elseif ( 'wcor_send_mes' == $send_where ) {
			/** @var $multi_event_sponsors Multi_Event_Sponsors */
			global $multi_event_sponsors;

			$recipient = $multi_event_sponsors->get_sponsor_emails( $multi_event_sponsors->get_wordcamp_me_sponsors( $wordcamp_id ) );
		} else {
			$recipient = get_post_meta( $wordcamp_id, 'E-mail Address', true );
		}

		return $recipient;
	}

	/**
	 * Validate a given e-mail address or array of e-mail addresses
	 *
	 * @param string | array $emails
	 * @return bool
	 */
	protected function validate_email_addresses( $emails ) {
		$emails = (array) $emails;

		foreach ( $emails as $email ) {
			if ( ! is_email( $email ) ) {
				return false;
			}
		}

		return true;
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
				$recipient = $this->get_recipient( $wordcamp->ID, $email->ID );
				
				if ( $this->timed_email_is_ready_to_send( $wordcamp, $email, $sent_email_ids ) ) {
					if ( $this->mail( $recipient, $email->post_title, $email->post_content, array(), $email, $wordcamp ) ) {
						$sent_email_ids[] = $email->ID;
						update_post_meta( $wordcamp->ID, 'wcor_sent_email_ids', $sent_email_ids );
					}
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
		}
		
		return $ready;
	}

	/**
	 * Get all of the reminder posts assigned to the given trigger.
	 *
	 * @param string $trigger
	 * @return array
	 */
	protected function get_triggered_posts( $trigger ) {
		$posts = get_posts( array(
			'posts_per_page' => -1,
			'post_type'      => WCOR_Reminder::POST_TYPE_SLUG,
			'meta_query'     => array(
				array(
					'key'    => 'wcor_send_when',
					'value'  => 'wcor_send_trigger',
				),
				array(
					'key'    => 'wcor_which_trigger',
					'value'  => $trigger,
				),
			),
		) );

		return $posts;
	}

	/**
	 * Determine if the given e-mail should be sent individually.
	 *
	 * Most e-mails with multiple recipients can be sent as a single message with all recipients in the To field,
	 * but some should be sent to each recipient individually in order to appear more personalized.
	 *
	 * @todo Maybe add a meta field so this can be easily controlled by the e-mail creator.
	 *
	 * @param int $email_id
	 * @return bool
	 */
	protected function send_individual_emails( $email_id ) {
		$send_where = get_post_meta( $email_id, 'wcor_send_where', true );

		return 'wcor_send_mes' == $send_where;
	}

	/**
	 * Sends e-mails hooked to the wcor_added_to_schedule trigger.
	 *
	 * This fires when a WordCamp is added to the schedule (i.e., when their `wordcamp` post goes from 'draft' to 'publish').
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $wordcamp
	 */
	public function send_trigger_added_to_schedule( $new_status, $old_status, $wordcamp ) {
		if ( empty( $wordcamp->post_type ) || WCPT_POST_TYPE_ID != $wordcamp->post_type ) {
			return;
		}

		if ( 'pending' != $old_status || 'publish' != $new_status ) {
			return;
		}

		$this->send_triggered_emails( $wordcamp, 'wcor_added_to_schedule' );
	}

	/**
	 * Sends e-mails hooked to the wcor_organizer_added_to_central trigger.
	 *
	 * This fires when an application is approved and the lead organizer's WordPress.org account
	 * is added as a Contributor to central.wordcamp.org.
	 *
	 * wcor_organizer_added_to_central is only triggered when the organizer has successfully been
	 * added to Central, so we don't need to do any precondition checks like other trigger callbacks.
	 *
	 * @param WP_Post $wordcamp
	 */
	public function send_trigger_organizer_added_to_central( $wordcamp ) {
		$this->send_triggered_emails( $wordcamp, 'wcor_organizer_added_to_central' );
	}

	/**
	 * Sends e-mails hooked to the wcor_wordcamp_site_created trigger.
	 *
	 * This fires when a new WordCamp.org ste is created.
	 *
	 * wcor_wordcamp_site_created is only triggered when a new site has successfully been created,
	 * so we don't need to do any precondition checks like other trigger callbacks.
	 *
	 * @param int $wordcamp_id
	 */
	public function send_trigger_wordcamp_site_created( $wordcamp_id ) {
		$this->send_triggered_emails( get_post( $wordcamp_id ), 'wcor_wordcamp_site_created' );
	}

	/**
	 * Send emails associated with the given trigger.
	 *
	 * This will save a record of which e-mails have already been sent and avoid sending duplicates.
	 *
	 * @param WP_Post $wordcamp
	 * @param string $trigger
	 */
	protected function send_triggered_emails( $wordcamp, $trigger ) {
		$sent_email_ids = (array) get_post_meta( $wordcamp->ID, 'wcor_sent_email_ids', true );
		$emails         = $this->get_triggered_posts( $trigger );

		foreach( $emails as $email ) {
			$recipient = $this->get_recipient( $wordcamp->ID, $email->ID );

			if ( ! in_array( $email->ID, $sent_email_ids ) ) {
				if ( $this->mail( $recipient, $email->post_title, $email->post_content, array(), $email, $wordcamp ) ) {
					$sent_email_ids[] = $email->ID;
					update_post_meta( $wordcamp->ID, 'wcor_sent_email_ids', $sent_email_ids );
				}
			}
		}
	}
}
