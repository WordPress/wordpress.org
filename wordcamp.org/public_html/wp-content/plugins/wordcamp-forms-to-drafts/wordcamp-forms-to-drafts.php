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

/*
 * @todo
 * - Refactor the update_post_meta() loop in each method into a DRY function.
 */

class WordCamp_Forms_To_Drafts {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_print_styles',          array( $this, 'print_front_end_styles'      )        );
		add_filter( 'the_content',              array( $this, 'force_login_to_use_form'     ),  9    );
		add_action( 'template_redirect',        array( $this, 'populate_form_based_on_user' ),  9    );
		add_action( 'grunion_pre_message_sent', array( $this, 'call_for_sponsors'           ), 10, 3 );
		add_action( 'grunion_pre_message_sent', array( $this, 'call_for_speakers'           ), 10, 3 );
	}

	/**
	 * Print CSS for the front-end
	 */
	public function print_front_end_styles() {
		if ( ! $this->form_requires_login( $this->get_current_form_id() ) ) {
			return;
		}

		?>

		<style>
			<?php require_once( __DIR__ . '/front-end.css' ); ?>
		</style>

		<?php
	}

	/**
	 * Force user to login to use certain forms.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function force_login_to_use_form( $content ) {
		$form_id              = $this->get_current_form_id();
		$please_login_message = '';

		if ( ! $this->form_requires_login( $form_id ) ) {
			return $content;
		}

		switch ( $form_id ) {
			case 'call-for-speakers':
				$please_login_message = sprintf(
					__( 'Before submitting your speaker proposal, please <a href="%s">log in to WordCamp.org</a> using your Word<em><strong>Press</strong></em>.org account*.', 'wordcamporg' ),
					wp_login_url( get_permalink() )
				);
				break;
		}

		return $this->inject_disabled_form_elements( $content, $please_login_message );
	}

	/**
	 * Inject the HTML elements that are used to disable a form until the user logs in
	 *
	 * @param string $content
	 * @param string $please_login_message
	 *
	 * @return string
	 */
	protected function inject_disabled_form_elements( $content, $please_login_message ) {
		$please_login_message = str_replace(
			__( 'Please use your <strong>WordPress.org</strong>* account to log in.', 'wordcamporg' ),
			$please_login_message,
			wcorg_login_message( '', get_permalink() )
		);

		// Prevent wpautop() from converting tabs into empty paragraphs in #wcorg-login-message
		$please_login_message = trim( str_replace( "\t", '', $please_login_message ) );

		$form_wrapper = '<div class="wcfd-disabled-form">' . $please_login_message . '<div class="wcfd-overlay"></div> [contact-form';
		$content      = str_replace( '[contact-form',   $form_wrapper,           $content );
		$content      = str_replace( '[/contact-form]', '[/contact-form]</div>', $content );

		return $content;
	}

	/**
	 * Get the WCFD ID of the current form
	 *
	 * @return string
	 */
	protected function get_current_form_id() {
		global $post;
		$form_id = '';

		if ( is_a( $post, 'WP_Post' ) ) {
			$form_id = get_post_meta( $post->ID, 'wcfd-key', true );
		}

		return $form_id;
	}

	/**
	 * Determine if the current form requires a login to use it
	 *
	 * @param string $form_id
	 *
	 * @return bool
	 */
	protected function form_requires_login( $form_id ) {
		$forms_that_require_login = array( 'call-for-speakers' );

		return in_array( $form_id, $forms_that_require_login, true ) && ! is_user_logged_in();
	}

	/**
	 * Populate certain form fields based on the current user.
	 *
	 * @todo Maybe remove username field, or make it readonly. We don't need an explicit field since we can just
	 *       grab the current user value, but it might be good to let them see/change which one they're using.
	 */
	public function populate_form_based_on_user() {
		global $current_user, $post;

		if ( ! is_user_logged_in() || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		$current_user = wp_get_current_user();
		$form_id = get_post_meta( $post->ID, 'wcfd-key', true );

		switch ( $form_id ) {
			case 'call-for-speakers':
				$default_values = array(
					'Name'                   => $current_user->display_name,
					'Email Address'          => $current_user->user_email,
					'WordPress.org Username' => $current_user->user_login,
				);

				foreach ( $default_values as $field_label => $default_value ) {
					$field_id = $this->get_grunion_field_id( $post->ID, $field_label );

					if ( ! isset( $_POST[ $field_id ] ) ) {
						$_POST[ $field_id ] = $default_value;
					}
				}

				break;
		}
	}

	/**
	 * Get the Grunion field ID
	 *
	 * This is a simplified version of what happens in Grunion_Contact_Form_Field::__construct()
	 *
	 * @todo submit Jetpack PR to modularize that logic so we can just call it directly instead of duplicating it
	 *
	 * @param int $page_id
	 * @param string $label
	 *
	 * @return string
	 */
	protected function get_grunion_field_id( $page_id, $label ) {
		$id = sprintf(
			'g%s-%s',
			$page_id,
			sanitize_title_with_dashes( preg_replace( '/[^a-zA-Z0-9.-_:]/', '', $label ) )
		);

		return $id;
	}

	/**
	 * Remove prefixes from form labels
	 *
	 * Grunion prefixes the field keys with 'N_', so that 'Name becomes '1_Name'. That prevents directly accessing
	 * the values, since the number is unknown.
	 *
	 * @param array $prefixed_values
	 *
	 * @return array
	 */
	protected function get_unprefixed_grunion_form_values( $prefixed_values ) {
		$unprefixed_values = array();

		foreach ( $prefixed_values as $key => $value ) {
			$unprefixed_values[ preg_replace( '#^\d+_#i', '', $key ) ] = $value;
		}

		return $unprefixed_values;
	}

	/**
	 * Identify the form that the submission is associated with.
	 *
	 * This requires that the post containing the [contact-form] shortcode has a meta field named 'wcfd-key' added
	 * with the value of the corresponding submission handler.
	 *
	 * @param int $submission_id
	 *
	 * @return string | false
	 */
	protected function get_form_key( $submission_id ) {
		$key        = false;
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
	 *
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
	 * on the current site. If we don't do this, PHP notices will be generated and will break the post/redirect/get
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
	 * Create a draft Sponsor post from a Call for Sponsors form submission.
	 *
	 * @todo
	 * - Update WordCamp_New_Site to inject wcfd-key meta and anything else necessary to make this active for new
	 *   sites
	 * - Add jetpack form field for Sponsor Level, where options are automatically pulled from wcb_sponsor_level
	 *   taxonomy and the selected term is applied to the drafted post. Maybe need to send PR to add filter to
	 *   insert custom fields programmatically.
	 * - Sideload the logo from submitted URL and set it as the featured image.
	 *
	 * @param int   $submission_id
	 * @param array $all_values
	 * @param array $extra_values
	 */
	public function call_for_sponsors( $submission_id, $all_values, $extra_values ) {
		if ( 'call-for-sponsors' != $this->get_form_key( $submission_id ) ) {
			return;
		}

		$all_values              = $this->get_unprefixed_grunion_form_values( $all_values );
		$sponsor_to_form_key_map = array(
			'_wcpt_sponsor_website' => 'Website',
		);

		$this->simulate_post_type( 'wordcamp' );

		// Create the post
		$draft_id = wp_insert_post( array(
			'post_type'    => 'wcb_sponsor',
			'post_title'   => $all_values['Company Name'],
			'post_content' => $all_values['Company Description'],
			'post_status'  => 'draft',
			'post_author'  => $this->get_user_id_from_username( 'wordcamp' ),
		) );

		// Create the post meta
		if ( $draft_id ) {
			foreach ( $sponsor_to_form_key_map as $sponsor_key => $form_key ) {
				if ( ! empty( $all_values[ $form_key ] ) ) {
					update_post_meta( $draft_id, $sponsor_key, $all_values[ $form_key ] );
				}
			}
		}
	}

	/**
	 * Create draft Speaker and Session posts from a Call for Speakers form submission.
	 *
	 * @todo Add jetpack form field for Track, where options are automatically pulled from wcb_track
	 *       taxonomy and the selected term(s) is applied to the drafted post.
	 * @todo If creating speaker or session fails, report to organizer so that submission doesn't get missed
	 *
	 * @param int   $submission_id
	 * @param array $all_values
	 * @param array $extra_values
	 */
	public function call_for_speakers( $submission_id, $all_values, $extra_values ) {
		if ( 'call-for-speakers' != $this->get_form_key( $submission_id ) ) {
			return;
		}

		global $current_user;

		$all_values = $this->get_unprefixed_grunion_form_values( $all_values );

		if ( ! $speaker_user_id = $this->get_user_id_from_username( $all_values['WordPress.org Username'] ) ) {
			$speaker_user_id                      = $current_user->ID;
			$all_values['WordPress.org Username'] = $current_user->user_login;
		}

		$speaker = $this->get_speaker_from_user_id( $speaker_user_id );

		if ( ! is_a( $speaker, 'WP_Post' ) ) {
			$speaker_id = $this->create_draft_speaker( $all_values );

			if ( ! is_a( $speaker_id, 'WP_Error' ) ) {
				$speaker = get_post( $speaker_id );
			}
		}

		if ( is_a( $speaker, 'WP_Post' ) ) {
			$this->create_draft_session( $all_values, $speaker );
		}
	}

	/**
	 * Get speaker post based on WordPress.org user name
	 *
	 * @param int $user_id
	 *
	 * @return WP_Post | false
	 */
	protected function get_speaker_from_user_id( $user_id ) {
		$speaker_query = new WP_Query( array(
			'post_type'      => 'wcb_speaker',
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),    // Trashed speakers are ignored because they'll likely be deleted

			'meta_query' => array(
				array(
					'key'     => '_wcpt_user_id',
					'value'   => $user_id,
					'compare' => '=',
				),
			),
		) );

		return empty( $speaker_query->post ) ? false : $speaker_query->post;
	}

	/**
	 * Create a drafted speaker post
	 *
	 * @param array $speaker
	 *
	 * @return int | WP_Error
	 */
	protected function create_draft_speaker( $speaker ) {
		$speaker_id = wp_insert_post(
			array(
				'post_type'    => 'wcb_speaker',
				'post_title'   => $speaker['Name'],
				'post_content' => $speaker['Your Bio'],
				'post_status'  => 'draft',
				'post_author'  => $this->get_user_id_from_username( 'wordcamp' ),
			),
			true
		);

		if ( $speaker_id ) {
			update_post_meta( $speaker_id, '_wcb_speaker_email', $speaker[ 'Email Address' ] );
			update_post_meta( $speaker_id, '_wcpt_user_id',      $this->get_user_id_from_username( $speaker['WordPress.org Username'] ) );
		}

		return $speaker_id;
	}

	/**
	 * Create a drafted session post
	 *
	 * @param array   $session
	 * @param WP_Post $speaker
	 *
	 * @return int | WP_Error
	 */
	protected function create_draft_session( $session, $speaker ) {
		$session_id = wp_insert_post(
			array(
				'post_type'    => 'wcb_session',
				'post_title'   => $session['Topic Title'],
				'post_content' => $session['Topic Description'],
				'post_status'  => 'draft',
				'post_author'  => $this->get_user_id_from_username( $session['WordPress.org Username'] ),
			),
			true
		);

		if ( $session_id ) {
			update_post_meta( $session_id, '_wcpt_speaker_id',      $speaker->ID );
			update_post_meta( $session_id, '_wcb_session_speakers', $speaker->post_title );
		}

		return $session_id;
	}
} // end WordCamp_Forms_To_Drafts

$GLOBALS['wordcamp_forms_to_drafts'] = new WordCamp_Forms_To_Drafts();
