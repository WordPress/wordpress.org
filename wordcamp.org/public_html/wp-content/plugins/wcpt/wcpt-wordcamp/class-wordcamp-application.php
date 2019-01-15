<?php

namespace WordPress_Community\Applications;

use function WordPress_Community\Applications\WordCamp\render_wordcamp_application_form;

require_once dirname( __DIR__ ) . '/wcpt-event/class-event-application.php';
require_once dirname( __DIR__ ) . '/views/applications/wordcamp/shortcode-application.php';

class WordCamp_Application extends Event_Application {

	public $post;

	const SHORTCODE_SLUG = 'wordcamp-organizer-application';

	static function get_event_label() {
		return __( 'WordCamp', 'wordcamporg' );
	}

	/**
	 * Gets the post type
	 *
	 * @return string
	 */
	static function get_event_type() {
		return WCPT_POST_TYPE_ID;
	}

	/**
	 * Enqueue scripts and stylesheets
	 */
	function enqueue_assets() {
		global $post;

		wp_register_script(
			'wordcamp-application',
			plugins_url( 'javascript/applications/wordcamp.js', __DIR__ ),
			array( 'jquery' ),
			1,
			true
		);

		if ( isset( $post->post_content ) && has_shortcode( $post->post_content, self::SHORTCODE_SLUG ) ) {
			wp_enqueue_script( 'wordcamp-application' );
		}
	}

	/**
	 * Render application form
	 *
	 * @param array $countries
	 *
	 * @return null|void
	 */
	function render_application_form( $countries ) {
		render_wordcamp_application_form( $countries );
	}

	/**
	 * Validate the submitted application data
	 *
	 * @param array $unsafe_data
	 *
	 * @return array|\WP_Error
	 */
	function validate_data( $unsafe_data ) {
		$safe_data   = array();
		$unsafe_data = shortcode_atts( $this->get_default_application_values(), $unsafe_data );

		$required_fields = array(
			'q_1079074_first_name',
			'q_1079074_last_name',
			'q_1079059_email',
			'q_4236565_wporg_username',
			'q_1079103_wordcamp_location',
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
	function get_default_application_values() {
		$values = array(
			// Part 1
			'q_1079074_first_name'                       => '',
			'q_1079074_last_name'                        => '',
			'q_1079059_email'                            => '',
			'q_1079060_add1'                             => '',
			'q_1079060_add2'                             => '',
			'q_1079060_city'                             => '',
			'q_1079060_state'                            => '',
			'q_1079060_zip'                              => '',
			'q_1079060_country'                          => '',
			'q_1045947_years_using_wp'                   => '',
			'q_1068246_ways_involved'                    => array(),
			'q_1068246_ways_involved_other'              => '',
			'q_1046032_attended_camp_before'             => '',
			'q_1046033_camps_been_to'                    => '',
			'q_1068223_hope_to_accomplish'               => array(),
			'q_1068223_hope_to_accomplish_other'         => '',

			// Part 2
			'q_1045950_active_meetup'                    => '',
			'q_1045953_role_in_meetup'                   => '',
			'q_1045972_meetup_url'                       => '',
			'q_1045967_meetup_members'                   => '',
			'q_1045956_how_often_meetup'                 => '',
			'q_1045971_how_many_attend'                  => '',
			'q_1079086_other_tech_events'                => '',
			'q_1079082_other_tech_events_success'        => '',

			// Part 3
			'q_1079103_wordcamp_location'                => '',
			'q_1046006_wordcamp_date'                    => '',
			'q_1046007_how_many_attendees'               => '',
			'q_1046038_organized_event_before'           => '',
			'q_1046099_describe_events'                  => '',
			'q_1046101_have_co_organizers'               => '',
			'q_1068188_relationship_co_organizers'       => array(),
			'q_1068188_relationship_co_organizers_other' => '',
			'q_1068187_co_organizer_contact_info'        => '',
			'q_1068214_raise_money'                      => '',
			'q_1068220_interested_sponsors'              => '',
			'q_1046009_good_presenters'                  => '',
			'q_1046021_presenter_names'                  => '',
			'q_1068197_venue_connections'                => '',
			'q_1068212_venues_considering'               => '',
			'q_4236565_wporg_username'                   => '',
			'q_4236565_slack_username'                   => '',
			'where_find_online'                          => '',
			'q_1079098_anything_else'                    => '',

			// Bonus
			'q_1079112_best_describes_you'               => '',
			'q_1079112_best_describes_you_other'         => '',
		);

		return $values;
	}

	/**
	 * Gets default status of new WordCamp application
	 *
	 * @return string
	 */
	public static function get_default_status() {
		return WCPT_DEFAULT_STATUS;
	}

	/**
	 * Public report URL for WordCamp applications.
	 *
	 * @return string
	 */
	public static function get_application_report_url() {
		return "https://central.wordcamp.org/reports/application-status/";
	}

	/**
	 * Create a WordCamp post from an application
	 *
	 * @param array $data
	 *
	 * @return bool|\WP_Error
	 */
	function create_post( $data ) {
		// Create the post
		$user      = wcorg_get_user_by_canonical_names( $data['q_4236565_wporg_username'] );
		$statues   = \WordCamp_Loader::get_post_statuses();
		$countries = wcorg_get_countries();

		$post = array(
			'post_type'   => $this->get_event_type(),
			'post_title'  => 'WordCamp ' . $data['q_1079103_wordcamp_location'],
			'post_status' => WCPT_DEFAULT_STATUS,
			'post_author' => is_a( $user, 'WP_User' ) ? $user->ID : 7694169, // Set `wordcamp` as author if supplied username is not valid
		);

		$post_id = wp_insert_post( $post, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Populate the meta fields
		add_post_meta( $post_id, '_application_data', $data );
		add_post_meta( $post_id, '_application_submitter_ip_address', $_SERVER['REMOTE_ADDR'] );


		add_post_meta(
			$post_id, 'Organizer Name', sprintf(
				'%s %s',
				$data['q_1079074_first_name'],
				$data['q_1079074_last_name']
			)
		);

		add_post_meta( $post_id, 'Email Address', $data['q_1079059_email'] );
		add_post_meta( $post_id, 'Location', $data['q_1079103_wordcamp_location'] );
		add_post_meta( $post_id, 'Number of Anticipated Attendees', $data['q_1046007_how_many_attendees'] );
		add_post_meta( $post_id, 'WordPress.org Username', $data['q_4236565_wporg_username'] );

		add_post_meta(
			$post_id, 'Mailing Address', sprintf(
				"%s\n%s%s%s %s\n%s",
				$data['q_1079060_add1'],
				$data['q_1079060_add2'] ? $data['q_1079060_add2'] . "\n" : '',
				$data['q_1079060_city'] ? $data['q_1079060_city'] . ', ' : '',
				$data['q_1079060_state'],
				$data['q_1079060_zip'],
				$data['q_1079060_country'] ? $countries[ $data['q_1079060_country'] ]['name'] : ''
			)
		);

		add_post_meta(
			$post_id, '_status_change', array(
				'timestamp' => time(),
				'user_id'   => is_a( $user, 'WP_User' ) ? $user->ID : 0,
				'message'   => sprintf( '%s &rarr; %s', 'Application', $statues[ WCPT_DEFAULT_STATUS ] ),
			)
		);

		$this->post = get_post( $post_id );
		return true;
	}

	/**
	 * Get organizer email if set
	 *
	 * @return null|string
	 */
	function get_organizer_email() {
		if ( isset( $this->post ) && isset( $this->post->ID ) ) {
			return get_post_meta( $this->post->ID, 'Email Address', true );
		}
	}

	/**
	 * Get meetup location if set
	 *
	 * @return null|string
	 */
	function get_event_location() {
		if ( isset( $this->post ) && isset( $this->post->ID ) ) {
			return get_post_meta( $this->post->ID, 'Location', true );
		}
	}

}
