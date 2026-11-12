<?php
/**
 * This adds custom roles for the HelpHub project.
 *
 * @package HelpHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpHub_Manager {

	/**
	 * The single instance of HelpHub_Custom_Roles.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null;

	/**
	 * Settings class object
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Custom roles Constructor.
	 *
	 * @param string $file    filename.
	 * @param string $version version.
	 */
	public function __construct( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token   = 'helphub_manager';

		$this->file = $file;
		$this->dir  = dirname( $this->file );

		$this->add_helphub_customrole();

		add_action( 'bbp_template_after_user_profile', array( $this, 'helphub_profile_section' ) );

		add_action( 'bbp_post_request', array( $this, 'helphub_profile_edits' ) );
	} // End __construct ()

	/**
	 * Main HelpHub_Manager Instance
	 *
	 * Ensures only one instance of HelpHub_Manager is loaded or can be loaded.
	 *
	 * @param string $file    Filename of site.
	 * @param string $version Version number.
	 * @since 1.0.0
	 * @static
	 * @see HelpHub_Custom_Roles()
	 * @return Main HelpHub_Manager instance
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Sorry, this is not allowed.', 'wporg-forums' ) ), esc_html( $this->_version ) );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Sorry, this is not allowed.', 'wporg-forums' ) ), esc_html( $this->_version ) );
	} // End __wakeup ()

	public function get_helphub_roles() {
		return array(
			'helphub_editor'  => esc_html__( 'HelpHub Editor', 'wporg-forums' ),
			'helphub_manager' => esc_html__( 'HelpHub Manager', 'wporg-forums' ),
		);
	}

	/**
	 * Output markup for various HelpHub managements in the user profile section of bbPress.
	 */
	public function helphub_profile_section() {
		/*
		 * Don't process anything if the user in question is lacking the proper capabilities.
		 *
		 * For our use, that means HelpHub Managers, anyone with higher access can use the appropriate edit screens.
		 */
		if ( ! current_user_can( 'manage_helphub' ) ) {
			return;
		}

		// Also don't allow editing your own user.
		if ( bbp_get_displayed_user_id() === get_current_user_id() ) {
			return;
		}

		$helphub_roles = $this->get_helphub_roles();

		// Get users current blog role.
		$user_role = bbp_get_user_blog_role( bbp_get_displayed_user_id() );

		/*
		 * Only allow changing roles of users that are HelpHub related, or do not already hold
		 * a role within the user hierarchy with sufficient capabilities to modify HelpHub articles.
		 *
		 * This is to prevent overriding users with higher capabilities altogether.
		 */
		if ( ! empty( $user_role ) && ! isset( $helphub_roles[ $user_role ] ) && user_can( bbp_get_displayed_user_id(), 'edit_posts' ) ) {
			return;
		}

		?>

		<div class="wporg-support-helphub">
			<h2 id="helphub" class="entry-title"><?php esc_html_e( 'HelpHub', 'wporg-forums' ); ?></h2>
			<div class="bbp-user-section">
				<form action="" method="post">
					<fieldset class="bbp-form">
						<label for="role"><?php esc_html_e( 'HelpHub Role', 'wporg-forums' ); ?></label>
						<select name="role" id="role">
							<option value=""><?php esc_html_e( '&mdash; No role for this site &mdash;', 'wporg-forums' ); ?></option>

							<?php foreach ( $helphub_roles as $role => $label ) : ?>

								<option <?php selected( $user_role, $role ); ?> value="<?php echo esc_attr( $role ); ?>"><?php echo $label; ?></option>

							<?php endforeach; ?>
						</select>
					</fieldset>

					<fieldset style="padding: 20px 20px 0" class="submit">
						<legend style="display:none;"><?php esc_html_e( 'Save Changes', 'wporg-forums' ); ?></legend>
						<div style="margin-bottom: 20px;float: left;width: 100%;clear: left;">
							<input type="hidden" name="action" id="helphub_post_action" value="helphub-update-user">
							<input type="hidden" name="user_id" id="user_id" value="<?php echo esc_attr( bbp_get_displayed_user_id() ); ?>">

							<?php wp_nonce_field( 'helphub-change-user-role-' . bbp_get_displayed_user_id(), '_helphub_manage' ); ?>

							<button style="float:right;" type="submit" class="button submit user-submit"><?php esc_html_e( 'Update User', 'wporg-forums' ); ?></button>
						</div>
					</fieldset>
				</form>
			</div>
		</div>

		<?php
	}

	/**
	 * Capture and perform any profile edits initiated by a HelpHub Manager.
	 */
	public function helphub_profile_edits() {
		// Don't process anything if the post actions are invalid.
		if ( ! isset( $_POST['action'] ) || 'helphub-update-user' !== $_POST['action'] ) {
			return;
		}

		// Get the displayed user ID.
		$user_id = bbp_get_displayed_user_id();

		// Ensure the proper user capabilities exist for changing user details.
		if ( ! current_user_can( 'manage_helphub' ) ) {
			return;
		}

		// Double-check that nobody is trying to edit their own user.
		if ( get_current_user_id() === $user_id ) {
			return;
		}

		// Check that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['_helphub_manage'], 'helphub-change-user-role-' . $user_id ) ) {
			return;
		}

		// Make sure the new role is a HelpHub one, or is being reset to nothing.
		$roles = $this->get_helphub_roles();
		if ( ! empty( $_POST['role'] ) && ! isset( $roles[ $_POST['role'] ] ) ) {
			return;
		}

		$user_forum_role = bbp_get_user_role( $user_id );

		$user = new stdClass();

		$user->ID   = (int) $user_id;
		$user->role = $_POST['role'];

		$edit_user = wp_update_user( $user );

		// Updating a user resets the forum role, so let's explicitly update that.
		bbp_set_user_role( $user_id, $user_forum_role );

		// Error(s) editng the user, so copy them into the global.
		if ( is_wp_error( $edit_user ) ) {
			bbpress()->errors = $edit_user;

			// Successful edit to redirect.
		} elseif ( is_integer( $edit_user ) ) {
			$redirect = add_query_arg( array( 'updated' => 'true' ), bbp_get_user_profile_url( $edit_user ) );

			wp_safe_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Adds a HelpHub custom role.
	 */
	public function add_helphub_customrole() {

		// Load users library.
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		get_editable_roles();
		$role = 'helphub_manager';

		// Check if the HelpHub Manager role is already added.
		global $wp_roles;
		$default_editorroles = $wp_roles->get_role( 'editor' );
		if ( empty( $GLOBALS['wp_roles']->is_role( $role ) ) ) {
			$wp_roles->add_role( $role, __( 'HelpHub Manager', 'wporg-forums' ), $default_editorroles->capabilities );

			$wp_roles->add_cap( $role, 'edit_theme_options' );
			$wp_roles->add_cap( $role, 'manage_helphub' );
		}
	}
}

/**
 * Returns the main instance of HelpHub_Manager to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object HelpHub_Custom_Roles
 */
function helphub_manager() {
	$instance = HelpHub_Manager::instance( __FILE__, '1.0.0' );
	return $instance;
}

helphub_manager();
