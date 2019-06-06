<?php

use WordCamp\Logger;
use WordCamp\Mentors_Dashboard;
use WordPress_Community\Applications\WordCamp_Application;

require_once WCPT_DIR . 'wcpt-event/class-event-admin.php';
require_once WCPT_DIR . 'wcpt-event/notification.php';

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
	class WordCamp_Admin extends Event_Admin {

		/**
		 * Initialize WCPT Admin
		 */
		public function __construct() {

			parent::__construct();

			// Add some general styling to the admin area.
			add_action( 'wcpt_admin_head', array( $this, 'admin_head' ) );

			// Scripts and CSS.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

			// Post status transitions.
			add_action( 'transition_post_status', array( $this, 'trigger_schedule_actions' ), 10, 3 );
			add_action( 'wcpt_approved_for_pre_planning', array( $this, 'add_organizer_to_central' ), 10 );
			add_action( 'wcpt_approved_for_pre_planning', array( $this, 'mark_date_added_to_planning_schedule' ), 10 );

			add_filter( 'wp_insert_post_data', array( $this, 'enforce_post_status' ), 10, 2 );

			add_filter(
				'wp_insert_post_data',
				array(
					$this,
					'require_complete_meta_to_publish_wordcamp',
				),
				11,
				2
			); // after enforce_post_status.

			// Cron jobs.
			add_action( 'plugins_loaded', array( $this, 'schedule_cron_jobs' ), 11 );
			add_action( 'wcpt_close_wordcamps_after_event', array( $this, 'close_wordcamps_after_event' ) );
			add_action( 'wcpt_metabox_save_done', array( $this, 'update_venue_address' ), 10, 2 );
			add_action( 'wcpt_metabox_save_done', array( $this, 'update_mentor' ) );
		}

		/**
		 * Add the metabox
		 *
		 * @uses add_meta_box
		 */
		public function metabox() {
			add_meta_box(
				'wcpt_information',
				__( 'WordCamp Information', 'wordcamporg' ),
				'wcpt_wordcamp_metabox',
				WCPT_POST_TYPE_ID,
				'advanced',
				'high'
			);

			add_meta_box(
				'wcpt_organizer_info',
				__( 'Organizing Team', 'wordcamporg' ),
				'wcpt_organizer_metabox',
				WCPT_POST_TYPE_ID,
				'advanced',
				'high'
			);

			add_meta_box(
				'wcpt_venue_info',
				__( 'Venue Information', 'wordcamporg' ),
				'wcpt_venue_metabox',
				WCPT_POST_TYPE_ID,
				'advanced',
				'high'
			);

			add_meta_box(
				'wcpt_contributor_info',
				__( 'Contributor Day Information', 'wordcamporg' ),
				'wcpt_contributor_metabox',
				WCPT_POST_TYPE_ID,
				'advanced'
			);

		}

		/**
		 * Get label for event type
		 *
		 * @return string
		 */
		public static function get_event_label() {
			return WordCamp_Application::get_event_label();
		}

		/**
		 * Get wordcamp post type
		 *
		 * @return string
		 */
		public static function get_event_type() {
			return WordCamp_Application::get_event_type();
		}

		/**
		 * Check if a field is readonly.
		 *
		 * @param string $key
		 *
		 * @return bool
		 */
		public function _is_protected_field( $key ) {
			return self::is_protected_field( $key );
		}

		/**
		 * Update mentor username.
		 *
		 * @param int $post_id
		 */
		public function update_mentor( $post_id ) {
			if ( $this->get_event_type() !== get_post_type() ) {
				return;
			}

			// If the Mentor username changed, update the site.
			//phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in `metabox_save` in class-event-admin.php.
			$mentor_username = $_POST[ wcpt_key_to_str( 'Mentor WordPress.org User Name', 'wcpt_' ) ];
			if ( get_post_meta( $post_id, 'Mentor WordPress.org User Name', true ) !== $mentor_username ) {
				$this->add_mentor( get_post( $post_id ), $mentor_username );
			}

		}

		/**
		 * Update venue address co-ords if changed
		 *
		 * These are used for the maps on Central, stats, etc.
		 *
		 * @param int   $post_id              Post id.
		 * @param array $original_meta_values Original meta values before save.
		 */
		public function update_venue_address( $post_id, $original_meta_values ) {
			if ( $this->get_event_type() !== get_post_type() ) {
				return;
			}

			// If the venue address was changed, update its coordinates.
			//phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in `metabox_save` in class-event-admin.php.
			$new_address = $_POST[ wcpt_key_to_str( 'Physical Address', 'wcpt_' ) ];
			if ( ! empty( $original_meta_values['Physical Address'][0] ) && $new_address === $original_meta_values['Physical Address'][0] ) {
				return;
			}

			$request_url = add_query_arg(
				array(
					'address' => rawurlencode( $new_address ),
				),
				'https://maps.googleapis.com/maps/api/geocode/json'
			);

			$key = apply_filters( 'wordcamp_google_maps_api_key', '', 'server' );

			if ( $key ) {
				$request_url = add_query_arg(
					array( 'key' => $key ),
					$request_url
				);
			}

			$response = wcorg_redundant_remote_get( $request_url );
			$body     = json_decode( wp_remote_retrieve_body( $response ) );

			// Don't delete the existing (and probably good) values if the request failed.
			if ( is_wp_error( $response ) || empty( $body->results[0]->address_components ) ) {
				Logger\log( 'geocoding_failure', compact( 'request_url', 'response' ) );
				return;
			}

			$meta_values = $this->parse_geocode_response( $response );

			foreach ( $meta_values as $key => $value ) {
				if ( is_null( $value ) ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			}

		}

		/**
		 * Parse the values we want out of the Geocode API response
		 *
		 * @see https://developers.google.com/maps/documentation/geocoding/intro#Types API response schema
		 *
		 * @param array $response
		 *
		 * @return array
		 */
		protected function parse_geocode_response( $response ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			$body = isset( $body->results[0] ) ? $body->results[0] : null;

			if ( isset( $body->geometry->location->lat ) ) {
				$coordinates = array(
					'latitude'  => $body->geometry->location->lat,
					'longitude' => $body->geometry->location->lng,
				);
			}

			if ( isset( $body->address_components ) ) {
				foreach ( $body->address_components as $component ) {
					foreach ( $component->types as $type ) {
						switch ( $type ) {

							case 'locality':
							case 'administrative_area_level_1':
							case 'postal_code':
								$$type = $component->long_name;
								break;

							case 'country':
								$country_code = $component->short_name; // This is not guaranteed to be ISO 3166-1 alpha-2, but should match in most cases.
								$country_name = $component->long_name;
								break;

						}
					}
				}
			}

			$values = array(
				'_venue_coordinates'  => isset( $coordinates ) ? $coordinates : null,
				'_venue_city'         => isset( $locality ) ? $locality : null,
				'_venue_state'        => isset( $administrative_area_level_1 ) ? $administrative_area_level_1 : null,
				'_venue_country_code' => isset( $country_code ) ? $country_code : null,
				'_venue_country_name' => isset( $country_name ) ? $country_name : null,
				'_venue_zip'          => isset( $postal_code ) ? $postal_code : null,
			);

			return $values;
		}

		/**
		 * Add the Mentor as an administrator on the given site.
		 *
		 * @param WP_Post $wordcamp        WordCamp post object.
		 * @param string  $mentor_username Mentor's WP.org user login.
		 */
		protected function add_mentor( $wordcamp, $mentor_username ) {
			$blog_id    = get_wordcamp_site_id( $wordcamp );
			$new_mentor = get_user_by( 'login', $mentor_username );

			if ( ! $blog_id || ! $new_mentor ) {
				return;
			}

			add_user_to_blog( $blog_id, $new_mentor->ID, 'administrator' );
		}

		/**
		 * Returns the names and types of post meta fields that have corresponding UI fields.
		 *
		 * For keys that don't have UI, see `get_venue_address_meta_keys()` and any similar functions.
		 *
		 * @param string $meta_group
		 *
		 * @return array
		 */
		public static function meta_keys( $meta_group = '' ) {
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
					$retval = array(
						'Organizer Name'                   => 'text',
						'WordPress.org Username'           => 'text',
						'Email Address'                    => 'text', // Note: This is the lead organizer's e-mail address, which is different than the "E-mail Address" field.
						'Telephone'                        => 'text',
						'Mailing Address'                  => 'textarea',
						'Sponsor Wrangler Name'            => 'text',
						'Sponsor Wrangler E-mail Address'  => 'text',
						'Budget Wrangler Name'             => 'text',
						'Budget Wrangler E-mail Address'   => 'text',
						'Venue Wrangler Name'              => 'text',
						'Venue Wrangler E-mail Address'    => 'text',
						'Speaker Wrangler Name'            => 'text',
						'Speaker Wrangler E-mail Address'  => 'text',
						'Food/Beverage Wrangler Name'      => 'text',
						'Food/Beverage Wrangler E-mail Address' => 'text',
						'Swag Wrangler Name'               => 'text',
						'Swag Wrangler E-mail Address'     => 'text',
						'Volunteer Wrangler Name'          => 'text',
						'Volunteer Wrangler E-mail Address' => 'text',
						'Printing Wrangler Name'           => 'text',
						'Printing Wrangler E-mail Address' => 'text',
						'Design Wrangler Name'             => 'text',
						'Design Wrangler E-mail Address'   => 'text',
						'Website Wrangler Name'            => 'text',
						'Website Wrangler E-mail Address'  => 'text',
						'Social Media/Publicity Wrangler Name' => 'text',
						'Social Media/Publicity Wrangler E-mail Address' => 'text',
						'A/V Wrangler Name'                => 'text',
						'A/V Wrangler E-mail Address'      => 'text',
						'Party Wrangler Name'              => 'text',
						'Party Wrangler E-mail Address'    => 'text',
						'Travel Wrangler Name'             => 'text',
						'Travel Wrangler E-mail Address'   => 'text',
						'Safety Wrangler Name'             => 'text',
						'Safety Wrangler E-mail Address'   => 'text',
						'Mentor WordPress.org User Name'   => 'text',
						'Mentor Name'                      => 'text',
						'Mentor E-mail Address'            => 'text',
					);

					break;

				case 'venue':
					$retval = array(
						'Venue Name'                 => 'text',
						'Physical Address'           => 'textarea',
						'Maximum Capacity'           => 'text',
						'Available Rooms'            => 'text',
						'Website URL'                => 'text',
						'Contact Information'        => 'textarea',
						'Exhibition Space Available' => 'checkbox',
					);
					break;

				case 'contributor':
					// These fields names need to be unique, hence the 'Contributor' prefix on each one.
					$retval = array(
						'Contributor Day'                => 'checkbox',
						'Contributor Day Date (YYYY-mm-dd)' => 'date',
						'Contributor Venue Name'         => 'text',
						'Contributor Venue Address'      => 'textarea',
						'Contributor Venue Capacity'     => 'text',
						'Contributor Venue Website URL'  => 'text',
						'Contributor Venue Contact Info' => 'textarea',
					);
					break;

				case 'wordcamp':
					$retval = array(
						'Start Date (YYYY-mm-dd)'           => 'date',
						'End Date (YYYY-mm-dd)'             => 'date',
						'Location'                          => 'text',
						'URL'                               => 'wc-url',
						'E-mail Address'                    => 'text',
						// Note: This is the address for the entire organizing team, which is different than the "Email Address" field.
						'Twitter'                           => 'text',
						'WordCamp Hashtag'                  => 'text',
						'Number of Anticipated Attendees'   => 'text',
						'Multi-Event Sponsor Region'        => 'mes-dropdown',
						'Global Sponsorship Grant Currency' => 'select-currency',
						'Global Sponsorship Grant Amount'   => 'number',
						'Global Sponsorship Grant'          => 'text',
						'Running money through WPCS PBC'    => 'checkbox',
					);
					break;

				case 'all':
				default:
					$retval = array(
						'Start Date (YYYY-mm-dd)'           => 'date',
						'End Date (YYYY-mm-dd)'             => 'date',
						'Location'                          => 'text',
						'URL'                               => 'wc-url',
						'E-mail Address'                    => 'text',
						'Twitter'                           => 'text',
						'WordCamp Hashtag'                  => 'text',
						'Number of Anticipated Attendees'   => 'text',
						'Multi-Event Sponsor Region'        => 'mes-dropdown',
						'Global Sponsorship Grant Currency' => 'select-currency',
						'Global Sponsorship Grant Amount'   => 'number',
						'Global Sponsorship Grant'          => 'text',
						'Running money through WPCS PBC'    => 'checkbox',

						'Organizer Name'                   => 'text',
						'WordPress.org Username'           => 'text',
						'Email Address'                    => 'text',
						'Telephone'                        => 'text',
						'Mailing Address'                  => 'textarea',
						'Sponsor Wrangler Name'            => 'text',
						'Sponsor Wrangler E-mail Address'  => 'text',
						'Budget Wrangler Name'             => 'text',
						'Budget Wrangler E-mail Address'   => 'text',
						'Venue Wrangler Name'              => 'text',
						'Venue Wrangler E-mail Address'    => 'text',
						'Speaker Wrangler Name'            => 'text',
						'Speaker Wrangler E-mail Address'  => 'text',
						'Food/Beverage Wrangler Name'      => 'text',
						'Food/Beverage Wrangler E-mail Address' => 'text',
						'Swag Wrangler Name'               => 'text',
						'Swag Wrangler E-mail Address'     => 'text',
						'Volunteer Wrangler Name'          => 'text',
						'Volunteer Wrangler E-mail Address' => 'text',
						'Printing Wrangler Name'           => 'text',
						'Printing Wrangler E-mail Address' => 'text',
						'Design Wrangler Name'             => 'text',
						'Design Wrangler E-mail Address'   => 'text',
						'Website Wrangler Name'            => 'text',
						'Website Wrangler E-mail Address'  => 'text',
						'Social Media/Publicity Wrangler Name' => 'text',
						'Social Media/Publicity Wrangler E-mail Address' => 'text',
						'A/V Wrangler Name'                => 'text',
						'A/V Wrangler E-mail Address'      => 'text',
						'Party Wrangler Name'              => 'text',
						'Party Wrangler E-mail Address'    => 'text',
						'Travel Wrangler Name'             => 'text',
						'Travel Wrangler E-mail Address'   => 'text',
						'Safety Wrangler Name'             => 'text',
						'Safety Wrangler E-mail Address'   => 'text',
						'Mentor WordPress.org User Name'   => 'text',
						'Mentor Name'                      => 'text',
						'Mentor E-mail Address'            => 'text',

						'Venue Name'                       => 'text',
						'Physical Address'                 => 'textarea',
						'Maximum Capacity'                 => 'text',
						'Available Rooms'                  => 'text',
						'Website URL'                      => 'text',
						'Contact Information'              => 'textarea',
						'Exhibition Space Available'       => 'checkbox',

						'Contributor Day'                  => 'checkbox',
						'Contributor Day Date (YYYY-mm-dd)' => 'date',
						'Contributor Venue Name'           => 'text',
						'Contributor Venue Address'        => 'textarea',
						'Contributor Venue Capacity'       => 'text',
						'Contributor Venue Website URL'    => 'text',
						'Contributor Venue Contact Info'   => 'textarea',
					);
					break;

			}

			return apply_filters( 'wcpt_admin_meta_keys', $retval, $meta_group );
		}

		/**
		 * Returns the slugs of the post meta fields for the venue's address.
		 *
		 * These aren't included in `meta_keys()` because they have no corresponding UI.
		 *
		 * @return array
		 */
		public static function get_venue_address_meta_keys() {
			return array(
				'_venue_coordinates',
				'_venue_city',
				'_venue_state',
				'_venue_country_code',
				'_venue_country_name',
				'_venue_zip',
			);
		}

		/**
		 * Fired during admin_print_styles
		 * Adds jQuery UI
		 */
		public function admin_scripts() {

			// Edit WordCamp screen.
			if ( WCPT_POST_TYPE_ID === get_post_type() ) {

				// Default data.
				$data = array(
					'Mentors' => array(
						'l10n' => array(
							'selectLabel' => esc_html__( 'Available mentors', 'wordcamporg' ),
							'confirm'     => esc_html__( 'Update Mentor field contents?', 'wordcamporg' ),
						),
					),
				);

				// Only include mentor data if the Mentor username field is editable.
				if ( current_user_can( 'wordcamp_manage_mentors' ) ) {
					$data['Mentors']['data'] = Mentors_Dashboard\get_all_mentor_data();
				}

				wp_localize_script(
					'wcpt-admin',
					'wordCampPostType',
					$data
				);
			}
		}

		/**
		 * Add some general styling to the admin area
		 */
		public function admin_head() {
			if ( ! empty( $_GET['post_type'] ) && WCPT_POST_TYPE_ID == $_GET['post_type'] ) : ?>

			.column-title { width: 40%; }
			.column-wcpt_location, .column-wcpt_date, column-wcpt_organizer { white-space: nowrap; }

				<?php
		endif;
		}

		/**
		 * Responsible for showing additional profile options and settings
		 *
		 * @todo Everything
		 */
		public function user_profile_update( $user_id ) {
			if ( ! wcpt_has_access() ) {
				return false;
			}
		}

		/**
		 * Responsible for saving additional profile options and settings
		 *
		 * @todo Everything
		 */
		public function user_profile_wordcamp( $profileuser ) {
			if ( ! wcpt_has_access() ) {
				return false;
			}
			?>

		<h3><?php esc_html_e( 'WordCamps', 'wordcamporg' ); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'WordCamps', 'wordcamporg' ); ?></th>

				<td>
				</td>
			</tr>
		</table>

			<?php
		}

		/**
		 * Manage the column headers
		 *
		 * @param array $columns
		 *
		 * @return array $columns
		 */
		public function column_headers( $columns ) {
			$columns = array(
				'cb'             => '<input type="checkbox" />',
				'title'          => __( 'Title',     'wordcamporg' ),
				// 'wcpt_location'    => __( 'Location', 'wordcamporg' ),
				'wcpt_date'      => __( 'Date',      'wordcamporg' ),
				'wcpt_organizer' => __( 'Organizer', 'wordcamporg' ),
				'wcpt_venue'     => __( 'Venue',     'wordcamporg' ),
				'date'           => __( 'Status',    'wordcamporg' ),
			);
			return $columns;
		}

		/**
		 * Print extra columns
		 *
		 * @param string $column
		 * @param int    $post_id
		 */
		public function column_data( $column, $post_id ) {
			if ( WCPT_POST_TYPE_ID !== $_GET['post_type'] ) {
				return $column;
			}

			switch ( $column ) {
				case 'wcpt_location':
					echo esc_html( wcpt_get_wordcamp_location() ? wcpt_get_wordcamp_location() : __( 'No Location', 'wordcamporg' ) );
					break;

				case 'wcpt_date':
					// Has a start date.
					$start = wcpt_get_wordcamp_start_date();
					if ( $start ) {

						// Has an end date.
						$end = wcpt_get_wordcamp_end_date();
						if ( $end ) {
							$string_date = sprintf( __( 'Start: %1$s<br />End: %2$s', 'wordcamporg' ), $start, $end );
							// No end date.
						} else {
							$string_date = sprintf( __( 'Start: %1$s', 'wordcamporg' ), $start );
						}

						// No date.
					} else {
						$string_date = __( 'No Date', 'wordcamporg' );
					}

					echo wp_kses( $string_date, array( 'br' => array() ) );
					break;

				case 'wcpt_organizer':
					echo esc_html( wcpt_get_wordcamp_organizer_name() ? wcpt_get_wordcamp_organizer_name() : __( 'No Organizer', 'wordcamporg' ) );
					break;

				case 'wcpt_venue':
					echo esc_html( wcpt_get_wordcamp_venue_name() ? wcpt_get_wordcamp_venue_name() : __( 'No Venue', 'wordcamporg' ) );
					break;
			}
		}

		/**
		 * Remove the quick-edit action link and display the description under
		 *
		 * @param array $actions
		 * @param array $post
		 * @return array $actions
		 */
		public function post_row_actions( $actions, $post ) {
			if ( WCPT_POST_TYPE_ID == $post->post_type ) {
				unset( $actions['inline hide-if-no-js'] );

				$wc = array();

				$wc_location = wcpt_get_wordcamp_location();
				if ( $wc_location ) {
					$wc['location'] = $wc_location;
				}

				$wc_url = make_clickable( wcpt_get_wordcamp_url() );
				if ( $wc_url ) {
					$wc['url'] = $wc_url;
				}

				echo wp_kses( implode( ' - ', (array) $wc ), wp_kses_allowed_html() );
			}

			return $actions;
		}

		/**
		 * Trigger actions related to WordCamps being scheduled.
		 *
		 * @param string  $new_status
		 * @param string  $old_status
		 * @param WP_Post $post
		 */
		public function trigger_schedule_actions( $new_status, $old_status, $post ) {
			if ( empty( $post->post_type ) || WCPT_POST_TYPE_ID != $post->post_type ) {
				return;
			}

			if ( $new_status == $old_status ) {
				return;
			}

			if ( 'wcpt-pre-planning' == $new_status ) {
				do_action( 'wcpt_approved_for_pre_planning', $post );
			} elseif ( 'wcpt-needs-schedule' == $old_status && 'wcpt-scheduled' == $new_status ) {
				do_action( 'wcpt_added_to_final_schedule', $post );
			}

			// todo add new triggers - which ones?
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

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordCamp status can be moved to pre-planning status only from the admin edit screen where nonce is already verified.
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
		 * Send notification to slack when a WordCamp is scheduled or declined. Runs whenever status of an applications changes
		 *
		 * @param string  $new_status
		 * @param string  $old_status
		 * @param WP_Post $wordcamp
		 *
		 * @return null|bool
		 */
		public function notify_application_status_in_slack( $new_status, $old_status, WP_Post $wordcamp ) {

			$notification_enabled = apply_filters( 'wordcamp_application_notification_enabled', true );

			if ( ! $notification_enabled ) {
				return null;
			}

			if ( 'wcpt-scheduled' === $new_status && 'wcpt-scheduled' !== $old_status ) {
				return $this->notify_new_wordcamp_in_slack( $wordcamp );
			} elseif ( 'wcpt-rejected' === $new_status && 'wcpt-rejected' !== $old_status ) {
				$location = get_post_meta( $wordcamp->ID, 'Location', true );
				return $this->schedule_decline_notification( $wordcamp, 'WordCamp', $location );
			}
		}

		/**
		 * Send notification when a new WordCamp comes in scheduled status.
		 *
		 * @param WP_Post $wordcamp
		 *
		 * @return null|bool|string
		 */
		public static function notify_new_wordcamp_in_slack( $wordcamp ) {
			$scheduled_notification_key = 'sent_scheduled_notification';
			if ( get_post_meta( $wordcamp->ID, $scheduled_notification_key, true ) ) {
				return null;
			}

			// Not translating any string because they will be sent to slack.
			$city             = get_post_meta( $wordcamp->ID, 'Location', true );
			$start_date       = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );
			$wordcamp_url     = get_post_meta( $wordcamp->ID, 'URL', true );
			$title            = 'New WordCamp scheduled!!!';

			$message = sprintf(
				"<%s|WordCamp $city> has been scheduled for a start date of %s. :tada: :community: :wordpress:\n\n%s",
				$wordcamp_url,
				date( 'F j, Y', $start_date ),
				$wordcamp_url
			);

			$attachment = create_event_status_attachment( $message, $wordcamp->ID, $title );

			$notification_sent = wcpt_slack_notify( COMMUNITY_EVENTS_SLACK, $attachment );
			if ( $notification_sent ) {
				update_post_meta( $wordcamp->ID, $scheduled_notification_key, true );
			}
			return $notification_sent;
		}

		/**
		 * Enforce a valid post status for WordCamps.
		 *
		 * @param array $post_data
		 * @param array $post_data_raw
		 * @return array
		 */
		public function enforce_post_status( $post_data, $post_data_raw ) {
			if ( WCPT_POST_TYPE_ID != $post_data['post_type'] || empty( $post_data_raw['ID'] ) ) {
				return $post_data;
			}

			$post = get_post( $post_data_raw['post_ID'] );
			if ( ! $post ) {
				return $post_data;
			}

			if ( ! empty( $post_data['post_status'] ) ) {
				$wcpt = get_post_type_object( WCPT_POST_TYPE_ID );

				// Only WordCamp Wranglers can change WordCamp statuses.
				if ( ! current_user_can( 'wordcamp_wrangle_wordcamps' ) ) {
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

			$required_needs_site_fields = $this->get_required_fields( 'needs-site' );
			$required_scheduled_fields  = $this->get_required_fields( 'scheduled' );

			// Check pending posts.
			if ( 'wcpt-needs-site' == $post_data['post_status'] && absint( $post_data_raw['ID'] ) > $min_site_id ) {
				foreach ( $required_needs_site_fields as $field ) {

					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce check would have done in `metabox_save`.
					$value = $_POST[ wcpt_key_to_str( $field, 'wcpt_' ) ];

					if ( empty( $value ) || 'null' == $value ) {
						$post_data['post_status']     = 'wcpt-needs-email';
						$this->active_admin_notices[] = 1;
						break;
					}
				}
			}

			// Check published posts.
			if ( 'wcpt-scheduled' == $post_data['post_status'] && isset( $post_data_raw['ID'] ) && absint( $post_data_raw['ID'] ) > $min_site_id ) {
				foreach ( $required_scheduled_fields as $field ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce check would have done in `metabox_save`.
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
		 * @param string $status 'needs-site' | 'scheduled' | 'any'.
		 *
		 * @return array
		 */
		public static function get_required_fields( $status ) {
			$needs_site = array( 'E-mail Address' );

			$scheduled = array(
				// WordCamp.
				'Start Date (YYYY-mm-dd)',
				'Location',
				'URL',
				'E-mail Address',
				'Number of Anticipated Attendees',
				'Multi-Event Sponsor Region',

				// Organizing Team.
				'Organizer Name',
				'WordPress.org Username',
				'Email Address',
				'Telephone',
				'Mailing Address',
				'Sponsor Wrangler Name',
				'Sponsor Wrangler E-mail Address',
				'Budget Wrangler Name',
				'Budget Wrangler E-mail Address',

				// Venue.
				'Physical Address', // used to build stats.
			);

			switch ( $status ) {
				case 'needs-site':
					$required_fields = $needs_site;
					break;

				case 'scheduled':
					$required_fields = $scheduled;
					break;

				case 'any':
				default:
					$required_fields = array_merge( $needs_site, $scheduled );
					break;
			}

			return $required_fields;
		}

		/**
		 * TODO: Add description.
		 *
		 * @return array
		 */
		public static function get_protected_fields() {
			$protected_fields = array();

			if ( ! current_user_can( 'wordcamp_manage_mentors' ) ) {
				$protected_fields = array_merge(
					$protected_fields,
					array(
						'Mentor WordPress.org User Name',
						'Mentor Name',
						'Mentor E-mail Address',
					)
				);
			}

			if ( ! current_user_can( 'wordcamp_wrangle_wordcamps' ) ) {
				$protected_fields = array_merge(
					$protected_fields,
					array(
						'Multi-Event Sponsor Region',
					)
				);
			}

			return $protected_fields;
		}

		/**
		 * Check if a field should be readonly, based on the current user's caps.
		 *
		 * @param string $field_name The field to check.
		 *
		 * @return bool
		 */
		public static function is_protected_field( $field_name ) {

			$protected_fields = self::get_protected_fields();

			return in_array( $field_name, $protected_fields );
		}

		/**
		 * Return admin notices for messages that were passed in the URL.
		 */
		public function get_admin_notices() {

			global $post;

			$screen = get_current_screen();

			if ( empty( $post->post_type ) || $this->get_event_type() != $post->post_type || 'post' !== $screen->base ) {
				return array();
			}

			// Show this error permanently, not just after updating.
			if ( ! empty( $post->{'Physical Address'} ) && empty( get_post_meta( $post->ID, '_venue_coordinates', true ) ) ) {
				$_REQUEST['wcpt_messages'] = empty( $_REQUEST['wcpt_messages'] ) ? '4' : $_REQUEST['wcpt_messages'] . ',4';
			}

			return array(
				1 => array(
					'type'   => 'error',
					'notice' => sprintf(
						__( 'This WordCamp cannot be moved to Needs Site until all of its required metadata is filled in: %s.', 'wordcamporg' ),
						implode( ', ', $this->get_required_fields( 'needs-site' ) )
					),
				),

				3 => array(
					'type'   => 'error',
					'notice' => sprintf(
						__( 'This WordCamp cannot be added to the schedule until all of its required metadata is filled in: %s.', 'wordcamporg' ),
						implode( ', ', $this->get_required_fields( 'scheduled' ) )
					),
				),

				4 => array(
					'type'   => 'error',
					'notice' => __( 'The physical address could not be geocoded, which prevents the camp from showing up in the Events Widget. Please tweak the address so that Google can parse it.', 'wordcamporg' ),
				),
			);

		}

		/**
		 * Get list of valid status transitions from given status
		 *
		 * @param string $status
		 *
		 * @return array
		 */
		public static function get_valid_status_transitions( $status ) {
			return WordCamp_Loader::get_valid_status_transitions( $status );
		}

		/**
		 * Get list of all available post statuses.
		 *
		 * @return array
		 */
		public static function get_post_statuses() {
			return WordCamp_Loader::get_post_statuses();
		}

		/**
		 * Capability required to edit wordcamp posts
		 *
		 * @return string
		 */
		public static function get_edit_capability() {
			return 'wordcamp_wrangle_wordcamps';
		}

		/**
		 * Schedule cron jobs
		 */
		public function schedule_cron_jobs() {
			if ( wp_next_scheduled( 'wcpt_close_wordcamps_after_event' ) ) {
				return;
			}

			wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'wcpt_close_wordcamps_after_event' );
		}

		/**
		 * Set WordCamp posts to the Closed status after the event is over
		 */
		public function close_wordcamps_after_event() {
			$scheduled_wordcamps = get_posts(
				array(
					'post_type'      => WCPT_POST_TYPE_ID,
					'post_status'    => 'wcpt-scheduled',
					'posts_per_page' => -1,
				)
			);

			foreach ( $scheduled_wordcamps as $wordcamp ) {
				$start_date = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );
				$end_date   = get_post_meta( $wordcamp->ID, 'End Date (YYYY-mm-dd)', true );

				if ( empty( $start_date ) ) {
					continue;
				}

				if ( empty( $end_date ) ) {
					$end_date = $start_date;
				}

				$end_date_at_midnight = strtotime( '23:59', $end_date );    // $end_date is the date at time 00:00, but the event isn't over until 23:59

				if ( $end_date_at_midnight > time() ) {
					continue;
				}

				wp_update_post(
					array(
						'ID'          => $wordcamp->ID,
						'post_status' => 'wcpt-closed',
					)
				);
			}
		}
	}
endif; // class_exists check.

/**
 * Functions for displaying specific meta boxes
 */
function wcpt_wordcamp_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'wordcamp' );
	wcpt_metabox( $meta_keys );
}

/**
 * Displays organizer metabox
 */
function wcpt_organizer_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'organizer' );
	wcpt_metabox( $meta_keys );
}

/**
 * Displays venue metabox
 */
function wcpt_venue_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'venue' );
	wcpt_metabox( $meta_keys );
}

/**
 * Displays contributor metabox
 */
function wcpt_contributor_metabox() {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'contributor' );
	wcpt_metabox( $meta_keys );
}

/**
 * The metabox that holds all of the additional information
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wcpt_metabox( $meta_keys ) {
	global $post_id;

	$required_fields = WordCamp_Admin::get_required_fields( 'any' );

	// @todo When you refactor meta_keys() to support changing labels -- see note in meta_keys() -- also make it support these notes.
	$messages = array(
		'Telephone'                       => 'Required for shipping.',
		'Mailing Address'                 => 'Shipping address.',
		'Physical Address'                => 'Please include the city, state/province and country.', // So it can be geocoded correctly for the map.
		'Global Sponsorship Grant Amount' => 'No commas, thousands separators or currency symbols. Ex. 1234.56',
		'Global Sponsorship Grant'        => 'Deprecated.',
	);

	Event_Admin::display_meta_boxes( $required_fields, $meta_keys, $messages, $post_id, WordCamp_Admin::get_protected_fields() );

}
