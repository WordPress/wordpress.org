<?php
/*
Plugin Name: WordCamp Forms to Drafts
Description: Convert form submissions into drafts for our custom post types.
Version:     0.1
Author:      WordCamp Central
Author URI:  http://wordcamp.org
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

class WordCamp_Forms_To_Drafts {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'grunion_pre_message_sent', array( $this, 'returning_organizer_application' ), 10, 3 );
		add_action( 'grunion_pre_message_sent', array( $this, 'new_organizer_application' ), 10, 3 );
	}

	/**
	 * Identify the form that the submission is associated with.
	 *
	 * This requires that the post containing the [contact-form] shortcode has a meta field named 'wcfd-key' added
	 * with the value of the corresponding submission handler.
	 *
	 * @param int $submission_id
	 * @return string | false
	 */
	protected function get_form_key( $submission_id ) {
		$key = false;
		$submission = get_post( $submission_id );

		if ( ! empty( $submission->post_parent ) ) {
			$key = get_post_meta( $submission->post_parent, 'wcfd-key', true );
		}

		return $key;
	}

	/**
	 * Get a user's ID based on their username.
	 *
	 * @param string $username
	 * @return int
	 */
	protected function get_user_id_from_username( $username ) {
		$user = get_user_by( 'login', $username );
		return empty( $user->ID ) ? 0 : $user->ID;
	}

	/**
	 * Simulate the existence of a post type.
	 *
	 * This plugin may need to insert a form into a different site, and the targeted post type may not be active
	 * on the current site. If we don't do this, PHP notices will be generated and will break the post/get/redirect
	 * flow because of the early headers.
	 *
	 * Yes, this is an ugly hack.
	 *
	 * @param $post_type
	 */
	protected function simulate_post_type( $post_type ) {
		global $wp_post_types;

		if ( empty( $wp_post_types[ $post_type ] ) ) {
			$wp_post_types[ $post_type ]       = $wp_post_types['post'];
			$wp_post_types[ $post_type ]->name = $post_type;
		}
	}

	/**
	 * Create a draft WordCamp post from a Returning Organizer Application submission.
	 *
	 * @param int   $submission_id
	 * @param array $all_values
	 * @param array $extra_values
	 */
	public function returning_organizer_application( $submission_id, $all_values, $extra_values ) {
		if ( 'returning-organizer-application' != $this->get_form_key( $submission_id ) ) {
			return;
		}

		$wordcamp_to_form_key_map = array(
			'Location'                        => 'WordCamp City, State, Country',
			'Organizer Name'                  => 'Lead Organizer Name',
			'WordPress.org Username'          => 'Lead Organizer WordPress.org Username',
			'Email Address'                   => 'Lead Organizer Email',
			'Sponsor Wrangler Name'           => 'Sponsor Wrangler Name',
			'Sponsor Wrangler E-mail Address' => 'Sponsor Wrangler E-mail Address',
			'Budget Wrangler Name'            => 'Budget Wrangler Name',
			'Budget Wrangler E-mail Address'  => 'Budget Wrangler E-mail Address',
			'Number of Anticipated Attendees' => 'Number of Anticipated Attendees',
		);

		$this->simulate_post_type( 'wordcamp' );

		switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

		// Create the post
		$draft_id = wp_insert_post( array(
			'post_type'   => 'wordcamp',
			'post_title'  => 'WordCamp ' . $all_values['WordCamp City, State, Country'],
			'post_status' => 'draft',
			'post_author' => $this->get_user_id_from_username( $all_values['Lead Organizer WordPress.org Username'] ),
		) );

		// Create the post meta
		if ( $draft_id ) {
			foreach ( $wordcamp_to_form_key_map as $wordcamp_key => $form_key ) {
				if ( ! empty( $all_values[ $form_key ] ) ) {
					update_post_meta( $draft_id, $wordcamp_key, $all_values[ $form_key ] );
				}
			}
		}

		restore_current_blog();
	}

	/**
	 * Create a draft WordCamp post from a New Organizer Application submission.
	 *
	 * @param int   $submission_id
	 * @param array $all_values
	 * @param array $extra_values
	 */
	public function new_organizer_application( $submission_id, $all_values, $extra_values ) {
		if ( 'new-organizer-application' != $this->get_form_key( $submission_id ) ) {
			return;
		}

		$wordcamp_to_form_key_map = array(
			'Location'                        => 'Enter the city, state/province, and country where you would like to organize a WordCamp.',
			'Organizer Name'                  => 'Lead Organizer Name',
			'WordPress.org Username'          => "Lead Organizer WordPress.org Username. This is the username you'd use to log in to http://wordpress.org/support/. If you don't have one, you can register on wordpress.org at https://wordpress.org/support/register.php",
			'Email Address'                   => 'Lead Organizer Email',
			'Number of Anticipated Attendees' => 'How many people do you think would attend?',
		);

		$this->simulate_post_type( 'wordcamp' );

		switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

		// Create the post
		$draft_id = wp_insert_post( array(
			'post_type'   => 'wordcamp',
			'post_title'  => 'WordCamp ' . $all_values['Enter the city, state/province, and country where you would like to organize a WordCamp.'],
			'post_status' => 'draft',
			'post_author' => $this->get_user_id_from_username( $all_values["Lead Organizer WordPress.org Username. This is the username you'd use to log in to http://wordpress.org/support/. If you don't have one, you can register on wordpress.org at https://wordpress.org/support/register.php"] ),
		) );

		// Create the post meta
		if ( $draft_id ) {
			foreach ( $wordcamp_to_form_key_map as $wordcamp_key => $form_key ) {
				if ( ! empty( $all_values[ $form_key ] ) ) {
					update_post_meta( $draft_id, $wordcamp_key, $all_values[ $form_key ] );
				}
			}

			$mailing_address = sprintf(
				"%s%s, %s %s\n%s",
				empty( $all_values['Lead Organizer Street Address'] ) ? '' : $all_values['Lead Organizer Street Address'] . "\n",
				$all_values['City'],
				$all_values['State/Province'],
				empty( $all_values['ZIP/Postal Code'] ) ? '' : $all_values['ZIP/Postal Code'],
				$all_values['Country']
			);

			update_post_meta( $draft_id, 'Mailing Address', $mailing_address );
		}

		restore_current_blog();
	}
} // end WordCamp_Forms_To_Drafts

$GLOBALS['wordcamp_forms_to_drafts'] = new WordCamp_Forms_To_Drafts();
