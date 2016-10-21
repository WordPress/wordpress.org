<?php

/**
 * A Custom post type to store the body of the reminder e-mails
 * @package WordCampOrganizerReminders
 */

class WCOR_Reminder {
	const POST_TYPE_SLUG = 'organizer-reminder';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',                              array( $this, 'register_post_type' ) );
		add_action( 'admin_init',                        array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE_SLUG, array( $this, 'save_post' ), 10, 2 );
	}

	/**
	 * Registers the Reminder post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => 'Organizer Reminders',
			'singular_name'      => 'Organizer Reminder',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Reminder',
			'edit'               => 'Edit',
			'edit_item'          => 'Edit Reminder',
			'new_item'           => 'New Reminder',
			'view'               => 'View Reminders',
			'view_item'          => 'View Reminder',
			'search_items'       => 'Search Reminders',
			'not_found'          => 'No reminders',
			'not_found_in_trash' => 'No reminders',
			'parent'             => 'Parent Reminder',
		);

		$params = array(
			'labels'              => $labels,
			'singular_label'      => 'Reminder',
			'public'              => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'hierarchical'        => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
		);

		register_post_type( self::POST_TYPE_SLUG, $params );
	}

	/**
	 * Adds meta boxes for the custom post type
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wcor_reminder_details',
			'Reminder Details',
			array( $this, 'markup_reminder_details' ),
			self::POST_TYPE_SLUG,
			'normal',
			'high'
		);

		add_meta_box(
			'wcor_manually_send',
			__( 'Manually Send', 'wordcamporg' ),
			array( $this, 'markup_manually_send' ),
			self::POST_TYPE_SLUG,
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
		$send_custom_address     = get_post_meta( $post->ID, 'wcor_send_custom_address', true );
		$send_when               = get_post_meta( $post->ID, 'wcor_send_when', true );
		$send_days_before        = get_post_meta( $post->ID, 'wcor_send_days_before', true );
		$send_days_after         = get_post_meta( $post->ID, 'wcor_send_days_after', true );
		$send_days_after_pending = get_post_meta( $post->ID, 'wcor_send_days_after_pending', true );
		$which_trigger           = get_post_meta( $post->ID, 'wcor_which_trigger', true );

		?>

		<h4>Who should this e-mail be sent to?</h4>

		<table>
			<tbody>
				<tr>
					<th><input id="wcor_send_organizers" name="wcor_send_where[]" type="checkbox" value="wcor_send_organizers" <?php checked( in_array( 'wcor_send_organizers', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_organizers">The organizing team</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_sponsor_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_sponsor_wrangler" <?php checked( in_array( 'wcor_send_sponsor_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_sponsor_wrangler">The Sponsor Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_budget_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_budget_wrangler" <?php checked( in_array( 'wcor_send_budget_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_budget_wrangler">The Budget Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_venue_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_venue_wrangler" <?php checked( in_array( 'wcor_send_venue_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_venue_wrangler">The Venue Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_speaker_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_speaker_wrangler" <?php checked( in_array( 'wcor_send_speaker_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_speaker_wrangler">The Speaker Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_food_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_food_wrangler" <?php checked( in_array( 'wcor_send_food_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_food_wrangler">The Food/Beverage Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_swag_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_swag_wrangler" <?php checked( in_array( 'wcor_send_swag_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_swag_wrangler">The Swag Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_volunteer_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_volunteer_wrangler" <?php checked( in_array( 'wcor_send_volunteer_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_volunteer_wrangler">The Volunteer Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_printing_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_printing_wrangler" <?php checked( in_array( 'wcor_send_printing_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_printing_wrangler">The Printing Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_design_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_design_wrangler" <?php checked( in_array( 'wcor_send_design_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_design_wrangler">The Design Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_website_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_website_wrangler" <?php checked( in_array( 'wcor_send_website_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_website_wrangler">The Website Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_social_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_social_wrangler" <?php checked( in_array( 'wcor_send_social_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_social_wrangler">The Social Media/Publicity Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_a_v_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_a_v_wrangler" <?php checked( in_array( 'wcor_send_a_v_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_a_v_wrangler">The A/V Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_party_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_party_wrangler" <?php checked( in_array( 'wcor_send_party_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_party_wrangler">The Party Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_travel_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_travel_wrangler" <?php checked( in_array( 'wcor_send_travel_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_travel_wrangler">The Travel Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_safety_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_safety_wrangler" <?php checked( in_array( 'wcor_send_safety_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_safety_wrangler">The Safety Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_mes" name="wcor_send_where[]" type="checkbox" value="wcor_send_mes" <?php checked( in_array( 'wcor_send_mes', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_mes">The WordCamp's Multi-Event Sponsors</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_camera_wrangler" name="wcor_send_where[]" type="checkbox" value="wcor_send_camera_wrangler" <?php checked( in_array( 'wcor_send_camera_wrangler', $send_where ) ); ?>></th>
					<td colspan="2"><label for="wcor_send_camera_wrangler">The Region's Camera Kit Wrangler</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_custom" name="wcor_send_where[]" type="checkbox" value="wcor_send_custom" <?php checked( in_array( 'wcor_send_custom', $send_where ) ); ?>></th>
					<td><label for="wcor_send_custom">A custom address: </label></td>
					<td><input id="wcor_send_custom_address" name="wcor_send_custom_address" type="text" class="regular-text" value="<?php echo esc_attr( $send_custom_address ); ?>" /></td>
				</tr>
			</tbody>
		</table>


		<h4>When should this e-mail be sent?</h4>

		<table>
			<tbody>
				<tr>
					<th><input id="wcor_send_before" name="wcor_send_when" type="radio" value="wcor_send_before" <?php checked( $send_when, 'wcor_send_before' ); ?>></th>
					<td><label for="wcor_send_before">before the camp starts: </label></td>
					<td>
						<input id="wcor_send_days_before" name="wcor_send_days_before" type="text" class="small-text" value="<?php echo esc_attr( $send_days_before ); ?>" />
						<label for="wcor_send_days_before">days</label>
					</td>
				</tr>

				<tr>
					<th><input id="wcor_send_after" name="wcor_send_when" type="radio" value="wcor_send_after" <?php checked( $send_when, 'wcor_send_after' ); ?>></th>
					<td><label for="wcor_send_after">after the camp ends: </label></td>
					<td>
						<input id="wcor_send_days_after" name="wcor_send_days_after" type="text" class="small-text" value="<?php echo esc_attr( $send_days_after ); ?>" />
						<label for="wcor_send_days_after">days</label>
					</td>
				</tr>

				<tr>
					<th><input id="wcor_send_after_pending" name="wcor_send_when" type="radio" value="wcor_send_after_pending" <?php checked( $send_when, 'wcor_send_after_pending' ); ?>></th>
					<td><label for="wcor_send_after_pending">after added to pending schedule: </label></td>
					<td>
						<input id="wcor_send_days_after_pending" name="wcor_send_days_after_pending" type="text" class="small-text" value="<?php echo esc_attr( $send_days_after_pending ); ?>" />
						<label for="wcor_send_days_after_pending">days</label>
					</td>
				</tr>

				<tr>
					<th><input id="wcor_send_trigger" name="wcor_send_when" type="radio" value="wcor_send_trigger" <?php checked( $send_when, 'wcor_send_trigger' ); ?>></th>
					<td><label for="wcor_send_trigger">on a trigger: </label></td>
					<td>
						<select name="wcor_which_trigger">
							<option value="null" <?php selected( $which_trigger, false ); ?>></option>

							<?php foreach ( $GLOBALS['WCOR_Mailer']->triggers as $trigger_id => $trigger ) : ?>
								<option value="<?php echo esc_attr( $trigger_id ); ?>" <?php selected( $which_trigger, $trigger_id ); ?>><?php echo esc_html( $trigger['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<h4>Available Placeholders:</h4>

		<h5>The WordCamp:</h5>

		<ul class="ul-disc">
			<li>[wordcamp_name]</li>
			<li>[wordcamp_start_date]</li>
			<li>[wordcamp_location]</li>
			<li>[wordcamp_url]</li>
			<li>[edit_wordcamp_url]</li>
			<li>[wordcamp_email]</li>
			<li>[wordcamp_twitter]</li>
			<li>[wordcamp_hashtag]</li>
			<li>[wordcamp_anticipated_attendees]</li>
			<li>[multi_event_sponsor_region]</li>
		</ul>

		<h5>The organizing team:</h5>
		<ul class="ul-disc">
			<li>[organizer_name]</li>
			<li>[lead_organizer_username]</li>
			<li>[lead_organizer_email]</li>
			<li>[lead_organizer_telephone]</li>
			<li>[organizer_address]</li>
			<li>[sponsor_wrangler_name]</li>
			<li>[sponsor_wrangler_email]</li>
			<li>[budget_wrangler_name]</li>
			<li>[budget_wrangler_email]</li>
			<li>[venue_wrangler_name]</li>
			<li>[venue_wrangler_email]</li>
			<li>[speaker_wrangler_name]</li>
			<li>[speaker_wrangler_email]</li>
			<li>[food_wrangler_name]</li>
			<li>[food_wrangler_email]</li>
			<li>[swag_wrangler_name]</li>
			<li>[swag_wrangler_email]</li>
			<li>[volunteer_wrangler_name]</li>
			<li>[volunteer_wrangler_email]</li>
			<li>[printing_wrangler_name]</li>
			<li>[printing_wrangler_email]</li>
			<li>[design_wrangler_name]</li>
			<li>[design_wrangler_email]</li>
			<li>[website_wrangler_name]</li>
			<li>[website_wrangler_email]</li>
			<li>[social_wrangler_name]</li>
			<li>[social_wrangler_email]</li>
			<li>[a_v_wrangler_name]</li>
			<li>[a_v_wrangler_email]</li>
			<li>[party_wrangler_name]</li>
			<li>[party_wrangler_email]</li>
			<li>[travel_wrangler_name]</li>
			<li>[travel_wrangler_email]</li>
			<li>[safety_wrangler_name]</li>
			<li>[safety_wrangler_email]</li>
		</ul>

		<h5>Venue</h5>
		<ul class="ul-disc">
			<li>[venue_name]</li>
			<li>[venue_address]</li>
			<li>[venue_max_capacity]</li>
			<li>[venue_available_rooms]</li>
			<li>[venue_url]</li>
			<li>[venue_contact_info]</li>
		</ul>

		<h5>Miscellaneous</h5>
		<ul class="ul-disc">
			<li>[multi_event_sponsor_info]</li>
		</ul>

		<?php
	}

	/**
	 * Builds the markup for the Manually Send metabox
	 *
	 * @param object $post
	 */
	public function markup_manually_send( $post ) {
		$wordcamps = $this->get_all_wordcamps();
		?>

		<p><?php _e( 'Check the box below and save the post to manually send this message to the assigned recipient(s), using the data from the selected WordCamp.', 'wordcamporg' ); ?></p>

		<p><?php _e( 'It will be sent immediately, regardless of when it is scheduled to be sent automatically, and regardless of whether or not it has already been sent automatically.', 'wordcamporg' ); ?></p>

		<p>
			<select name="wcor_manually_send_wordcamp">
				<option value="instructions"><?php _e( 'Select a WordCamp', 'wordcamporg' ); ?></option>
				<?php /* translators: label for a spacer <option> at the beginning of a <select> */ ?>
				<option value="spacer"><?php _e( '- - -', 'wordcamporg' ); ?></option>

				<?php foreach ( $wordcamps as $wordcamp ) : ?>
					<option value="<?php echo esc_attr( $wordcamp->ID ); ?>">
						[<?php echo esc_html( $wordcamp->meta['sort_column'] ); ?>]
						<?php echo esc_html( $wordcamp->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<input id="wcor_manually_send_checkbox" name="wcor_manually_send" type="checkbox">
			<label for="wcor_manually_send_checkbox"><?php _e( 'Manually send this e-mail', 'wordcamporg' ); ?></label>
		</p>

		<?php
	}

	/**
	 * Retrieve all WordCamps and their metadata, sorted by status and year.
	 *
	 * @return array
	 */
	protected function get_all_wordcamps() {
		if ( $wordcamps = get_transient( 'wcor_get_all_wordcamps' ) ) {
			return $wordcamps;
		}

		$statuses = WordCamp_Loader::get_post_statuses();
		$statuses = array_merge( array_keys( $statuses ), array( 'draft', 'pending', 'publish' ) );

		$wordcamps = get_posts( array(
			'post_type'   => WCPT_POST_TYPE_ID,
			'post_status' => $statuses,
			'numberposts' => -1,
		) );

		foreach ( $wordcamps as &$wordcamp ) {
			$wordcamp->meta                = get_post_custom( $wordcamp->ID );
			$wordcamp->meta['sort_column'] = empty( $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] ) ? $wordcamp->post_status : date( 'Y', $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] );
		}

		usort( $wordcamps, array( $this, 'usort_wordcamps_by_year_and_status' ) );

		set_transient( 'wcor_get_all_wordcamps', $wordcamps, HOUR_IN_SECONDS );

		return $wordcamps;
	}

	/**
	 * Sort WordCamps by year and post status.
	 *
	 * This is a usort() callback.
	 *
	 * WordCamps without a start date should be listed first, followed by WordCamps with a start date.
	 *
	 * Within the set that does not have a start date, they should be sorted by status; first drafts, then pending,
	 * then published. Those with the same status should be sorted alphabetically.
	 *
	 * Within the set that does have a start date, they should be sorted by year, descending. Those within the same
	 * year should be sorted alphabetically.
	 *
	 * Example:
	 *
	 * 1) draft   missing start date, titled "WordCamp Atlanta"
	 * 2) draft   missing start date, titled "WordCamp Chicago"
	 * 3) pending missing start date, titled "WordCamp Seattle"
	 * 4) publish missing start date, titled "WordCamp Portland"
	 * 5) publish in year 2014,       titled "WordCamp Dayton"
	 * 6) publish in year 2014,       titled "WordCamp San Francisco"
	 * 7) publish in year 2013,       titled "WordCamp Boston"
	 * 8) publish in year 2013,       titled "WordCamp Columbus"
	 *
	 * @param WP_Post $a
	 * @param WP_Post $b
	 *
	 * @return int
	 */
	protected function usort_wordcamps_by_year_and_status( $a, $b ) {
		$a_year = empty( $a->meta['Start Date (YYYY-mm-dd)'][0] ) ? false : date( 'Y', $a->meta['Start Date (YYYY-mm-dd)'][0] );
		$b_year = empty( $b->meta['Start Date (YYYY-mm-dd)'][0] ) ? false : date( 'Y', $b->meta['Start Date (YYYY-mm-dd)'][0] );

		$status_weights = array(
			'draft'   => 3,
			'pending' => 2,
			'publish' => 1,
		);

		if ( empty( $a_year ) || empty( $b_year ) ) {
			if ( $a->post_status == $b->post_status ) {
				return $a->post_title > $b->post_title;
			} else {
				return $status_weights[ $a->post_status ] < $status_weights[ $b->post_status ];
			}
		} else {
			if ( date( 'Y', $a->meta['Start Date (YYYY-mm-dd)'][0] ) == date( 'Y', $b->meta['Start Date (YYYY-mm-dd)'][0] ) ) {
				return $a->post_title > $b->post_title;
			} else {
				return $a_year < $b_year;
			}
		}
	}

	/**
	 * Checks to make sure the conditions for saving post meta are met
	 *
	 * @param int $post_id
	 * @param object $post
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
