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
	}

	/**
	 * Builds the markup for the Reminder Details metabox
	 *
	 * @param object $post
	 */
	public static function markup_reminder_details( $post ) {
		$send_where          = get_post_meta( $post->ID, 'wcor_send_where', true );
		$send_custom_address = get_post_meta( $post->ID, 'wcor_send_custom_address', true );
		$send_when           = get_post_meta( $post->ID, 'wcor_send_when', true );
		$send_days_before    = get_post_meta( $post->ID, 'wcor_send_days_before', true );
		$send_days_after     = get_post_meta( $post->ID, 'wcor_send_days_after', true );
		$which_trigger       = get_post_meta( $post->ID, 'wcor_which_trigger', true );
		
		?>

		<h4>Who should this e-mail be sent to?</h4>

		<table>
			<tbody>
				<tr>
					<th><input id="wcor_send_organizers" name="wcor_send_where" type="radio" value="wcor_send_organizers" <?php checked( $send_where, 'wcor_send_organizers' ); ?>></th>
					<td colspan="2"><label for="wcor_send_organizers">The organizing team</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_mes" name="wcor_send_where" type="radio" value="wcor_send_mes" <?php checked( $send_where, 'wcor_send_mes' ); ?>></th>
					<td colspan="2"><label for="wcor_send_mes">The WordCamp's Multi-Event Sponsors</label></td>
				</tr>

				<tr>
					<th><input id="wcor_send_custom" name="wcor_send_where" type="radio" value="wcor_send_custom" <?php checked( $send_where, 'wcor_send_custom' ); ?>></th>
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

		<?php
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
	}

	/**
	 * Saves the meta data for the reminder post
	 * 
	 * @param WP_Post $post
	 * @param array $new_meta
	 */
	protected function save_post_meta( $post, $new_meta ) {
		if ( isset( $new_meta['wcor_send_where'] ) ) {
			if ( in_array( $new_meta['wcor_send_where'], array( 'wcor_send_organizers', 'wcor_send_mes', 'wcor_send_custom' ) ) ) {
				update_post_meta( $post->ID, 'wcor_send_where', $new_meta['wcor_send_where'] );
			}
		}

		if ( isset( $new_meta['wcor_send_custom_address'] ) && is_email( $new_meta['wcor_send_custom_address'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_custom_address', sanitize_email( $new_meta['wcor_send_custom_address'] ) );
		}		
		
		if ( isset( $new_meta['wcor_send_when'] ) ) {
			if ( in_array( $new_meta['wcor_send_when'], array( 'wcor_send_before', 'wcor_send_after', 'wcor_send_trigger' ) ) ) {
				update_post_meta( $post->ID, 'wcor_send_when', $new_meta['wcor_send_when'] );
			}
		}

		if ( isset( $new_meta['wcor_send_days_before'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_days_before', absint( $new_meta['wcor_send_days_before'] ) );
		}

		if ( isset( $new_meta['wcor_send_days_after'] ) ) {
			update_post_meta( $post->ID, 'wcor_send_days_after', absint( $new_meta['wcor_send_days_after'] ) );
		}

		if ( isset( $new_meta['wcor_which_trigger'] ) ) {
			if ( in_array( $new_meta['wcor_which_trigger'], array_merge( array( 'null' ), array_keys( $GLOBALS['WCOR_Mailer']->triggers ) ) ) ) {
				update_post_meta( $post->ID, 'wcor_which_trigger', $new_meta['wcor_which_trigger'] );
			}
		}
	}
}
