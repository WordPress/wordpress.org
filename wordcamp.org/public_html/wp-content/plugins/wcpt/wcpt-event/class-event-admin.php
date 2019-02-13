<?php
/**
 * Implement Event_Admin class
 *
 * @package WordCamp Post Type
 */

/**
 * Class Event_Admin
 *
 * Abstract class providing common functions for event admins
 *
 * @package WordCamp Post Type
 * @subpackage Admin
 */
abstract class Event_Admin {

	public $active_admin_notices;

	/**
	 * Event_Admin constructor.
	 */
	public function __construct() {

		$this->active_admin_notices = array();

		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_status_metabox' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_log_metabox' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_note_metabox' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_original_application' ) );

		add_action( 'save_post_' . $this->get_event_type(), array( $this, 'metabox_save' ), 10, 2 );

		add_action( 'manage_posts_custom_column', array( $this, 'column_data' ), 10, 2 );

		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );

		add_filter(
			'manage_' . $this->get_event_type() . '_posts_columns',
			array(
				$this,
				'column_headers',
			)
		);
		// Forum column headers.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'common_admin_scripts' ) );

		add_action( 'admin_print_styles', array( $this, 'common_admin_styles' ) );

		add_action( 'transition_post_status', array( $this, 'log_status_changes' ), 10, 3 );

		add_action( 'transition_post_status', array( $this, 'notify_application_status_in_slack' ), 10, 3 );

		add_filter( 'redirect_post_location', array( $this, 'add_admin_notices_to_redirect_url' ), 10, 2 );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'print_admin_notices' ) );

		add_action( 'send_decline_notification_action',  'Event_Admin::send_decline_notification', 10, 3 );

	}

	/**
	 * Get all possible valid transitions from given status.
	 *
	 * @param string $status Name of status.
	 *
	 * @return array List of all valid transitions.
	 */
	abstract public static function get_valid_status_transitions( $status );

	/**
	 * List of all possible status.
	 *
	 * @return array
	 */
	abstract public static function get_post_statuses();

	/**
	 * Get post type
	 *
	 * @return string
	 */
	abstract public static function get_event_type();

	/**
	 * Get user facing event label
	 *
	 * @return string
	 */
	abstract public static function get_event_label();

	/**
	 * Get meta key group
	 *
	 * @param string $meta_group Name of meta group.
	 *
	 * @return array
	 */
	abstract public static function meta_keys( $meta_group = '' );

	/**
	 * Check if $key is a read-only field
	 *
	 * @param string $key Name of the field.
	 *
	 * @return bool
	 */
	abstract public static function is_protected_field( $key );

	/**
	 * Add metaboxes to admin view
	 */
	abstract public function metabox();

	/**
	 * Render column data for a given column
	 *
	 * @param string $column  Name of column.
	 * @param int    $post_id Post ID.
	 */
	abstract public function column_data( $column, $post_id );

	/**
	 * Change post row actions
	 *
	 * @param array   $actions Array of actions.
	 * @param WP_Post $post    Post object.
	 */
	abstract public function post_row_actions( $actions, $post );

	/**
	 * Render column headers
	 *
	 * @param array $columns List of columns.
	 */
	abstract public function column_headers( $columns );

	/**
	 * Add status metabox
	 */
	public function add_status_metabox() {
		add_meta_box(
			'submitdiv',
			__( 'Status', 'wordcamporg' ),
			array( $this, 'metabox_status' ),
			$this->get_event_type(),
			'side',
			'high'
		);

	}

	/**
	 * Add log meta box to application
	 */
	public function add_log_metabox() {
		if ( ! current_user_can( $this->get_edit_capability() ) ) {
			return;
		}

		if ( ! current_user_can( 'wordcamp_wrangle_wordcamps' ) ) {
			return;
		}

		add_meta_box(
			'wcpt_log',
			'Log',
			'wcpt_log_metabox',
			$this->get_event_type(),
			'advanced',
			'low'
		);

	}

	/**
	 * Add note metabox to application
	 */
	public function add_note_metabox() {
		if ( ! current_user_can( $this->get_edit_capability() ) ) {
			return;
		}

		if ( ! current_user_can( 'wordcamp_wrangle_wordcamps' ) ) {
			return;
		}

		add_meta_box(
			'wcpt_notes',
			__( 'Add Private Note', 'wordcamporg' ),
			'wcpt_add_note_metabox',
			$this->get_event_type(),
			'side',
			'low'
		);
	}

	/**
	 * Add original application metabox
	 */
	public function add_original_application() {
		if ( ! current_user_can( $this->get_edit_capability() ) ) {
			return;
		}

		add_meta_box(
			'wcpt_original_application',
			'Original Application',
			array( $this, 'original_application_metabox' ),
			$this->get_event_type(),
			'advanced',
			'low'
		);
	}

	/**
	 * Render the Original application
	 *
	 * @param WP_Post $post Current post.
	 */
	public function original_application_metabox( $post ) {
		$application_data = get_post_meta( $post->ID, '_application_data', true );
		require_once WCPT_DIR . 'views/wordcamp/metabox-original-application.php';
	}

	/**
	 * Render the WordCamp status meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function metabox_status( $post ) {
		require_once WCPT_DIR . 'views/wordcamp/metabox-status.php';

		render_event_metabox( $this, $post, $this->get_event_type(), $this->get_event_label(), $this->get_edit_capability() );
	}

	/**
	 * Gets name of capability required to edit the current event
	 *
	 * @return string
	 */
	abstract public static function get_edit_capability();

	/**
	 * Log when the post status changes
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Current Post.
	 */
	public function log_status_changes( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || 'auto-draft' === $new_status ) {
			return;
		}

		if ( empty( $post->post_type ) || $this->get_event_type() !== $post->post_type ) {
			return;
		}

		$old_status = get_post_status_object( $old_status );
		$new_status = get_post_status_object( $new_status );

		$log_id = add_post_meta(
			$post->ID,
			'_status_change',
			array(
				'timestamp' => time(),
				'user_id'   => get_current_user_id(),
				'message'   => sprintf( '%s &rarr; %s', $old_status->label, $new_status->label ),
			)
		);
		// Encoding $post_type and status_change meta ID in key so that we can fetch it if needed while simultaneously be able to have a where clause on value.
		// Because of the way MySQL works, it will still be able to use index on meta_key when searching, as long as we are querying just the prefix.
		if ( $log_id ) {
			add_post_meta( $post->ID, "_status_change_log_$post->post_type $log_id", time() );
		}
	}

	/**
	 * Hooked to `transition_post_status`, will send notifications to community slack channels based whenever an application status changes to something that we are interested in. Most likely would be when an application is declined or accepted.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old Status.
	 * @param WP_Post $event
	 */
	abstract public function notify_application_status_in_slack( $new_status, $old_status, WP_Post $event );

	/**
	 * Schedule notificaiton for declined application. Currently supports WordCamp and Meetup
	 *
	 * @param WP_Post $event Event object.
	 * @param string  $label Could be WordCamp or Meetup.
	 * @param string  $location
	 */
	public static function schedule_decline_notification( $event, $label, $location ) {
		wp_schedule_single_event( time() + DAY_IN_SECONDS, 'send_decline_notification_action', array( $event->ID, $label, $location ) );
	}

	/**
	 * Send declined notification to community team slack channel.
	 *
	 * @param int    $event_id
	 * @param string $label
	 * @param string $location
	 */
	public static function send_decline_notification( $event_id, $label, $location ) {
		$message = sprintf(
			'A %s application for %s has been declined, and the applicant has been informed via email.',
			$label,
			$location
		);

		$attachment = create_event_status_attachment( $message, $event_id, '' );
		wcpt_slack_notify( COMMUNITY_TEAM_SLACK, $attachment );
	}

	/**
	 * Load common admin side scripts
	 */
	public function common_admin_scripts() {

		if ( $this->get_event_type() !== get_post_type() ) {
			return;
		}

		wp_register_script(
			'wcpt-admin',
			WCPT_URL . 'javascript/wcpt-wordcamp/admin.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			WCPT_VERSION,
			true
		);

		$gutenberg_enabled = false;
		$current_screen = get_current_screen();
		if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
			$gutenberg_enabled = true;
		}

		wp_localize_script(
			'wcpt-admin',
			'wcpt_admin',
			array( 'gutenberg_enabled' => $gutenberg_enabled )
		);

		wp_enqueue_script( 'wcpt-admin' );
		wp_enqueue_script( 'select2' );

	}

	/**
	 * Load common admin styles
	 */
	public function common_admin_styles() {

		if ( $this->get_event_type() !== get_post_type() ) {
			return;
		}

		wp_register_style(
			'wcpt-admin',
			plugins_url( 'css/applications/admin.css', __DIR__ ),
			array(),
			WCPT_VERSION
		);

		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'wp-datepicker-skins' );
		wp_enqueue_style( 'select2' );
		wp_enqueue_style( 'wcpt-admin' );

	}

	/**
	 * Display the status of a WordCamp post
	 *
	 * @param array $states List of post states.
	 *
	 * @return array
	 */
	public function display_post_states( $states ) {
		global $post;

		if ( $post->post_type !== $this->get_event_type() ) {
			return $states;
		}

		$status = get_post_status_object( $post->post_status );
		if ( get_query_var( 'post_status' ) !== $post->post_status ) {
			$states[ $status->name ] = $status->label;
		}

		return $states;
	}

	/**
	 * Save metadata from form.
	 *
	 * @param int     $post_id      The ID of the post being saved.
	 * @param WP_Post $post         The post being saved.
	 * @param bool    $verify_nonce Whether or not to verify the nonce. Set to false when calling manually, leave
	 *                              true when calling via `save_post` hook.
	 *
	 * @hook save_post
	 */
	public function metabox_save( $post_id, $post, $verify_nonce = true ) {
		// Don't add/remove meta on revisions and auto-saves.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( $this->get_event_type() !== $post->post_type ) {
			return;
		}

		// Don't add/remove meta on trash, untrash, restore, etc.
		if ( empty( $_POST['action'] ) || 'editpost' !== $_POST['action'] ) {
			return;
		}

		// Make sure the request came from the edit post screen.
		if ( $verify_nonce ) {
			if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $post_id ) ) {
				wp_die( 'Unable to verify nonce.' );
			}
		}


		$meta_keys = $this->meta_keys();
		$orig_meta_values = get_post_meta( $post_id );

		foreach ( $meta_keys as $key => $value ) {
			$post_value     = wcpt_key_to_str( $key, 'wcpt_' );
			$values[ $key ] = isset( $_POST[ $post_value ] ) ? esc_attr( $_POST[ $post_value ] ) : '';

			// Don't update protected fields.
			if ( $this->is_protected_field( $key ) ) {
				continue;
			}

			switch ( $value ) {
				case 'text':
				case 'deputy_list':
				case 'textarea':
					update_post_meta( $post_id, $key, $values[ $key ] );
					break;

				case 'number':
					update_post_meta( $post_id, $key, floatval( $values[ $key ] ) );
					break;

				case 'checkbox':
					if ( ! empty( $values[ $key ] ) && 'on' == $values[ $key ] ) {
						update_post_meta( $post_id, $key, true );
					} else {
						update_post_meta( $post_id, $key, false );
					}
					break;

				case 'date':
					if ( ! empty( $values[ $key ] ) ) {
						$values[ $key ] = strtotime( $values[ $key ] );
					}

					update_post_meta( $post_id, $key, $values[ $key ] );
					break;

				case 'select-currency':
					$currencies = WordCamp_Budgets::get_currencies();
					$new_value  = ( array_key_exists( $values[ $key ], $currencies ) ) ? $values[ $key ] : '';

					update_post_meta( $post_id, $key, $new_value );
					break;

				default:
					do_action( 'wcpt_metabox_save', $key, $value, $post_id );
					break;
			}
		}

		// TODO: This should also pass $_POST params since nonce is verified here.
		do_action( 'wcpt_metabox_save_done', $post_id, $orig_meta_values );

		$this->validate_and_add_note( $post_id );

	}

	/**
	 * Add our custom admin notice keys to the redirect URL.
	 *
	 * Any member can add a key to $this->active_admin_notices to signify that the corresponding message should
	 * be shown when the redirect finished. When it does, print_admin_notices() will examine the URL and create
	 * a notice with the message that corresponds to the key.
	 *
	 * @param string $location
	 * @param int    $post_id
	 *
	 * @return string
	 */
	public function add_admin_notices_to_redirect_url( $location, $post_id ) {
		if ( $this->active_admin_notices ) {
			$location = add_query_arg( 'wcpt_messages', implode( ',', $this->active_admin_notices ), $location );
		}

		// Don't show conflicting messages like 'Post submitted'.
		if ( in_array( 1, $this->active_admin_notices ) && false !== strpos( $location, 'message=8' ) ) {
			$location = remove_query_arg( 'message', $location );
		}

		return $location;
	}

	/**
	 * Return admin notices for messages that were passed in the URL.
	 */
	abstract public function get_admin_notices();

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
		$screen = get_current_screen();

		if ( empty( $post->post_type ) || $this->get_event_type() != $post->post_type || 'post' !== $screen->base ) {
			return;
		}

		$notices = $this->get_admin_notices();

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
	 * Validate and add a new private note
	 *
	 * @param int $post_id
	 */
	protected function validate_and_add_note( $post_id ) {
		if ( empty( $_POST['wcpt_new_note'] ) ) {
			return;
		}

		check_admin_referer( 'wcpt_notes', 'wcpt_notes_nonce' );

		$new_note_message = sanitize_textarea_field( wp_unslash( $_POST['wcpt_new_note'] ) );

		if ( empty( $new_note_message ) ) {
			return;
		}

		// Note that this is private, see `wcpt_get_log_entries()`.
		add_post_meta(
			$post_id,
			'_note',
			array(
				'timestamp' => time(),
				'user_id'   => get_current_user_id(),
				'message'   => $new_note_message,
			)
		);
	}

	/**
	 * Displays meta boxes in admin view.
	 *
	 * @param array $required_fields Required fields.
	 * @param array $meta_keys List of meta keys.
	 * @param array $messages List of messages to displayed by a key.
	 * @param int   $post_id ID of post.
	 * @param array $protected_fields List of read only fields.
	 */
	public static function display_meta_boxes(
		$required_fields,
		$meta_keys,
		$messages,
		$post_id,
		$protected_fields
	) {

		foreach ( $meta_keys as $key => $value ) :
			$object_name = wcpt_key_to_str( $key, 'wcpt_' );
			$readonly    = in_array( $key, $protected_fields ) ? ' readonly="readonly"' : '';
			?>

			<div class="inside">
				<?php if ( 'checkbox' == $value ) : ?>

					<p>
						<strong><?php echo esc_html( $key ); ?></strong>:
						<input type="checkbox" name="<?php echo esc_attr( $object_name ); ?>"
							   id="<?php echo esc_attr( $object_name ); ?>" <?php checked( get_post_meta( $post_id, $key, true ) ); ?><?php echo esc_attr( $readonly ); ?> />
					</p>

				<?php else : ?>

					<p>
						<strong><?php echo esc_html( $key ); ?></strong>
						<?php if ( in_array( $key, $required_fields, true ) ) : ?>
							<span class="description"><?php esc_html_e( '(required)', 'wordcamporg' ); ?></span>
						<?php endif; ?>
					</p>

					<p>
						<label class="screen-reader-text"
							   for="<?php echo esc_attr( $object_name ); ?>"><?php echo esc_html( $key ); ?></label>

						<?php
						switch ( $value ) :
							case 'text':
								?>

								<input type="text" size="36" name="<?php echo esc_attr( $object_name ); ?>"
									   id="<?php echo esc_attr( $object_name ); ?>"
									   value="<?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?>"<?php echo esc_attr( $readonly ); ?> />

								<?php
								break;
							case 'number':
								?>

								<input type="number" size="16" name="<?php echo esc_attr( $object_name ); ?>"
									   id="<?php echo esc_attr( $object_name ); ?>"
									   value="<?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?>"
									   step="any" min="0"<?php echo esc_attr( $readonly ); ?> />

								<?php
								break;
							case 'date':
								// Quick filter on dates.
								$date = get_post_meta( $post_id, $key, true );
								if ( $date ) {
									$date = date( 'Y-m-d', $date );
								}

								?>

								<input type="text" size="36" class="date-field" name="<?php echo esc_attr( $object_name ); ?>"
									   id="<?php echo esc_attr( $object_name ); ?>"
									   value="<?php echo esc_attr( $date ); ?>"<?php echo esc_attr( $readonly ); ?> />

								<?php
								break;
							case 'textarea':
								?>

								<textarea rows="4" cols="23" name="<?php echo esc_attr( $object_name ); ?>"
										  id="<?php echo esc_attr( $object_name ); ?>"<?php echo esc_attr( $readonly ); ?>><?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?></textarea>

								<?php
								break;
							case 'select-currency':
								$currencies = WordCamp_Budgets::get_currencies();
								?>

								<?php
								if ( $readonly ) :
									$value = get_post_meta( $post_id, $key, true );
									?>
								<select name="<?php echo esc_attr( $object_name ); ?>"
										id="<?php echo esc_attr( $object_name ); ?>"<?php echo esc_attr( $readonly ); ?>>
									<option value="<?php echo esc_attr( $value ); ?>" selected>
										<?php echo ( $value ) ? esc_html( $currencies[ $value ] . ' (' . $value . ')' ) : ''; ?>
									</option>
								</select>
							<?php else : ?>
								<select name="<?php echo esc_attr( $object_name ); ?>"
										id="<?php echo esc_attr( $object_name ); ?>" class="select-currency">
									<?php foreach ( $currencies as $symbol => $name ) : ?>
										<option value="<?php echo esc_attr( $symbol ); ?>"<?php selected( $symbol, get_post_meta( $post_id, $key, true ) ); ?>>
											<?php echo ( $symbol ) ? esc_html( $name . ' (' . $symbol . ')' ) : ''; ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

								<?php
								break;

							case 'deputy_list':
								wp_dropdown_users(
									array(
										'role__in'         => array(
											'administrator',
											'editor',
										),
										'name'             => esc_attr( $object_name ),
										'id'               => esc_attr( $object_name ),
										'selected'         => get_post_meta( $post_id, $key, true ),
										'show_option_none' => 'None',
									)
								);
								break;
							default:
								do_action( 'wcpt_metabox_value', $key, $value, $object_name );
								break;

						endswitch;
						?>

						<?php if ( ! empty( $messages[ $key ] ) ) : ?>
							<?php
							if ( 'textarea' == $value ) {
								echo '<br />';
							}
							?>

							<span class="description"><?php echo esc_html( $messages[ $key ] ); ?></span>
						<?php endif; ?>
					</p>

				<?php endif; ?>
			</div>

			<?php
		endforeach;
	}


}
