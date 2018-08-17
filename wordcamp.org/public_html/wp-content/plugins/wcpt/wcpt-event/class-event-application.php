<?php
/**
 * Implement abstract class for Event_Application
 *
 * @package WordCamp Post Type
 */

namespace WordPress_Community\Applications;

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
	 * Render the output the of the [meetup-organizer-application] shortcode.
	 *
	 * @todo Use force_login_to_view_form() and populate_form_based_on_user().
	 *
	 * @return string
	 */
	public function render_application_shortcode() {
		ob_start();

		if ( isset( $_POST['submit-application'] ) ) {
			$this->submit_application();
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
	 */
	public function submit_application() {
		$application_data = $this->validate_data( $_POST );
		if ( $this->is_rate_limited() ) {
			$message        = __( 'You have submitted too many applications recently. Please wait and try again in a few hours.', 'wordcamporg' );
			$notice_classes = 'notice-error';
		} elseif ( is_wp_error( $application_data ) ) {
			$message        = $application_data->get_error_message();
			$notice_classes = 'notice-error';
		} else {
			$this->create_post( $application_data );
			$this->notify_applicant_application_received( $this->get_organizer_email(), $this->get_event_location() );
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
	 * @param string $meetup_city
	 */
	public function notify_applicant_application_received( $email_address, $meetup_city ) {
		//translators: Name of the event. Egs WordCamp.
		$subject = sprintf( __( "We've received your %s application", 'wpct' ), $this->get_event_label() );
		$headers = array( 'Reply-To: support@wordcamp.org' );
		//translators: Name and city of the event. Egs WordCamp in New York.
		$message = sprintf(
			__(
				"Thank you for applying to organize a %s in %s! We'll send you a follow-up e-mail once we've had a chance to review your application.",
				'wpct'
			),
			$this->get_event_label(), sanitize_text_field( $meetup_city )
		);

		wp_mail( $email_address, $subject, $message, $headers );
	}
}
