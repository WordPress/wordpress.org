<?php

/**
 * A Custom post type to store the body of the reminder e-mails
 * @package WordCampOrganizerReminders
 */

class WCOR_Reminder {
	const AUTOMATED_POST_TYPE_SLUG = 'organizer-reminder';
	const REQUIRED_CAPABILITY      = 'manage_options';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',                              array( $this, 'register_post_type' ) );
		add_action( 'admin_init',                        array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_menu',                        array( $this, 'register_menu_pages' ) );
		add_action( 'save_post_' . self::AUTOMATED_POST_TYPE_SLUG, array( $this, 'save_post' ), 10, 2 );
	}

	/**
	 * Registers the Reminder post type
	 */
	public function register_post_type() {
		$automated_labels = array(
			'name'               => 'Automated Reminders',
			'singular_name'      => 'Automated Reminder',
			'add_new'            => 'Add New Automated',
			'add_new_item'       => 'Add New Automated Reminder',
			'edit'               => 'Edit',
			'edit_item'          => 'Edit Automated Reminder',
			'new_item'           => 'New Automated Reminder',
			'view'               => 'View Automated Reminders',
			'view_item'          => 'View Automated Reminder',
			'search_items'       => 'Search Automated Reminders',
			'not_found'          => 'No automated reminders',
			'not_found_in_trash' => 'No automated reminders',
			'parent'             => 'Parent Automated Reminder',
		);

		$automated_params = array(
			'labels'              => $automated_labels,
			'singular_label'      => 'Automated Reminder',
			'public'              => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'organizer-reminders',
			'show_in_nav_menus'   => false,
			'hierarchical'        => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
		);

		register_post_type( self::AUTOMATED_POST_TYPE_SLUG, $automated_params );
	}

	/**
	 * Adds meta boxes for the custom post type
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wcor_reminder_details',
			'Reminder Details',
			array( $this, 'markup_reminder_details' ),
			self::AUTOMATED_POST_TYPE_SLUG,
			'normal',
			'high'
		);

		add_meta_box(
			'wcor_manually_send',
			__( 'Manually Send', 'wordcamporg' ),
			array( $this, 'markup_manually_send' ),
			self::AUTOMATED_POST_TYPE_SLUG,
			'side'
		);

		add_meta_box(
			'wcor_available_placeholders',
			'Available Placeholders',
			array( $this, 'render_available_placeholders' ),
			self::AUTOMATED_POST_TYPE_SLUG,
			'side'
		);
	}

	/**
	 * Builds the markup for the Reminder Details metabox
	 *
	 * @param object $post
	 */
	public function markup_reminder_details( $post ) {
		$send_where              = get_post_meta( $post->ID, 'wcor_send_where' );

		require_once( __DIR__ . '/views/metabox-reminder-details.php' );
	}

	/**
	 * Builds the markup for the Available Placeholders metabox.
	 */
	public function render_available_placeholders() {
		require_once( __DIR__ . '/views/metabox-placeholders.php' );
	}

	/**
	 * Builds the markup for the Manually Send metabox
	 *
	 * @param object $post
	 */
	public function markup_manually_send( $post ) {
		?>

		<p><?php _e( 'Check the box below and save the post to manually send this message to the assigned recipient(s), using the data from the selected WordCamp.', 'wordcamporg' ); ?></p>

		<p><?php _e( 'It will be sent immediately, regardless of when it is scheduled to be sent automatically, and regardless of whether or not it has already been sent automatically.', 'wordcamporg' ); ?></p>

		<p>
			<?php echo get_wordcamp_dropdown( 'wcor_manually_send_wordcamp' ); ?>
		</p>

		<p>
			<input id="wcor_manually_send_checkbox" name="wcor_manually_send" type="checkbox">
			<label for="wcor_manually_send_checkbox"><?php _e( 'Manually send this e-mail', 'wordcamporg' ); ?></label>
		</p>

		<?php
	}

	/**
	 * Register new admin pages
	 */
	public function register_menu_pages() {
		add_menu_page(
			'Organizer Reminders',
			'Organizer Reminders',
			self::REQUIRED_CAPABILITY,
			'organizer-reminders',
			'',
			'dashicons-email-alt',
			30
		);
	}

	/**
	 * Checks to make sure the conditions for saving post meta are met
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		$ignored_actions = array( 'trash', 'untrash', 'restore' );

		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], $ignored_actions ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts', $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! isset( $post->ID ) || $post->post_status == 'auto-draft' ) {
			return;
		}

		$this->save_post_meta( $post, $_POST );
		$this->send_manual_email( $post, $_POST );
	}

	/**
	 * Saves the meta data for the reminder post
	 *
	 * @param WP_Post $post
	 * @param array $new_meta
	 */
	protected function save_post_meta( $post, $new_meta ) {
		$send_where_whitelist = array( 'wcor_send_organizers', 'wcor_send_sponsor_wrangler', 'wcor_send_budget_wrangler', 'wcor_send_venue_wrangler', 'wcor_send_speaker_wrangler', 'wcor_send_food_wrangler', 'wcor_send_swag_wrangler', 'wcor_send_volunteer_wrangler', 'wcor_send_printing_wrangler', 'wcor_send_design_wrangler', 'wcor_send_website_wrangler', 'wcor_send_social_wrangler', 'wcor_send_a_v_wrangler', 'wcor_send_party_wrangler', 'wcor_send_travel_wrangler', 'wcor_send_safety_wrangler', 'wcor_send_mes', 'wcor_send_camera_wrangler', 'wcor_send_custom' );

		delete_post_meta( $post->ID, 'wcor_send_where' );
		if ( isset( $new_meta['wcor_send_where'] ) ) {
			foreach( $new_meta['wcor_send_where'] as $send_where ) {
				if ( in_array( $send_where, $send_where_whitelist ) ) {
					add_post_meta( $post->ID, 'wcor_send_where', $send_where );
				}
			}
		}

		if ( isset( $new_meta['wcor_send_custom_address'] ) && is_email( $new_meta['wcor_send_custom_address'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_custom_address', sanitize_email( $new_meta['wcor_send_custom_address'] ) );
		}

		if ( isset( $new_meta['wcor_send_when'] ) ) {
			if ( in_array( $new_meta['wcor_send_when'], array( 'wcor_send_before', 'wcor_send_after', 'wcor_send_after_pending', 'wcor_send_trigger' ) ) ) {
				update_post_meta( $post->ID, 'wcor_send_when', $new_meta['wcor_send_when'] );
			}
		}

		if ( isset( $new_meta['wcor_send_days_before'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_days_before', absint( $new_meta['wcor_send_days_before'] ) );
		}

		if ( isset( $new_meta['wcor_send_days_after'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_days_after', absint( $new_meta['wcor_send_days_after'] ) );
		}

		if ( isset( $new_meta['wcor_send_days_after_pending'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_days_after_pending', absint( $new_meta['wcor_send_days_after_pending'] ) );
		}

		if ( isset( $new_meta['wcor_which_trigger'] ) ) {
			if ( in_array( $new_meta['wcor_which_trigger'], array_merge( array( 'null' ), array_keys( $GLOBALS['WCOR_Mailer']->triggers ) ) ) ) {
				update_post_meta( $post->ID, 'wcor_which_trigger', $new_meta['wcor_which_trigger'] );
			}
		}
	}

	/**
	 * Sends an e-mail manually.
	 *
	 * This provides a way to send e-mails at will, regardless of the time or trigger that the e-mail is normally
	 * associated with, and regardless of whether or not the e-mail has already been sent to the recipient.
	 *
	 * @todo Add admin notices, but it's a pain to make them persist through the post/redirect/get process.
	 *       Will be easy if #11515 lands in Core.
	 *
	 * @param WP_Post $email
	 * @param array   $form_values
	 */
	protected function send_manual_email( $email, $form_values ) {
		/** @var $WCOR_Mailer WCOR_Mailer */
		global $WCOR_Mailer;

		if ( empty( $form_values['wcor_manually_send'] ) || 'on' != $form_values['wcor_manually_send'] || in_array( $form_values['wcor_manually_send_wordcamp'], array( 'instructions', 'spacer' ) ) ) {
			return;
		}

		$wordcamp = get_post( $form_values['wcor_manually_send_wordcamp'] );
		$WCOR_Mailer->send_manual_email( $email, $wordcamp );
	}
}
