<?php
/**
 * The main plugin class.
 *
 * @package HelpHub_Contributors
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * HelpHub Contributors Class
 *
 * Define internationalization, admin and frontend hooks.
 *
 * @since 1.0.0
 */
class HelpHub_Contributors {
	/**
	 * The single instance.
	 *
	 * @var    object
	 * @access private
	 * @since  1.0.0
	 */
	private static $_instance;

	/**
	 * Unique ID of plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string  $helphub_contributors Unique ID of plugin.
	 */
	private $helphub_contributors;

	/**
	 * The version of plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string  $version The current version of plugin.
	 */
	private $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->helphub_contributors = 'helphub-contributors';
		$this->version              = '1.0.0';
		add_action( 'init', array( $this, 'set_locale' ) );
		$this->required_dependencies();
		$this->admin_hooks();
		$this->frontend_hooks();
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	/**
	 * Define the locale for plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function set_locale() {
		load_plugin_textdomain(
			$this->get_helphub_contributors(), false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Required dependencies for plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function required_dependencies() {
		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-helphub-contributors-admin.php';
		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-helphub-contributors-public.php';
	}

	/**
	 * Register admin hooks.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function admin_hooks() {
		return new HelpHub_Contributors_Admin( $this->get_helphub_contributors(), $this->get_version() );
	}

	/**
	 * Register frontend hooks.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function frontend_hooks() {
		return new HelpHub_Contributors_Public( $this->get_helphub_contributors(), $this->get_version() );
	}

	/**
	 * The unique identifier of the plugin.
	 *
	 * @since  1.0.0
	 * @return string The name of the plugin.
	 */
	public function get_helphub_contributors() {
		return $this->helphub_contributors;
	}

	/**
	 * The version number of the plugin.
	 *
	 * @since  1.0.0
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Activation of the plugin.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function activate() {
		$this->update_version();
	}

	/**
	 * Update version number.
	 *
	 * @access private
	 * @since  1.0.0
	 */
	private function update_version() {
		update_option( $this->helphub_contributors . '-version', $this->version );
	}

	/**
	 * Single class instance
	 *
	 * @return HelpHub_Contributors instance
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}
