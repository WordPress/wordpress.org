<?php
/**
 * Implement abstract class for Event_Application
 *
 * @package WordCamp Post Type
 */

namespace WordPress_Community\Applications;
require_once WCPT_DIR . 'wcpt-event/notification.php';

/**
 * Class Event_Application
 * Provides interface for event application
 *
 * @package WordPress_Community\Applications
 */
abstract class Event_Application {

	/**
	 * Get user facing string of event type.
	 *
	 * @return string
	 */
	abstract public static function get_event_label();

	/**
	 * Get post type.
	 *
	 * @return string
	 */
	abstract public static function get_event_type();

	/**
	 * Common assets used by all event types.
	 */
	public function enqueue_common_assets() {
		wp_register_style(
			'wp-community-applications',
			plugins_url( 'css/applications/common.css', __DIR__ ),
			array(),
			1
		);
		wp_register_style(
			'wordcamp-application',
			plugins_url( 'css/applications/wordcamp.css', __DIR__ ),
			array( 'wp-community-applications' ),
			1
		);

		wp_enqueue_style( 'wordcamp-application' );
		wp_enqueue_style( 'wp-community-applications' );
	}

	/**
	 * Render the output the of the application forms shortcode.
	 *
	 * @todo Use force_login_to_view_form() and populate_form_based_on_user().
	 *
	 * @return string
	 */
	public function render_application_shortcode() {
		ob_start();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- We do not verify nonce for frontend forms because WP Super Cache may cache an expired nonce token.
		if ( isset( $_POST['submit-application'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->submit_application( $_POST );
		} else {
			$countries = wcorg_get_countries();
			$this->render_application_form( $countries );
		}

		return ob_get_clean();
	}

	/**
	 * Render event application from for organizer.
	 *
	 * @param array $countries List of countries.
	 *
	 * @return null
	 */
	abstract public function render_application_form( $countries );

	/**
	 * Submit application details. Calls `create_post` to actually create the event.
	 *
	 * @param array $post_data Form params.
	 */
	public function submit_application( $post_data ) {
		$application_data = $this->validate_data( $post_data );

		if ( $this->is_rate_limited() ) {
			$message        = __( 'You have submitted too many applications recently. Please wait and try again in a few hours.', 'wordcamporg' );
			$notice_classes = 'notice-error';
		} elseif ( is_wp_error( $application_data ) ) {
			$message        = $application_data->get_error_message();
			$notice_classes = 'notice-error';
		} else {
			$this->create_post( $application_data );
			$this->notify_applicant_application_received(
				$this->get_organizer_email(),
				$this->get_event_location()
			);

			$this->notify_new_application_in_slack();

			$message        = __( "Thank you for your application! We've received it, and we'll contact you once we've had a chance to review it.", 'wordcamporg' );
			$notice_classes = 'notice-success';
		}

		$this->display_notice( $message, $notice_classes );
	}

	/**
	 * Validate application data. Returns either error object or data.
	 *
	 * @param array $unsafe_data
	 *
	 * @return array|\WP_Error
	 */
	abstract public function validate_data( $unsafe_data );

	/**
	 * Create and insert post into DB
	 *
	 * @param array $application_data
	 *
	 * @return bool
	 */
	abstract public function create_post( $application_data );


	/**
	 * Check if the application submitter has been rate limited
	 *
	 * This isn't really designed to protect against DDoS or anything sophisticated; it just prevents us from having
	 * to clean up thousands of fake applications when security researchers use bots to probe for vulnerabilities.
	 *
	 * @return bool
	 */
	public function is_rate_limited() {
		$limit = 3;

		$previous_entries = get_posts(
			array(
				'post_type'      => $this->get_event_type(),
				'post_status'    => get_post_stati(), // This will include trashed posts, unlike 'any'.
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',

				'date_query'     => array(
					array(
						'column'    => 'post_date',
						'after'     => '1 hour ago',
						'inclusive' => true,
					),
				),

				'meta_query'     => array(
					array(
						'key'   => '_application_submitter_ip_address',
						'value' => $_SERVER['REMOTE_ADDR'],
					),
				),
			)
		);

		return count( $previous_entries ) >= $limit;
	}

	/**
	 * Get email of organizer if set
	 *
	 * @return string|null
	 */
	abstract public function get_organizer_email();

	/**
	 * Get location of event if set.
	 *
	 * @return string|null
	 */
	abstract public function get_event_location();

	/**
	 * Get default status for a new application.
	 *
	 * @return string|null
	 */
	abstract public static function get_default_status();

	/**
	 * Get publicly accessible report url for a event. Should return null if such report is published.
	 *
	 * @return string|null
	 */
	abstract public static function get_application_report_url();

	/**
	 * Display a notice to applicant while submitting the form.
	 *
	 * @param string $message Message to be displayed.
	 * @param string $notice_classes Space separated list of classes to be applied to message div.
	 */
	public function display_notice( $message, $notice_classes ) {
		?>
		<div class="notice notice-large <?php echo esc_attr( $notice_classes ); ?>">
			<?php echo esc_html( $message ); ?>
		</div>
		<?php
	}

	/**
	 * Notify the applicant that we've received their application
	 *
	 * @param string $email_address
	 * @param string $event_city
	 */
	public function notify_applicant_application_received( $email_address, $event_city ) {
		//translators: Name of the event. E.g. WordCamp or meetup.
		$subject = sprintf( __( "We've received your %s application", 'wordcamporg' ), $this->get_event_label() );
		$headers = array( 'Reply-To: support@wordcamp.org' );
		//translators: Name and city of the event. E.g. WordCamp New York.
		$message = sprintf(
			__(
				"Thank you for applying to organize a %1\$s in %2\$s! We'll send you a follow-up e-mail once we've had a chance to review your application.",
				'wordcamporg'
			),
			$this->get_event_label(),
			sanitize_text_field( $event_city )
		);

		wp_mail( $email_address, $subject, $message, $headers );
	}

	/**
	 * Notify in community slack channel that we've received an application.
	 */
	public function notify_new_application_in_slack() {

		// Not translating because this will be sent to community events slack channel.
		$message = sprintf( 'A %s application for %s has been received.', $this->get_event_label(), $this->get_event_location() );

		$public_report_url = $this->get_application_report_url();
		if ( isset( $public_report_url ) ) {
			// `<%s|here> is syntax for slack message to hyperlink text `here` with url provided in `%s`
			$message = sprintf( '%s Public status can be followed on <%s|%s application report page>.', $message, $public_report_url, $this->get_event_label() );
		}

		$default_status = $this->get_default_status();
		$queue_size = wp_count_posts( $this->get_event_type() )->$default_status;
		if ( isset( $queue_size ) ) {
			$singular = "is $queue_size application";
			$plural   = "are $queue_size applications";
			$message = sprintf(
				"%s\n _There %s in vetting queue._",
				$message,
				1 === $queue_size ? $singular : $plural
			);
		}

		$attachment = create_event_attachment( $message,  sprintf( 'New %s application ', $this->get_event_label() ) );
		return wcpt_slack_notify( COMMUNITY_TEAM_SLACK, $attachment );
	}
}
