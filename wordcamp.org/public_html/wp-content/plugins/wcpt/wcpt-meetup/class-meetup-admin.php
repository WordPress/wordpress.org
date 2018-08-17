<?php
/**
 * Implement Meetup Admin class
 *
 * @package WordCamp Post Type
 */

use \WordPress_Community\Applications\Meetup_Application;

require_once WCPT_DIR . 'wcpt-event/class-event-admin.php';

if ( ! class_exists( 'MeetupAdmin' ) ) :

	/**
	 * Implements Meetup Admin class
	 */
	class Meetup_Admin extends Event_Admin {

		/**
		 * Meetup_Admin constructor.
		 */
		public function __construct() {
			parent::__construct();

			add_action( 'wcpt_metabox_save_done', array( $this, 'save_tag_checkboxes' ) );
		}

		/**
		 * Return user facing label of event type.
		 *
		 * @return string
		 */
		public static function get_event_label() {
			return Meetup_Application::get_event_label();
		}

		/**
		 * Get post type of event
		 *
		 * @return string
		 */
		public static function get_event_type() {
			return Meetup_Application::get_event_type();
		}

		/**
		 * TODO: Add valid transition statuses.
		 *
		 * @param string $status Current status of the meetup.
		 *
		 * @return array Valid status transitions.
		 */
		public static function get_valid_status_transitions( $status ) {
			return array_keys( Meetup_Application::get_post_statuses() );
		}

		/**
		 * List of all valid post statuses for Meetup event
		 *
		 * @return array
		 */
		public static function get_post_statuses() {
			return Meetup_Application::get_post_statuses();
		}

		/**
		 * Name of capability required to edit the event
		 *
		 * @return string
		 */
		public static function get_edit_capability() {
			return 'can_wrangle_meetup';
		}

		/**
		 * Columns to display in admin list view
		 *
		 * @param array $columns
		 *
		 * @return array
		 */
		public function column_headers( $columns ) {
			$columns = array(
				'cb'             => '<input type="checkbox" />',
				'title'          => __( 'Title', 'wcpt' ),
				'tags'           => __( 'Tags', 'wcpt' ),
				'organizer'      => __( 'Organizer', 'wcpt' ),
				'date'           => __( 'Date', 'wcpt' ),
				'meetup.com_url' => __( 'Meetup URL', 'wcpt' ),
				'helpscout_url'  => __( 'HelpScout Link', 'wcpt' ),
			);

			return $columns;
		}

		/**
		 * TODO: Implement
		 *
		 * @param string $key Name of the field.
		 *
		 * @return bool Whether `$key` is a protected field.
		 */
		public static function is_protected_field( $key ) {
			return false;
		}

		/**
		 * Get rendered column data.
		 *
		 * @param string $column Name of the column.
		 * @param int    $post_id
		 */
		public function column_data( $column, $post_id ) {
			if ( empty( $_GET['post_type'] ) || $_GET['post_type'] !== $this->get_event_type() ) {
				return;
			}

			switch ( $column ) {
				case 'organizer':
					echo esc_html( get_post_meta( $post_id, 'Organizer Name', true ) . '<' . get_post_meta( $post_id, 'Email', true ) . '>' );
					break;
				case 'meetup.com_url':
					$this->print_clickable_link( get_post_meta( $post_id, 'Meetup URL', true ) );
					break;
				case 'helpscout_url':
					$this->print_clickable_link( get_post_meta( $post_id, 'HelpScout link', true ) );
					break;
			}
		}

		/**
		 * Helper function to wrap link HTML around a url.
		 *
		 * @param string $link
		 */
		protected function print_clickable_link( $link ) {
			?>
		<a href="<?php echo esc_attr( $link ); ?>" target="_blank">
			<?php echo esc_html( $link ); ?>
		</a>
			<?php
		}

		/**
		 * TODO: Remove quickedit action.
		 *
		 * @param $actions
		 * @param $post
		 *
		 * @return mixed
		 */
		public function post_row_actions( $actions, $post ) {
			return $actions;
		}

		/**
		 * Add metaboxes for meetup events.
		 */
		public function metabox() {

			add_meta_box(
				'wcpt_meetup_meetup_information',
				__( 'Meetup Information', 'wcpt' ),
				array( $this, 'wcpt_meetup_information_metabox' ),
				Meetup_Application::POST_TYPE,
				'advanced'
			);

			add_meta_box(
				'wcpt_meetup_application',
				__( 'Application Information', 'wcpt' ),
				array( $this, 'wcpt_application_metabox' ),
				Meetup_Application::POST_TYPE,
				'advanced'
			);

			add_meta_box(
				'wcpt_meetup_organizer_info',
				__( 'Organizer Information', 'wcpt' ),
				array( $this, 'wcpt_organizer_info_metabox' ),
				Meetup_Application::POST_TYPE,
				'advanced'
			);

			add_meta_box(
				'wcpt_meetup_swag',
				__( 'Swag Information', 'wcpt' ),
				array( $this, 'wcpt_swag_metabox' ),
				Meetup_Application::POST_TYPE,
				'advanced'
			);

			add_meta_box(
				'wcpt_show_tag_logs',
				'Tag logs',
				array( $this, 'wcpt_tag_log_metabox' ),
				$this->get_event_type(),
				'advanced',
				'low'
			);

		}

		/**
		 * Render information metabox group.
		 */
		public function wcpt_meetup_information_metabox() {
			$meta_keys = $this->meta_keys( 'information' );
			$this->meetup_metabox( $meta_keys );
		}

		/**
		 * Render application metabox group.
		 */
		public function wcpt_application_metabox() {
			$meta_keys = $this->meta_keys( 'application' );
			$this->meetup_metabox( $meta_keys );
		}

		/**
		 * Render organizer metabox group.
		 */
		public function wcpt_organizer_info_metabox() {
			$meta_keys = $this->meta_keys( 'organizer' );
			$this->meetup_metabox( $meta_keys );
		}

		/**
		 * Render swag metabox group.
		 */
		public function wcpt_swag_metabox() {
			$meta_keys = $this->meta_keys( 'swag' );
			$this->meetup_metabox( $meta_keys );
		}

		/**
		 * Render notes metabox group.
		 */
		public function wcpt_notes_metabox() {
			$meta_keys = $this->meta_keys( 'notes' );
			$this->meetup_metabox( $meta_keys );
		}

		/**
		 * Render logs metabox groups.
		 */
		public function wcpt_logs_metabox() {
			$meta_keys = $this->meta_keys( 'logs' );
			$this->meetup_metabox( $meta_keys );
		}

		/**
		 * Display metaboxes for the keys provided in the argument.
		 *
		 * @param array $meta_keys
		 */
		public function meetup_metabox( $meta_keys ) {
			global $post_id;

			self::display_meta_boxes( array(), $meta_keys, array(), $post_id, array() );
		}

		/**
		 * Renders tags logs
		 */
		public function wcpt_tag_log_metabox() {
			global $post_id;
			$entries = get_post_meta( $post_id, '_tags_log' );
			$entries = process_raw_entries( $entries, 'Tag changed' );
			require( WCPT_DIR . '/views/common/metabox-log.php' );
		}

		/**
		 * Meta keys group for Meetup Event.
		 *
		 * @param string $meta_group
		 *
		 * @return array
		 */
		public static function meta_keys( $meta_group = '' ) {

			$info_keys = array(
				'Meetup URL'      => 'text',
				'HelpScout link'  => 'text',
				'Meetup Location' => 'text',
			);

			$application_keys = array(
				'Date Applied'               => 'date',
				'Already a meetup'           => 'text',
				'Date of Last Contact'       => 'date',
				'Who contacted'              => 'text',
				'Vetted Date'                => 'date',
				'Vetted by'                  => 'text',
				'Orientation Date'           => 'date',
				'Oriented by'                => 'text',
				'Joined chapter date'        => 'date',
				'Joined chapter by'          => 'text',
				'More Information requested' => 'checkbox',
				'Changes Requested'          => 'checkbox',
				'Needs Meeting'              => 'checkbox'
			);

			$organizer_keys = array(
				'Organizer Name'                               => 'text',
				'Email'                                        => 'text',
				'Primary organizer WordPress.org username'     => 'text',
				'Co-Organizers usernames (seperated by comma)' => 'text',
				'Organizer description'                        => 'text',
				'Date closed'                                  => 'date',
				'Slack'                                        => 'text',
				'Region'                                       => 'text',
				'Address'                                      => 'textarea',
				'Extra Comments' => 'textarea',
			);

			$swag_keys = array(
				'Needs swag' => 'checkbox',
				'Swag notes' => 'text',
			);

			switch ( $meta_group ) {
				case 'information':
					$data = $info_keys;
					break;
				case 'application':
					$data = $application_keys;
					break;
				case 'organizer':
					$data = $organizer_keys;
					break;
				case 'swag':
					$data = $swag_keys;
					break;
				case 'all':
				default:
					$data = array_merge(
						$info_keys,
						$application_keys,
						$organizer_keys,
						$swag_keys
					);
			}
			return $data;
		}

		/**
		 * Toggle tags that are rendered as checkboxes
		 *
		 * @param int $post_id
		 */
		public function save_tag_checkboxes( $post_id ) {

			$post_tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

			$post_tags = $this->add_or_remove_tag( $post_id, $post_tags, 'wcpt_needs_swag', 'Needs Swag', $_POST['wcpt_swag_notes'] );

			$post_tags = $this->add_or_remove_tag( $post_id, $post_tags, 'wcpt_more_information_requested', 'More Information requested' );

			$post_tags = $this->add_or_remove_tag( $post_id, $post_tags, 'wcpt_changes_requested', 'Changes Requested' );

			$post_tags = $this->add_or_remove_tag( $post_id, $post_tags, 'wcpt_needs_meeting', 'Needs Meeting' );

			if ( $post_tags != wp_get_post_tags( $post_id ) ) {
				wp_set_post_tags( $post_id, $post_tags );
			}
		}

		/**
		 * Add or remove tag. Also logs and make a note when required
		 *
		 * @param $post_id
		 * @param $tag_array
		 * @param $input_name
		 * @param $label
		 * @param string $note_message
		 *
		 * @return array
		 */
		public function add_or_remove_tag( $post_id, $tag_array, $input_name, $label, $note_message = '' ) {

			if ( $note_message !== '' ) {
				$note_message = ' Note: ' . $note_message;
			}

			if ( isset( $_POST[ $input_name] ) && $_POST[ $input_name ] && ( ! in_array( $label, $tag_array ) ) ) {

				$tag_array[] = $label;

				add_post_meta(
					$post_id, '_tags_log', array(
						'timestamp' => time(),
						'user_id'   => get_current_user_id(),
						'message'   => 'Tag ' . $label . ' added.' . $note_message,
					)
				);

			} elseif ( ! isset( $_POST[ $input_name] ) && in_array( $label, $tag_array ) ) {
				$tag_array = array_diff( $tag_array, array( $label ) );

				add_post_meta(
					$post_id, '_tags_log', array(
						'timestamp' => time(),
						'user_id'   => get_current_user_id(),
						'message'   => 'Tag ' . $label . ' removed.' . $note_message,
					)
				);
			}

			return $tag_array;
		}
	}

endif;
