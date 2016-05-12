<?php

if ( ! class_exists( 'WordCamp_Admin' ) ) :
/**
 * WCPT_Admin
 *
 * Loads plugin admin area
 *
 * @package WordCamp Post Type
 * @subpackage Admin
 * @since WordCamp Post Type (0.1)
 */
class WordCamp_Admin {
	protected $active_admin_notices;

	/**
	 * Initialize WCPT Admin
	 */
	function __construct() {
		$this->active_admin_notices = array();

		// Add some general styling to the admin area
		add_action( 'wcpt_admin_head',                                array( $this, 'admin_head' ) );

		// Forum column headers.
		add_filter( 'manage_' . WCPT_POST_TYPE_ID . '_posts_columns', array( $this, 'column_headers' ) );
		add_filter( 'display_post_states',                            array( $this, 'display_post_states' ) );

		// Forum columns (in page row)
		add_action( 'manage_posts_custom_column',                     array( $this, 'column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                               array( $this, 'post_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'add_meta_boxes',                                 array( $this, 'metabox' ) );
		add_action( 'save_post',                                      array( $this, 'metabox_save' ), 10, 2 );

		// Scripts and CSS
		add_action( 'admin_enqueue_scripts',                          array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts',                            array( $this, 'admin_print_scripts' ), 99 );
		add_action( 'admin_print_styles',                             array( $this, 'admin_styles' ) );

		// Post status transitions
		add_action( 'transition_post_status',                         array( $this, 'trigger_schedule_actions' ), 10, 3 );
		add_action( 'transition_post_status',                         array( $this, 'log_status_changes'       ), 10, 3 );
		add_action( 'wcpt_added_to_planning_schedule',                array( $this, 'add_organizer_to_central' ), 10 );
		add_action( 'wcpt_added_to_planning_schedule',                array( $this, 'mark_date_added_to_planning_schedule' ), 10 );
		add_filter( 'wp_insert_post_data',                            array( $this, 'enforce_post_status' ), 10, 2 );
		add_filter( 'wp_insert_post_data',                            array( $this, 'require_complete_meta_to_publish_wordcamp' ), 11, 2 ); // after enforce_post_status

		// Admin notices
		add_action( 'admin_notices',                                  array( $this, 'print_admin_notices' ) );
		add_filter( 'redirect_post_location',                         array( $this, 'add_admin_notices_to_redirect_url' ), 10, 2 );
	}

	/**
	 * metabox ()
	 *
	 * Add the metabox
	 *
	 * @uses add_meta_box
	 */
	function metabox() {
		add_meta_box(
			'wcpt_information',
			__( 'WordCamp Information', 'wcpt' ),
			'wcpt_wordcamp_metabox',
			WCPT_POST_TYPE_ID,
			'advanced',
			'high'
		);

		add_meta_box(
			'wcpt_organizer_info',
			__( 'Organizing Team', 'wcpt' ),
			'wcpt_organizer_metabox',
			WCPT_POST_TYPE_ID,
			'advanced',
			'high'
		);

		add_meta_box(
			'wcpt_venue_info',
			__( 'Venue Information', 'wcpt' ),
			'wcpt_venue_metabox',
			WCPT_POST_TYPE_ID,
			'advanced',
			'high'
		);

		add_meta_box(
			'wcpt_original_application',
			'Original Application',
			array( $this, 'original_application_metabox' ),
			WCPT_POST_TYPE_ID,
			'advanced',
			'low'
		);

		// Notes are private, so only show them to network admins
		if ( current_user_can( 'manage_network' ) ) {
			add_meta_box(
				'wcpt_notes',
				__( 'Add a Note', 'wordcamporg' ),
				'wcpt_add_note_metabox',
				WCPT_POST_TYPE_ID,
				'side',
				'low'
			);

			add_meta_box(
				'wcpt_log',
				'Log',
				'wcpt_log_metabox',
				WCPT_POST_TYPE_ID,
				'advanced',
				'low'
			);
		}

		// Remove core's submitdiv.
		remove_meta_box( 'submitdiv', WCPT_POST_TYPE_ID, 'side' );

		$statuses = WordCamp_Loader::get_post_statuses();

		add_meta_box(
			'submitdiv',
			__( 'Status', 'wordcamporg' ),
			array( $this, 'metabox_status' ),
			WCPT_POST_TYPE_ID,
			'side',
			'high'
		);
	}

	/**
	 * metabox_save ()
	 *
	 * Pass the metabox values before saving
	 *
	 * @param int $post_id
	 * @return int
	 */
	function metabox_save( $post_id, $post ) {
		// Don't add/remove meta on revisions and auto-saves
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) )
			return;

		// Don't add/remove meta on trash, untrash, restore, etc
		if ( empty( $_POST['action'] ) || 'editpost' != $_POST['action'] ) {
			return;
		}

		// WordCamp post type only
		if ( WCPT_POST_TYPE_ID != get_post_type() ) {
			return;
		}

		// Make sure the requset came from the edit post screen.
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $post_id ) ) {
			return;
		}

		// If the venue address was changed, update its coordinates
		$new_address = $_POST[ wcpt_key_to_str( 'Physical Address', 'wcpt_' ) ];
		if ( $new_address != get_post_meta( $post_id, 'Physical Address', true ) ) {
			if ( $coordinates = $this->geocode_address( $new_address ) ) {
				update_post_meta( $post_id, '_venue_coordinates', $coordinates );
			} else {
				delete_post_meta( $post_id, '_venue_coordinates' );
			}
		}

		// Post meta keys
		$wcpt_meta_keys = WordCamp_Admin::meta_keys();

		// Loop through meta keys and update
		foreach ( $wcpt_meta_keys as $key => $value ) {
			// Get post value
			$post_value   = wcpt_key_to_str( $key, 'wcpt_' );
			$values[ $key ] = isset( $_POST[ $post_value ] ) ? esc_attr( $_POST[ $post_value ] ) : '';

			switch ( $value ) {
				case 'text'     :
				case 'textarea' :
					update_post_meta( $post_id, $key, $values[ $key ] );
					break;

				case 'checkbox' :
					if ( ! empty( $values[ $key ] ) && 'on' == $values[ $key ] ) {
						update_post_meta( $post_id, $key, true );
					} else {
						update_post_meta( $post_id, $key, false );
					}
					break;

				case 'date' :
					if ( !empty( $values[ $key ] ) )
						$values[ $key ] = strtotime( $values[ $key ] );

					update_post_meta( $post_id, $key, $values[ $key ] );
					break;

				default:
					do_action( 'wcpt_metabox_save', $key, $value, $post_id );
					break;
			}
		}

		do_action( 'wcpt_metabox_save_done', $post_id );

		$this->validate_and_add_note( $post_id );
	}

	/**
	 * Validate and add a new note
	 *
	 * @param int $post_id
	 */
	protected function validate_and_add_note( $post_id ) {
		if ( empty( $_POST['wcpt_new_note' ] ) )
			return;

		check_admin_referer( 'wcpt_notes', 'wcpt_notes_nonce' );

		$new_note_message = sanitize_text_field( wp_unslash( $_POST['wcpt_new_note'] ) );

		if ( empty( $new_note_message ) ) {
			return;
		}

		add_post_meta( $post_id, '_note', array(
			'timestamp' => time(),
			'user_id'   => get_current_user_id(),
			'message'   => $new_note_message,
		) );
	}

	/**
	 * Geocode the given address into a latitude and longitude pair.
	 *
	 * @param string $address
	 *
	 * @return mixed
	 *      false if the geocode request failed
	 *      array with latitude and longitude indexes if the request succeeded
	 */
	function geocode_address( $address ) {
		if ( ! $address ) {
			return false;
		}

		$coordinates      = false;
		$request_url      = add_query_arg( 'address', urlencode( $address ), 'https://maps.googleapis.com/maps/api/geocode/json' );
		$geocode_response = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_url ) ) );

		if ( ! empty( $geocode_response->results[0]->geometry->location->lat ) ) {
			$coordinates = array(
				'latitude'  => $geocode_response->results[0]->geometry->location->lat,
				'longitude' => $geocode_response->results[0]->geometry->location->lng
			);
		}

		return $coordinates;
	}

	/**
	 * meta_keys ()
	 *
	 * Returns post meta key
	 *
	 * @return array
	 */
	function meta_keys( $meta_group = '' ) {
		/*
		 * Warning: These keys are used for both the input field label and the postmeta key, so if you want to
		 * modify an existing label then you'll also need to migrate any rows in the database to use the new key.
		 *
		 * Some of them are also exposed via the JSON API, so you'd need to build in a back-compat layer for that
		 * as well.
		 *
		 * When adding new keys, updating the wcorg-json-api plugin to either whitelist it, or test that it's not
		 * being exposed.
		 */

		switch ( $meta_group ) {
			case 'organizer':
				$retval = array (
					'Organizer Name'                                 => 'text',
					'WordPress.org Username'                         => 'text',
					'Email Address'                                  => 'text', // Note: This is the lead organizer's e-mail address, which is different than the "E-mail Address" field
					'Telephone'                                      => 'text',
					'Mailing Address'                                => 'textarea',
					'Sponsor Wrangler Name'                          => 'text',
					'Sponsor Wrangler E-mail Address'                => 'text',
					'Budget Wrangler Name'                           => 'text',
					'Budget Wrangler E-mail Address'                 => 'text',
					'Venue Wrangler Name'                            => 'text',
					'Venue Wrangler E-mail Address'                  => 'text',
					'Speaker Wrangler Name'                          => 'text',
					'Speaker Wrangler E-mail Address'                => 'text',
					'Food/Beverage Wrangler Name'                    => 'text',
					'Food/Beverage Wrangler E-mail Address'          => 'text',
					'Swag Wrangler Name'                             => 'text',
					'Swag Wrangler E-mail Address'                   => 'text',
					'Volunteer Wrangler Name'                        => 'text',
					'Volunteer Wrangler E-mail Address'              => 'text',
					'Printing Wrangler Name'                         => 'text',
					'Printing Wrangler E-mail Address'               => 'text',
					'Design Wrangler Name'                           => 'text',
					'Design Wrangler E-mail Address'                 => 'text',
					'Website Wrangler Name'                          => 'text',
					'Website Wrangler E-mail Address'                => 'text',
					'Social Media/Publicity Wrangler Name'           => 'text',
					'Social Media/Publicity Wrangler E-mail Address' => 'text',
					'A/V Wrangler Name'                              => 'text',
					'A/V Wrangler E-mail Address'                    => 'text',
					'Party Wrangler Name'                            => 'text',
					'Party Wrangler E-mail Address'                  => 'text',
					'Travel Wrangler Name'                           => 'text',
					'Travel Wrangler E-mail Address'                 => 'text',
					'Safety Wrangler Name'                           => 'text',
					'Safety Wrangler E-mail Address'                 => 'text',
					'Mentor Name'                                    => 'text',
					'Mentor E-mail Address'                          => 'text',
				);

				break;

			case 'venue':
				$retval = array (
					'Venue Name'                      => 'text',
					'Physical Address'                => 'textarea',
					'Maximum Capacity'                => 'text',
					'Available Rooms'                 => 'text',
					'Website URL'                     => 'text',
					'Contact Information'             => 'textarea',
					'Exhibition Space Available'      => 'checkbox',
				);
				break;

			case 'wordcamp':
				$retval = array (
					'Start Date (YYYY-mm-dd)'         => 'date',
					'End Date (YYYY-mm-dd)'           => 'date',
					'Location'                        => 'text',
					'URL'                             => 'wc-url',
					'E-mail Address'                  => 'text',    // Note: This is the address for the entire organizing team, which is different than the "Email Address" field
					'Twitter'                         => 'text',
					'WordCamp Hashtag'                => 'text',
					'Number of Anticipated Attendees' => 'text',
					'Multi-Event Sponsor Region'      => 'mes-dropdown',
				);
				break;

			case 'all':
			default:
				$retval = array(
					'Start Date (YYYY-mm-dd)'         => 'date',
					'End Date (YYYY-mm-dd)'           => 'date',
					'Location'                        => 'text',
					'URL'                             => 'wc-url',
					'E-mail Address'                  => 'text',
					'Twitter'                         => 'text',
					'WordCamp Hashtag'                => 'text',
					'Number of Anticipated Attendees' => 'text',
					'Multi-Event Sponsor Region'      => 'mes-dropdown',

					'Organizer Name'                                 => 'text',
					'WordPress.org Username'                         => 'text',
					'Email Address'                                  => 'text',
					'Telephone'                                      => 'text',
					'Mailing Address'                                => 'textarea',
					'Sponsor Wrangler Name'                          => 'text',
					'Sponsor Wrangler E-mail Address'                => 'text',
					'Budget Wrangler Name'                           => 'text',
					'Budget Wrangler E-mail Address'                 => 'text',
					'Venue Wrangler Name'                            => 'text',
					'Venue Wrangler E-mail Address'                  => 'text',
					'Speaker Wrangler Name'                          => 'text',
					'Speaker Wrangler E-mail Address'                => 'text',
					'Food/Beverage Wrangler Name'                    => 'text',
					'Food/Beverage Wrangler E-mail Address'          => 'text',
					'Swag Wrangler Name'                             => 'text',
					'Swag Wrangler E-mail Address'                   => 'text',
					'Volunteer Wrangler Name'                        => 'text',
					'Volunteer Wrangler E-mail Address'              => 'text',
					'Printing Wrangler Name'                         => 'text',
					'Printing Wrangler E-mail Address'               => 'text',
					'Design Wrangler Name'                           => 'text',
					'Design Wrangler E-mail Address'                 => 'text',
					'Website Wrangler Name'                          => 'text',
					'Website Wrangler E-mail Address'                => 'text',
					'Social Media/Publicity Wrangler Name'           => 'text',
					'Social Media/Publicity Wrangler E-mail Address' => 'text',
					'A/V Wrangler Name'                              => 'text',
					'A/V Wrangler E-mail Address'                    => 'text',
					'Party Wrangler Name'                            => 'text',
					'Party Wrangler E-mail Address'                  => 'text',
					'Travel Wrangler Name'                           => 'text',
					'Travel Wrangler E-mail Address'                 => 'text',
					'Safety Wrangler Name'                           => 'text',
					'Safety Wrangler E-mail Address'                 => 'text',
					'Mentor Name'                                    => 'text',
					'Mentor E-mail Address'                          => 'text',

					'Venue Name'                      => 'text',
					'Physical Address'                => 'textarea',
					'Maximum Capacity'                => 'text',
					'Available Rooms'                 => 'text',
					'Website URL'                     => 'text',
					'Contact Information'             => 'textarea',
					'Exhibition Space Available'      => 'checkbox',
				);
				break;

		}

		return apply_filters( 'wcpt_admin_meta_keys', $retval, $meta_group );
	}

	/**
	 * Fired during admin_print_styles
	 * Adds jQuery UI
	 */
	function admin_scripts() {
		if ( get_post_type() == WCPT_POST_TYPE_ID )
			wp_enqueue_script( 'jquery-ui-datepicker' );
	}

	/**
	 * Print our scripts for the Edit WordCamp screen
	 *
	 * If this file grows larger, it'd make sense to switch to using wp_enqueue_script().
	 */
	function admin_print_scripts() {
		if ( WCPT_POST_TYPE_ID !== get_post_type() ) {
			return;
		}

		?>

		<script>
			<?php require_once( dirname( __DIR__ ) . '/javascript/wcpt-wordcamp/admin.js' ); ?>
		</script>

		<?php
	}

	function admin_styles() {
		if ( get_post_type() == WCPT_POST_TYPE_ID ) {
			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_style( 'wp-datepicker-skins' );
		}
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head() {
		if ( !empty( $_GET['post_type'] ) && $_GET['post_type'] == WCPT_POST_TYPE_ID ) : ?>

			.column-title { width: 40%; }
			.column-wcpt_location, .column-wcpt_date, column-wcpt_organizer { white-space: nowrap; }

		<?php endif;
	}

	/**
	 * user_profile_update ()
	 *
	 * Responsible for showing additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_update( $user_id ) {
		if ( !wcpt_has_access() )
			return false;
	}

	/**
	 * user_profile_wordcamp ()
	 *
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_wordcamp( $profileuser ) {
		if ( !wcpt_has_access() )
			return false;
		?>

		<h3><?php _e( 'WordCamps', 'wcpt' ); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'WordCamps', 'wcpt' ); ?></th>

				<td>
				</td>
			</tr>
		</table>

		<?php
	}

	/**
	 * column_headers ()
	 *
	 * Manage the column headers
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function column_headers( $columns ) {
		$columns = array (
			'cb'               => '<input type="checkbox" />',
			'title'            => __( 'Title', 'wcpt' ),
			//'wcpt_location'    => __( 'Location', 'wcpt' ),
			'wcpt_date'        => __( 'Date',      'wcpt' ),
			'wcpt_organizer'   => __( 'Organizer', 'wcpt' ),
			'wcpt_venue'       => __( 'Venue',     'wcpt' ),
			'date'             => __( 'Status',    'wcpt' )
		);
		return $columns;
	}

	/**
	 * Display the status of a WordCamp post
	 *
	 * @param array $states
	 *
	 * @return array
	 */
	public function display_post_states( $states ) {
		global $post;

		if ( $post->post_type != WCPT_POST_TYPE_ID ) {
			return $states;
		}

		$status = get_post_status_object( $post->post_status );
		if ( get_query_var( 'post_status' ) != $post->post_status ) {
			$states[ $status->name ] = $status->label;
		}

		return $states;
	}

	/**
	 * column_data ( $column, $post_id )
	 *
	 * Print extra columns
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function column_data( $column, $post_id ) {
		if ( $_GET['post_type'] !== WCPT_POST_TYPE_ID )
			return $column;

		switch ( $column ) {
			case 'wcpt_location' :
				echo wcpt_get_wordcamp_location() ? wcpt_get_wordcamp_location() : __( 'No Location', 'wcpt' );
				break;

			case 'wcpt_date' :
				// Has a start date
				if ( $start = wcpt_get_wordcamp_start_date() ) {

					// Has an end date
					if ( $end = wcpt_get_wordcamp_end_date() ) {
						$string_date = sprintf( __( 'Start: %1$s<br />End: %2$s', 'wcpt' ), $start, $end );

					// No end date
					} else {
						$string_date = sprintf( __( 'Start: %1$s', 'wcpt' ), $start );
					}

				// No date
				} else {
					$string_date = __( 'No Date', 'wcpt' );
				}

				echo $string_date;
				break;

			case 'wcpt_organizer' :
				echo wcpt_get_wordcamp_organizer_name() ? wcpt_get_wordcamp_organizer_name() : __( 'No Organizer', 'wcpt' );
				break;

			case 'wcpt_venue' :
				echo wcpt_get_wordcamp_venue_name() ? wcpt_get_wordcamp_venue_name() : __( 'No Venue', 'wcpt' );
				break;
		}
	}

	/**
	 * post_row_actions ( $actions, $post )
	 *
	 * Remove the quick-edit action link and display the description under
	 *
	 * @param array $actions
	 * @param array $post
	 * @return array $actions
	 */
	function post_row_actions( $actions, $post ) {
		if ( WCPT_POST_TYPE_ID == $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			$wc = array();

			if ( $wc_location = wcpt_get_wordcamp_location() )
				$wc['location'] = $wc_location;

			if ( $wc_url = make_clickable( wcpt_get_wordcamp_url() ) )
				$wc['url'] = $wc_url;

			echo implode( ' - ', (array) $wc );
		}

		return $actions;
	}

	/**
	 * Trigger actions related to WordCamps being scheduled.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param WP_Post $post
	 */
	public function trigger_schedule_actions( $new_status, $old_status, $post ) {
		if ( empty( $post->post_type ) || WCPT_POST_TYPE_ID != $post->post_type ) {
			return;
		}

		if ( $new_status == $old_status ) {
			return;
		}

		if ( $old_status == 'wcpt-pre-planning' && $new_status == 'wcpt-pre-planning' ) {
			do_action( 'wcpt_added_to_planning_schedule', $post );
		} elseif ( $old_status == 'wcpt-needs-schedule' && $new_status == 'wcpt-scheduled' ) {
			do_action( 'wcpt_added_to_final_schedule', $post );
		}

		// back-compat for old statuses
		if ( 'draft' == $old_status && 'pending' == $new_status ) {
			do_action( 'wcpt_added_to_planning_schedule', $post );
		} elseif ( 'pending' == $old_status && 'publish' == $new_status ) {
			do_action( 'wcpt_added_to_final_schedule', $post );
		}

		// todo add new triggers - which ones?
	}

	/**
	 * Log when the post status changes
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function log_status_changes( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || $new_status == 'auto-draft' ) {
			return;
		}

		if ( empty( $post->post_type ) || WCPT_POST_TYPE_ID != $post->post_type ) {
			return;
		}

		$old_status = get_post_status_object( $old_status );
		$new_status = get_post_status_object( $new_status );

		add_post_meta( $post->ID, '_status_change', array(
			'timestamp' => time(),
			'user_id'   => get_current_user_id(),
			'message'   => sprintf( '%s &rarr; %s', $old_status->label, $new_status->label ),
		) );
	}

	/**
	 * Add the lead organizer to Central when a WordCamp application is accepted.
	 *
	 * Adding the lead organizer to Central allows them to enter all the `wordcamp`
	 * meta info themselves, and also post updates to the Central blog.
	 *
	 * @param WP_Post $post
	 */
	public function add_organizer_to_central( $post ) {
		$lead_organizer = get_user_by( 'login', $_POST['wcpt_wordpress_org_username'] );

		if ( $lead_organizer && add_user_to_blog( get_current_blog_id(), $lead_organizer->ID, 'contributor' ) ) {
			do_action( 'wcor_organizer_added_to_central', $post );
		}
	}

	/**
	 * Record when the WordCamp was added to the planning schedule.
	 *
	 * This is used by the Organizer Reminders plugin to send automated e-mails at certain points after the camp
	 * has been added to the planning schedule.
	 *
	 * @param WP_Post $wordcamp
	 */
	public function mark_date_added_to_planning_schedule( $wordcamp ) {
		update_post_meta( $wordcamp->ID, '_timestamp_added_to_planning_schedule', time() );
	}

	/**
	 * Enforce a valid post status for WordCamps.
	 *
	 * @param array $post_data
	 * @param array $post_data_raw
	 * @return array
	 */
	public function enforce_post_status( $post_data, $post_data_raw ) {
		if ( $post_data['post_type'] != WCPT_POST_TYPE_ID || empty( $_POST['post_ID'] ) ) {
			return $post_data;
		}

		$post = get_post( $_POST['post_ID'] );
		if ( ! $post ) {
			return $post_data;
		}

		if ( ! empty( $post_data['post_status'] ) ) {
			// Only network admins can change WordCamp statuses.
			if ( ! current_user_can( 'manage_network' ) ) {
				$post_data['post_status'] = $post->post_status;
			}

			// Enforce a valid status.
			$statuses = array_keys( WordCamp_Loader::get_post_statuses() );
			$statuses = array_merge( $statuses, array( 'trash' ) );

			if ( ! in_array( $post_data['post_status'], $statuses ) ) {
				$post_data['post_status'] = $statuses[0];
			}
		}

		return $post_data;
	}

	/**
	 * Prevent WordCamp posts from being set to pending or published until all the required fields are completed.
	 *
	 * @param array $post_data
	 * @param array $post_data_raw
	 * @return array
	 */
	public function require_complete_meta_to_publish_wordcamp( $post_data, $post_data_raw ) {
		if ( WCPT_POST_TYPE_ID != $post_data['post_type'] ) {
			return $post_data;
		}

		// The ID of the last site that was created before this rule went into effect, so that we don't apply the rule retroactively.
		$min_site_id = apply_filters( 'wcpt_require_complete_meta_min_site_id', '2416297' );

		$required_pre_planning_fields = $this->get_required_fields( 'pre-planning' );
		$required_scheduled_fields    = $this->get_required_fields( 'scheduled' );

		// Check pending posts
		if ( 'wcpt-approved-pre-pl' == $post_data['post_status'] && absint( $_POST['post_ID'] ) > $min_site_id ) {
			foreach( $required_pre_planning_fields as $field ) {
				$value = $_POST[ wcpt_key_to_str( $field, 'wcpt_' ) ];

				if ( empty( $value ) || 'null' == $value ) {
					$post_data['post_status']     = 'wcpt-interview-sched';
					$this->active_admin_notices[] = 1;
					break;
				}
			}
		}

		// Check published posts
		if ( 'wcpt-scheduled' == $post_data['post_status'] && isset( $_POST['post_ID'] ) && absint( $_POST['post_ID'] ) > $min_site_id ) {
			foreach( $required_scheduled_fields as $field ) {
				$value = $_POST[ wcpt_key_to_str( $field, 'wcpt_' ) ];

				if ( empty( $value ) || 'null' == $value ) {
					$post_data['post_status']     = 'wcpt-needs-schedule';
					$this->active_admin_notices[] = 3;
					break;
				}
			}
		}

		return $post_data;
	}

	/**
	 * Get a list of fields required to move to a certain post status
	 *
	 * @param string $status 'pre-planning' | 'scheduled' | 'any'
	 *
	 * @return array
	 */
	public static function get_required_fields( $status ) {
		$pre_planning = array( 'E-mail Address' );

		$scheduled = array(
			// WordCamp
			'Start Date (YYYY-mm-dd)',
			'Location',
			'URL',
			'E-mail Address',
			'Number of Anticipated Attendees',
			'Multi-Event Sponsor Region',

			// Organizing Team
			'Organizer Name',
			'WordPress.org Username',
			'Email Address',
			'Telephone',
			'Mailing Address',
			'Sponsor Wrangler Name',
			'Sponsor Wrangler E-mail Address',
			'Budget Wrangler Name',
			'Budget Wrangler E-mail Address',
		);

		switch ( $status ) {
			case 'pre-planning':
				$required_fields = $pre_planning;
				break;

			case 'scheduled':
				$required_fields = $scheduled;
				break;

			case 'any':
			default:
				$required_fields = array_merge( $pre_planning, $scheduled );
				break;
		}

		return $required_fields;
	}

	/**
	 * Add our custom admin notice keys to the redirect URL.
	 *
	 * Any member can add a key to $this->active_admin_notices to signify that the corresponding message should
	 * be shown when the redirect finished. When it does, print_admin_notices() will examine the URL and create
	 * a notice with the message that corresponds to the key.
	 *
	 * @param $location
	 * @param $post_id
	 * @return string
	 */
	public function add_admin_notices_to_redirect_url( $location, $post_id ) {
		if ( $this->active_admin_notices ) {
			$location = add_query_arg( 'wcpt_messages', implode( ',', $this->active_admin_notices ), $location );
		}

		// Don't show conflicting messages like 'Post submitted.'
		if ( in_array( 1, $this->active_admin_notices ) && false !== strpos( $location, 'message=8' ) ) {
			$location = remove_query_arg( 'message', $location );
		}

		return $location;
	}

	/**
	 * Create admin notices for messages that were passed in the URL.
	 *
	 * Any member can add a key to $this->active_admin_notices to signify that the corresponding message should
	 * be shown when the redirect finished. add_admin_notices_to_redirect_url() adds those keys to the redirect
	 * url, and this function examines the URL and create a notice with the message that corresponds to the key.
	 *
	 * $notices[key]['type'] should equal 'error' or 'updated'.
	 */
	public function print_admin_notices() {
		global $post;

		if ( empty( $post->post_type ) || WCPT_POST_TYPE_ID != $post->post_type ) {
			return;
		}

		$notices = array(
			1 => array(
				'type'   => 'error',
				'notice' => __( 'This WordCamp cannot be approved for pre-planning until all of its required metadata is filled in.', 'wordcamporg' ),
			),

			3 => array(
				'type'   => 'error',
				'notice' => __( 'This WordCamp cannot be added to the schedule until all of its required metadata is filled in.', 'wordcamporg' ),
			),
		);

		if ( ! empty( $_REQUEST['wcpt_messages'] ) ) {
			$active_notices = explode( ',', $_REQUEST['wcpt_messages'] );

			foreach ( $active_notices as $notice_key ) {
				if ( isset( $notices[ $notice_key ] ) ) {
					?>

					<div class="<?php echo esc_attr( $notices[ $notice_key ]['type'] ); ?>">
						<p><?php echo wp_kses( $notices[ $notice_key ]['notice'], wp_kses_allowed_html( 'post' ) ); ?></p>
					</div>

					<?php
				}
			}
		}
	}

	/**
	 * Render the WordCamp status meta box.
	 */
	public function metabox_status( $post ) {
		require_once( WCPT_DIR . 'views/wordcamp/metabox-status.php' );
	}

	/**
	 * Render the WordCamp status meta box.
	 */
	public function original_application_metabox( $post ) {
		$application_data = get_post_meta( $post->ID, '_application_data', true );
		require_once( WCPT_DIR . 'views/wordcamp/metabox-original-application.php' );
	}
}
endif; // class_exists check

/**
 * Functions for displaying specific meta boxes
 */
function wcpt_wordcamp_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'wordcamp' );
	wcpt_metabox( $meta_keys );
}

function wcpt_organizer_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'organizer' );
	wcpt_metabox( $meta_keys );
}

function wcpt_venue_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'venue' );
	wcpt_metabox( $meta_keys );
}

/**
 * wcpt_metabox ()
 *
 * The metabox that holds all of the additional information
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wcpt_metabox( $meta_keys ) {
	global $post_id;

	$required_fields = WordCamp_Admin::get_required_fields( 'any' );

	// @todo When you refactor meta_keys() to support changing labels -- see note in meta_keys() -- also make it support these notes
	$messages = array(
		'Telephone'       => 'Required for shipping.',
		'Mailing Address' => 'Shipping address.',
		'Physical Address' => 'Please include the city, state/province and country.', // So it can be geocoded correctly for the map
	);

	foreach ( $meta_keys as $key => $value ) :
		$object_name = wcpt_key_to_str( $key, 'wcpt_' );
	?>

		<div class="inside">
			<?php if ( 'checkbox' == $value ) : ?>

				<p>
					<strong><?php echo $key; ?></strong>:
					<input type="checkbox" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>" <?php checked( get_post_meta( $post_id, $key, true ) ); ?> />
				</p>

			<?php else : ?>

				<p>
					<strong><?php echo $key; ?></strong>
					<?php if ( in_array( $key, $required_fields, true ) ) : ?>
						<span class="description"><?php _e( '(required)', 'wordcamporg' ); ?></span>
					<?php endif; ?>
				</p>

				<p>
					<label class="screen-reader-text" for="<?php echo $object_name; ?>"><?php echo $key; ?></label>

					<?php switch ( $value ) :
						case 'text' : ?>

							<input type="text" size="36" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>" value="<?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?>" />

						<?php break;
						case 'date' :

							// Quick filter on dates
							if ( $date = get_post_meta( $post_id, $key, true ) ) {
								$date = date( 'Y-m-d', $date );
							}

							?>

							<input type="text" size="36" class="date-field" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>" value="<?php echo $date; ?>" />

						<?php break;
						case 'textarea' : ?>

							<textarea rows="4" cols="23" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>"><?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?></textarea>

						<?php break;

						default:
							do_action( 'wcpt_metabox_value', $key, $value, $object_name );
							break;

					endswitch; ?>

					<?php if ( ! empty( $messages[ $key ] ) ) : ?>
						<?php if ( 'textarea' == $value ) { echo '<br />'; } ?>

						<span class="description"><?php echo esc_html( $messages[ $key ] ); ?></span>
					<?php endif; ?>
				</p>

			<?php endif; ?>
		</div>

	<?php endforeach;
}
