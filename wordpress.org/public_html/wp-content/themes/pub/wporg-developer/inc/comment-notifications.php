<?php
/**
 * Code Reference notifications for new comments and user contributed notes.
 *
 * @package wporg-developer
 */

/**
 * Class to handle notifications for new comments and user contributed notes.
 */
class DevHub_Global_Comment_Notifications {

	/**
	 * Meta key name for flag to indicate if user has opted into being notified of
	 * all new comments.
	 *
	 * @var array
	 * @access public
	 */
	public static $meta_key_name = 'devhub_comment_notification_optin';

	/**
	 * Value for the meta value to indicate the user has opted into comment notifications.
	 *
	 * @var string
	 * @access public
	 */
	public static $yes_meta_value = 'Y';

	/**
	 * Name for capability that permits user to subscribe to all comments.
	 *
	 * @var string
	 * @access public
	 */
	public static $cap_name = 'devhub_subscribe_to_all_comments';

	/**
	 * Initializer
	 *
	 * @access public
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Initialization
	 *
	 * @access public
	 */
	public static function do_init() {
		// Restrict ability to subscribe to comments.
		add_filter( 'user_has_cap',                    array( __CLASS__, 'assign_subscribe_caps' ) );

		// Add users who opted to be notified.
		add_filter( 'comment_notification_recipients', array( __CLASS__, 'add_comment_notification_recipients' ), 10, 2 );
		add_filter( 'comment_moderation_recipients',   array( __CLASS__, 'add_comment_notification_recipients' ), 10, 2 );

		// Adds the checkbox to user profiles.
		add_action( 'profile_personal_options',        array( __CLASS__, 'add_comment_notification_checkbox' ) );

		// Saves the user preference for comment notifications.
		add_action( 'personal_options_update',         array( __CLASS__, 'option_save' ) );
	}

	/**
	 * Adjusts capabilities for administrators and editors to allow subscribing to
	 * all comments.
	 *
	 * @access public
	 *
	 * @param  array $caps Array of user capabilities.
	 * @return array
	 */
	public static function assign_subscribe_caps( $caps ) {
		// Get current user's role.
		$role = wp_get_current_user()->roles[0];

		// Only administrators and editors can subscribe to all comments.
		if ( in_array( $role, array( 'administrator', 'editor' ) ) ) {
			$caps[ self::$cap_name ] = true;
		}

		return $caps;
	}

	/**
	 * Adds users who opted to be notified.
	 *
	 * @access public
	 *
	 * @param  array $emails     Array of email addresses to be notified.
	 * @param  int   $comment_id The comment ID for the comment just created.
	 * @return array
	 */
	public static function add_comment_notification_recipients( $emails, $comment_id ) {
		// Get users who opted in to comment notifications.
		$user_query = new WP_User_Query( array( 'meta_key' => self::$meta_key_name, 'meta_value' => self::$yes_meta_value ) );

		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				// Don't notify the current user about their own comment.
				if ( $user->ID == get_current_user_id() ) {
					continue;
				}

				// Add the email address unless it is already set to receive a notification.
				if ( ! in_array( $user->user_email, $emails ) ) {
					$emails[] = $user->user_email;
				}
			}
		}

		return $emails;
	}

	/**
	 * Adds the checkbox to user profiles to allow them to opt into receiving
	 * notifications for all comments.
	 *
	 * @access public
	 */
	public static function add_comment_notification_checkbox() {
		if ( ! current_user_can( self::$cap_name ) ) {
			return;
		}

		$checked = checked( wp_get_current_user()->{self::$meta_key_name}, 'Y', false );

		?>
		<table class="form-table">
		<tr>
			<th scope="row"><?php _e( 'New Comment Emails', 'wporg' ); ?></th>
			<td>
				<label for="<?php esc_attr_e( self::$meta_key_name ); ?>">
					<input name="<?php esc_attr_e( self::$meta_key_name ); ?>" type="checkbox" id="<?php esc_attr_e( self::$meta_key_name ); ?>" value="Y"<?php echo $checked; ?> />
					<?php _e( 'Email me whenever a comment is submitted to the site.', 'wporg' ); ?>
				</label>
			</td>
		</tr>
		</table>
		<?php
	}

	/**
	 * Saves value of checkbox to allow user to opt into receiving
	 * notifications for all comments.
	 *
	 * @access public
	 *
	 * @param  int  $user_id The user ID.
	 * @return bool True if the option saved successfully.
	 */
	public static function option_save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( self::$cap_name ) ) {
			return false;
		}

		if ( isset( $_POST[ self::$meta_key_name ] ) && self::$yes_meta_value === $_POST[ self::$meta_key_name ] ) {
			return update_usermeta( $user_id, self::$meta_key_name, self::$yes_meta_value );
		} else {
			return delete_usermeta( $user_id, self::$meta_key_name );
		}
	}
} // DevHub_Global_Comment_Notifications

DevHub_Global_Comment_Notifications::init();
