<?php
/**
 * Implements meetup application class
 *
 * @package WordCamp Post Type
 */

namespace WordPress_Community\Applications;
use function WordPress_Community\Applications\Meetup\render_meetup_application_form;

require_once dirname( __DIR__ ) . '/wcpt-event/class-event-application.php';

/**
 * Class Meetup_Application
 *
 * @package WordPress_Community\Applications
 */
class Meetup_Application extends Event_Application {

	/**
	 * Used to maintain state across functions. Set in create_post function.
	 *
	 * @var \WP_Post
	 */
	public $post;

	const SHORTCODE_SLUG = 'meetup-organizer-application';

	const POST_TYPE = 'wp_meetup';

	/**
	 * User facing string of event type.
	 *
	 * @return string
	 */
	public static function get_event_label() {
		return __( 'Meetup', 'wordcamporg' );
	}

	/**
	 * Get the post type.
	 *
	 * @return string
	 */
	public static function get_event_type() {
		return self::POST_TYPE;
	}

	/**
	 * Get MeetUp post statuses
	 *
	 * @return array
	 */
	public static function get_post_statuses() {
		return array(
			'wcpt-mtp-nds-vet'   => _x( 'Needs Vetting', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-more-info' => _x( 'More Info Requested', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-nds-ori'   => _x( 'Needs Orientation/Interview', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-nds-sit'   => _x( 'Needs Site', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-nds-trn'   => _x( 'Needs Transfer', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-nds-nw-ow' => _x( 'Needs to assign new owner', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-chng-req'  => _x( 'Changes requested', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-nds-swag'  => _x( 'Needs Swag', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-rejected'  => _x( 'Declined', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-active'    => _x( 'Active', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-dormant'   => _x( 'Dormant', 'Meetup status', 'wordcamporg' ),
			'wcpt-mtp-removed'   => _x( 'Removed', 'Meetup status', 'wordcamporg' ),
		);
	}

	/**
	 * Public statuses for meetup. Meetup having these statuses will be rendered in the tracking widget.
	 *
	 * @return array
	 */
	public static function get_public_post_statuses() {
		return array( 'wcpt-mtp-nds-swag', 'wcpt-mtp-active', 'wcpt-mtp-dormant' );
	}

	/**
	 * Enqueue scripts and stylesheets.
	 */
	function enqueue_assets() {
		global $post;

		wp_register_script(
			'meetup-application',
			plugins_url( 'javascript/applications/meetup.js', __DIR__ ),    // todo won't need this?
			array( 'jquery' ),
			1,
			true
		);

		wp_register_style(
			'meetup-application',
			plugins_url( 'css/applications/meetup.css', __DIR__ ),    // todo need this?
			array( 'wp-community-applications' ),
			1
		);

		if ( isset( $post->post_content ) && has_shortcode( $post->post_content, self::SHORTCODE_SLUG ) ) {
			wp_enqueue_script( 'meetup-application' );
			wp_enqueue_style( 'meetup-application' );
		}
	}

	/**
	 * Render application form
	 *
	 * @param array $countries
	 *
	 * @return null|void
	 */
	public function render_application_form( $countries ) {
		require_once dirname( __DIR__ ) . '/views/applications/meetup/shortcode-application.php';
		render_meetup_application_form( $countries );
	}

	/**
	 * Validate the submitted application data
	 *
	 * @param array $unsafe_data
	 *
	 * @return array|\WP_Error
	 */
	public function validate_data( $unsafe_data ) {
		$safe_data   = array();
		$unsafe_data = shortcode_atts( $this->get_default_application_values(), $unsafe_data );

		$required_fields = array(
			'q_first_name',
			'q_last_name',
			'q_email',
			'q_city',
			'q_state',
			'q_country',
			'q_zip',
			'q_already_a_meetup',
			'q_describe_yourself',
			'q_wporg_username',
		);

		foreach ( $unsafe_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$safe_data[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$safe_data[ $key ] = sanitize_text_field( $value );
			}
		}

		foreach ( $required_fields as $field ) {
			if ( empty( $safe_data[ $field ] ) ) {
				return new \WP_Error( 'required_fields', "Please click on your browser's Back button, and fill in all of the required fields." );
			}
		}

		return $safe_data;
	}

	/**
	 * Get the default values for all application fields
	 *
	 * @return array
	 */
	public function get_default_application_values() {
		// WordCamp uses an ID with questions. Not sure how are they used. Ask @corey
		$values = array(
			'q_first_name'          => '',
			'q_last_name'           => '',
			'q_email'               => '',
			'q_city'                => '',
			'q_state'               => '',
			'q_country'             => '',
			'q_zip'                 => '',
			'q_already_a_meetup'    => '',
			'q_existing_meetup_url' => '',
			'q_describe_yourself'   => '',
			'q_wporg_username'      => '',
			'q_wp_slack_username'   => '',
		);

		return $values;
	}

	/**
	 * Create a meetup post from an application
	 *
	 * @param array $data
	 *
	 * @return bool|\WP_Error
	 */
	public function create_post( $data ) {
		// Create the post.
		$user     = wcorg_get_user_by_canonical_names( $data['q_wporg_username'] );
		$statuses = self::get_post_statuses();

		$post = array(
			'post_type'   => self::get_event_type(),
			'post_title'  => $data['q_city'] . ', ' . $data['q_country'],
			'post_status' => self::get_default_status(),
			'post_author' => is_a( $user, 'WP_User' ) ? $user->ID : 0,
		);

		$post_id = wp_insert_post( $post, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Populate the meta fields.
		add_post_meta( $post_id, '_application_data', $data );

		add_post_meta(
			$post_id, 'Organizer Name', sprintf(
				'%s %s',
				$data['q_first_name'],
				$data['q_last_name']
			)
		);

		add_post_meta( $post_id, 'Email', $data['q_email'] );
		add_post_meta( $post_id, 'City', $data['q_city'] );
		add_post_meta( $post_id, 'State', $data['q_state'] );
		add_post_meta( $post_id, 'Country', $data['q_country'] );
		add_post_meta( $post_id, 'Zip', $data['q_zip'] );
		add_post_meta( $post_id, 'Already a meetup', $data['q_already_a_meetup'] );
		add_post_meta( $post_id, 'Meetup URL', $data['q_existing_meetup_url'] );
		add_post_meta( $post_id, 'Best describe organizer', $data['q_describe_yourself'] );
		add_post_meta( $post_id, 'Primary organizer WordPress.org username', $data['q_wporg_username'] );
		add_post_meta( $post_id, 'Skype/Slack', $data['q_wp_slack_username'] );
		add_post_meta( $post_id, 'Date Applied', time() );

		add_post_meta(
			$post_id, '_status_change', array(
				'timestamp' => time(),
				'user_id'   => is_a( $user, 'WP_User' ) ? $user->ID : 0,
				'message'   => sprintf( '%s &rarr; %s', 'Application', $statuses[ self::get_default_status() ] ),
			)
		);

		$this->post = get_post( $post_id );

		return true;
	}

	/**
	 * Get the default status.
	 *
	 * @return string
	 */
	public static function get_default_status() {
		return 'wcpt-mtp-nds-vet';
	}

	/**
	 * Get organizer email if set
	 *
	 * @return null|string
	 */
	public function get_organizer_email() {
		if ( isset( $this->post ) && isset( $this->post->ID ) ) {
			return get_post_meta( $this->post->ID, 'q_email' );
		}
	}

	/**
	 * Get meetup location if set
	 *
	 * @return null|string
	 */
	public function get_event_location() {
		if ( isset( $this->post ) && isset( $this->post->ID ) ) {
			return get_post_meta( $this->post->ID, 'q_city' );
		}
	}

}
