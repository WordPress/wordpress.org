<?php
/**
 * This file is part of the Helphub Post Types plugin
 *
 * @package WordPress
 */

/**
 * Main HelpHub_Post_Types Class
 *
 * @class   HelpHub_Post_Types
 * @version 1.0.0
 * @since   1.0.0
 * @package HelpHub_Post_Types
 * @author  Jon Ang
 */
final class HelpHub_Post_Types {
	/**
	 * HelpHub_Post_Types The single instance of HelpHub_Post_Types.
	 *
	 * @var    object
	 * @access private
	 * @since  1.0.0
	 */
	private static $_instance = null;

	/**
	 * The token.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $token;

	/**
	 * The version number.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $version;

	/**
	 * The plugin directory URL.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $plugin_url;

	/**
	 * The plugin directory path.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $plugin_path;

	/* Admin - Start */

	/**
	 * The admin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $admin;

	/**
	 * The settings object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings;

	/* Admin - End */

	/* Post Types - Start */

	/**
	 * The post types we're registering.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $post_types = array();

	/* Post Types - End */

	/* Taxonomies - Start */

	/**
	 * The taxonomies we're registering.
	 *
	 * @var     array
	 * @access  public
	 * @since   1.0.0
	 */
	public $taxonomies = array();

	/* Taxonomies - End */


	/**
	 * Constructor function.
	 *
	 * @access  public
	 * @since   1.0.0
	 */
	public function __construct() {
		$this->token       = 'helphub';
		$this->plugin_url  = plugin_dir_url( __DIR__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->version     = '1.0.0';

		/* Post Types - Start */

		require_once( dirname( __FILE__ ) . '/class-helphub-post-types-post-type.php' );
		require_once( dirname( __FILE__ ) . '/class-helphub-post-types-taxonomy.php' );

		$this->post_types['post']            = new HelpHub_Post_Types_Post_Type(
			'post',
			__( 'Post', 'wporg-forums' ),
			__( 'Posts', 'wporg-forums' ),
			array(
				'menu_icon' => 'dashicons-post',
			),
			array(),
			'post',
			'posts'
		);
		$this->post_types['helphub_article'] = new HelpHub_Post_Types_Post_Type(
			'helphub_article',
			__( 'Article', 'wporg-forums' ),
			__( 'Articles', 'wporg-forums' ),
			array(
				'menu_icon' => 'dashicons-media-document',
				'supports'  => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions', 'comments' ),
			),
			array(),
			'article',
			'articles'
		);
		$this->post_types['helphub_version'] = new HelpHub_Post_Types_Post_Type(
			'helphub_version',
			__( 'WordPress Version', 'wporg-forums' ),
			__( 'WordPress Versions', 'wporg-forums' ),
			array(
				'menu_icon' => 'dashicons-wordpress',
			),
			array(),
			'wordpress-version',
			'wordpress-versions'
		);

		/* Post Types - End */

		// Register an example taxonomy. To register more taxonomies, duplicate this line.
		$this->taxonomies['helphub_category']      = new HelpHub_Post_Types_Taxonomy( array( 'post', 'helphub_article' ), 'category', __( 'Category', 'wporg-forums' ), __( 'Categories', 'wporg-forums' ) );
		$this->taxonomies['helphub_major_release'] = new HelpHub_Post_Types_Taxonomy( 'helphub_version', 'helphub_major_release', __( 'Major Release', 'wporg-forums' ), __( 'Major Releases', 'wporg-forums' ) );

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'pre_get_posts', array( $this, 'fix_archive_category' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	} // End __construct()

	/**
	 * Main HelpHub_Post_Types Instance
	 *
	 * Ensures only one instance of HelpHub_Post_Types is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see HelpHub_Post_Types()
	 * @return HelpHub_Post_Types instance
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	} // End instance()

	/**
	 * Load the localisation file.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wporg-forums', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	} // End load_plugin_textdomain()

	/**
	 * Make sure category archive actually loads
	 *
	 * @access public
	 * @since 1.1.0
	 */
	public function fix_archive_category( WP_Query $query ) {
		if ( ! is_admin() && is_category() && $query->is_main_query() ) {
			$query->set( 'post_type', 'helphub_article' );
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	} // End fix_archive_category()

	/**
	 * Enqueue post type admin Styles.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_admin_styles() {
		global $pagenow;

		wp_enqueue_style( 'helphub-post-types-admin-style', $this->plugin_url . 'assets/css/admin.css', array(), '1.0.0' );

		if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) ) :
			if ( array_key_exists( get_post_type(), $this->post_types ) ) :
				wp_enqueue_script( 'helphub-post-types-admin', $this->plugin_url . 'assets/js/admin.js', array( 'jquery' ), '1.0.1', true );
				wp_enqueue_script( 'helphub-post-types-gallery', $this->plugin_url . 'assets/js/gallery.js', array( 'jquery' ), '1.0.0', true );
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_enqueue_style( 'jquery-ui-datepicker' );
			endif;
		endif;
		wp_localize_script(
			'helphub-post-types-admin', 'HelphubAdmin',
			array(
				'default_title'  => __( 'Upload', 'wporg-forums' ),
				'default_button' => __( 'Select this', 'wporg-forums' ),
			)
		);

		wp_localize_script(
			'helphub-post-types-gallery', 'HelphubGallery',
			array(
				'gallery_title'  => __( 'Add Images to Product Gallery', 'wporg-forums' ),
				'gallery_button' => __( 'Add to gallery', 'wporg-forums' ),
				'delete_image'   => __( 'Delete image', 'wporg-forums' ),
			)
		);

	} // End enqueue_admin_styles()

	/**
	 * Cloning is forbidden.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wporg-forums' ), '1.0.0' );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wporg-forums' ), '1.0.0' );
	} // End __wakeup()

	/**
	 * Installation. Runs on activation.
	 *
	 * @access  public
	 * @since   1.0.0
	 */
	public function install() {
		$this->_log_version_number();
	} // End install()

	/**
	 * Log the plugin version number.
	 *
	 * @access  private
	 * @since   1.0.0
	 */
	private function _log_version_number() {
		// Log the version number.
		update_option( $this->token . '-version', $this->version );
	} // End _log_version_number()
} // End Class
