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
	 * wcpt_admin ()
	 *
	 * Initialize WCPT Admin
	 */
	function WordCamp_Admin () {
		$this->active_admin_notices = array();

		// Add some general styling to the admin area
		add_action( 'wcpt_admin_head',                                array( $this, 'admin_head' ) );

		// Forum column headers.
		add_filter( 'manage_' . WCPT_POST_TYPE_ID . '_posts_columns', array( $this, 'column_headers' ) );

		// Forum columns (in page row)
		add_action( 'manage_posts_custom_column',                     array( $this, 'column_data' ), 10, 2 );
		add_filter( 'post_row_actions',                               array( $this, 'post_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'admin_menu',                                     array( $this, 'metabox' ) );
		add_action( 'save_post',                                      array( $this, 'metabox_save' ) );

		// Scripts and CSS
		add_action( 'admin_enqueue_scripts',                          array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts',                            array( $this, 'admin_print_scripts' ), 99 );
		add_action( 'admin_print_styles',                             array( $this, 'admin_styles' ) );

		// Post status transitions
		add_action( 'transition_post_status',                         array( $this, 'trigger_schedule_actions' ), 10, 3 );
		add_action( 'wcpt_added_to_planning_schedule',                array( $this, 'add_organizer_to_central' ), 10 );
		add_action( 'wcpt_added_to_planning_schedule',                array( $this, 'mark_date_added_to_planning_schedule' ), 10 );
		add_filter( 'wp_insert_post_data',                            array( $this, 'enforce_post_status_progression' ), 10, 2 );
		add_filter( 'wp_insert_post_data',                            array( $this, 'require_complete_meta_to_publish_wordcamp' ), 10, 2 );

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
	}

	/**
	 * metabox_save ()
	 *
	 * Pass the metabox values before saving
	 *
	 * @param int $post_id
	 * @return int
	 */
	function metabox_save( $post_id ) {

		// Don't add/remove meta on revisions and auto-saves
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) )
			return;

		// WordCamp post type only
		if ( WCPT_POST_TYPE_ID == get_post_type() ) {
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
		}
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
					'Organizer Name'                  => 'text',
					'WordPress.org Username'          => 'text',
					'Email Address'                   => 'text',    // Note: This is the lead organizer's e-mail address, which is different than the "E-mail Address" field
					'Telephone'                       => 'text',
					'Mailing Address'                 => 'textarea',
					'Sponsor Wrangler Name'           => 'text',
					'Sponsor Wrangler E-mail Address' => 'text',
					'Budget Wrangler Name'            => 'text',
					'Budget Wrangler E-mail Address'  => 'text',
					'Mentor Name'                     => 'text',
					'Mentor E-mail Address'           => 'text',
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

					'Organizer Name'                  => 'text',
					'WordPress.org Username'          => 'text',
					'Email Address'                   => 'text',
					'Telephone'                       => 'text',
					'Mailing Address'                 => 'textarea',
					'Sponsor Wrangler Name'           => 'text',
					'Sponsor Wrangler E-mail Address' => 'text',
					'Budget Wrangler Name'            => 'text',
					'Budget Wrangler E-mail Address'  => 'text',
					'Mentor Name'                     => 'text',
					'Mentor E-mail Address'           => 'text',

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

	function admin_print_scripts() {
		if ( get_post_type() == WCPT_POST_TYPE_ID ) :
		?>

			<script>
				jQuery( document ).ready( function( $ ) {
					$( '.date-field' ).datepicker( {
						dateFormat: 'yy-mm-dd',
						changeMonth: true,
						changeYear:  true
					} );
				} );
			</script>

		<?php
		endif;
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
	 * When an application is submitted, a `wordcamp` post is created with a `draft` status. When it's accepted
	 * to the planning schedule the status changes to `pending`, and when it's accepted for the final schedule
	 * the status changes to 'publish'.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param WP_Post $post
	 */
	public function trigger_schedule_actions( $new_status, $old_status, $post ) {
		if ( empty( $post->post_type ) || WCPT_POST_TYPE_ID != $post->post_type ) {
			return;
		}

		if ( 'draft' == $old_status && 'pending' == $new_status ) {
			do_action( 'wcpt_added_to_planning_schedule', $post );
		} elseif ( 'pending' == $old_status && 'publish' == $new_status ) {
			do_action( 'wcpt_added_to_final_schedule', $post );
		}
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
		$lead_organizer = get_user_by( 'login', get_post_meta( $post->ID, 'WordPress.org Username', true ) );

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
	 * Force WordCamp posts to go through the expected status progression.
	 *
	 * They should start as drafts, then move to pending, and then be published. This is necessary because
	 * many automated processes (e.g., Organizer Reminder emails) are triggered when the post moves from
	 * one status to another, and deviations from the expected progression can cause bugs.
	 *
	 * Posts should still be allowed to move backwards in the progression, though.
	 *
	 * @param array $post_data
	 * @param array $post_data_raw
	 * @return array
	 */
	public function enforce_post_status_progression( $post_data, $post_data_raw ) {
		if ( WCPT_POST_TYPE_ID == $post_data['post_type'] && ! empty( $_POST ) ) {
			$previous_post_status = get_post( absint( $_POST['post_ID'] ) );
			$previous_post_status = $previous_post_status->post_status;

			if ( 'pending' == $post_data['post_status'] && ! in_array( $previous_post_status, array( 'draft', 'pending', 'publish' ) ) ) {
				$this->active_admin_notices[] = 2;
				$post_data['post_status'] = $previous_post_status;
			}

			if ( 'publish' == $post_data['post_status'] && ! in_array( $previous_post_status, array( 'pending', 'publish' ) ) ) {
				$this->active_admin_notices[] = 2;
				$post_data['post_status'] = $previous_post_status;
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
		// The ID of the last site that was created before this rule went into effect, so that we don't apply the rule retroactively.
		$min_site_id = apply_filters( 'wcpt_require_complete_meta_min_site_id', '2416297' );

		$required_pending_fields = array( 'E-mail Address' );

		$required_publish_fields = array(
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

		// Check pending posts
		if ( WCPT_POST_TYPE_ID == $post_data['post_type'] && 'pending' == $post_data['post_status'] && absint( $_POST['post_ID'] ) > $min_site_id ) {
			foreach( $required_pending_fields as $field ) {
				$value = $_POST[ wcpt_key_to_str( $field, 'wcpt_' ) ];

				if ( empty( $value ) || 'null' == $value ) {
					$post_data['post_status']     = 'draft';
					$this->active_admin_notices[] = 3;
					break;
				}
			}
		}

		// Check published posts
		if ( WCPT_POST_TYPE_ID == $post_data['post_type'] && 'publish' == $post_data['post_status'] && absint( $_POST['post_ID'] ) > $min_site_id ) {
			foreach( $required_publish_fields as $field ) {
				$value = $_POST[ wcpt_key_to_str( $field, 'wcpt_' ) ];

				if ( empty( $value ) || 'null' == $value ) {
					$post_data['post_status']     = 'pending';
					$this->active_admin_notices[] = 1;
					break;
				}
			}
		}

		return $post_data;
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
				'notice' => __( 'This WordCamp cannot be published until all of its required metadata is filled in.', 'wordcamporg' ),
			),

			2 => array(
				'type'   => 'error',
				'notice' => sprintf(
					__(
						'WordCamps must start as drafts, then be set as pending, and then be published. The post status has been reset to <strong>%s</strong>.',    // todo improve language
						'wordcamporg'
					),
					$post->post_status
				)
			),

			3 => array(
				'type'   => 'error',
				'notice' => __( 'This WordCamp cannot be set to pending until all of its required metadata is filled in.', 'wordcamporg' ),
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
				</p>

			<?php endif; ?>
		</div>

	<?php endforeach;
}
