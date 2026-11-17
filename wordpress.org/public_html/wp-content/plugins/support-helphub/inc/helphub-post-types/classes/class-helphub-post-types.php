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

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Delay post type and taxonomy registration until init to avoid loading translations too early.
		add_action( 'init', array( $this, 'register_post_types_and_taxonomies' ), 0 );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'pre_get_posts', array( $this, 'fix_archive_category' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
		add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_updated_messages' ), 10, 2 );
	} // End __construct()

	/**
	 * Register post types and taxonomies.
	 *
	 * Hooked to init to avoid loading translations too early.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function register_post_types_and_taxonomies() {
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
				'supports'  => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'page-attributes', 'revisions', 'comments' ),
				'labels'    => array(
					'name'                     => __( 'Articles', 'wporg-forums' ),
					'singular_name'            => __( 'Article', 'wporg-forums' ),
					'add_new'                  => __( 'Add Article', 'wporg-forums' ),
					'add_new_item'             => __( 'Add Article', 'wporg-forums' ),
					'edit_item'                => __( 'Edit Article', 'wporg-forums' ),
					'new_item'                 => __( 'New Article', 'wporg-forums' ),
					'view_item'                => __( 'View Article', 'wporg-forums' ),
					'view_items'               => __( 'View Articles', 'wporg-forums' ),
					'search_items'             => __( 'Search Articles', 'wporg-forums' ),
					'not_found'                => __( 'No articles found.', 'wporg-forums' ),
					'not_found_in_trash'       => __( 'No articles found in Trash.', 'wporg-forums' ),
					'all_items'                => __( 'All Articles', 'wporg-forums' ),
					'archives'                 => __( 'Article Archives', 'wporg-forums' ),
					'attributes'               => __( 'Article Attributes', 'wporg-forums' ),
					'insert_into_item'         => __( 'Insert into article', 'wporg-forums' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this article', 'wporg-forums' ),
					'filter_items_list'        => __( 'Filter articles list', 'wporg-forums' ),
					'items_list_navigation'    => __( 'Articles list navigation', 'wporg-forums' ),
					'items_list'               => __( 'Articles list', 'wporg-forums' ),
					'item_published'           => __( 'Article published.', 'wporg-forums' ),
					'item_published_privately' => __( 'Article published privately.', 'wporg-forums' ),
					'item_reverted_to_draft'   => __( 'Article reverted to draft.', 'wporg-forums' ),
					'item_trashed'             => __( 'Article trashed.', 'wporg-forums' ),
					'item_scheduled'           => __( 'Article scheduled.', 'wporg-forums' ),
					'item_updated'             => __( 'Article updated.', 'wporg-forums' ),
					'item_link'                => _x( 'Article Link', 'navigation link block title', 'wporg-forums' ),
					'item_link_description'    => _x( 'A link to an article.', 'navigation link block description', 'wporg-forums' ),
				),
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
				'labels'    => array(
					'name'                     => __( 'WordPress Versions', 'wporg-forums' ),
					'singular_name'            => __( 'WordPress Version', 'wporg-forums' ),
					'add_new'                  => __( 'Add WordPress Version', 'wporg-forums' ),
					'add_new_item'             => __( 'Add WordPress Version', 'wporg-forums' ),
					'edit_item'                => __( 'Edit WordPress Version', 'wporg-forums' ),
					'new_item'                 => __( 'New WordPress Version', 'wporg-forums' ),
					'view_item'                => __( 'View WordPress Version', 'wporg-forums' ),
					'view_items'               => __( 'View WordPress Versions', 'wporg-forums' ),
					'search_items'             => __( 'Search WordPress Versions', 'wporg-forums' ),
					'not_found'                => __( 'No WordPress versions found.', 'wporg-forums' ),
					'not_found_in_trash'       => __( 'No WordPress versions found in Trash.', 'wporg-forums' ),
					'all_items'                => __( 'All WordPress Versions', 'wporg-forums' ),
					'archives'                 => __( 'WordPress Version Archives', 'wporg-forums' ),
					'attributes'               => __( 'WordPress Version Attributes', 'wporg-forums' ),
					'insert_into_item'         => __( 'Insert into WordPress version', 'wporg-forums' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this WordPress version', 'wporg-forums' ),
					'filter_items_list'        => __( 'Filter WordPress versions list', 'wporg-forums' ),
					'items_list_navigation'    => __( 'WordPress versions list navigation', 'wporg-forums' ),
					'items_list'               => __( 'WordPress versions list', 'wporg-forums' ),
					'item_published'           => __( 'WordPress version published.', 'wporg-forums' ),
					'item_published_privately' => __( 'WordPress version published privately.', 'wporg-forums' ),
					'item_reverted_to_draft'   => __( 'WordPress version reverted to draft.', 'wporg-forums' ),
					'item_trashed'             => __( 'WordPress version trashed.', 'wporg-forums' ),
					'item_scheduled'           => __( 'WordPress version scheduled.', 'wporg-forums' ),
					'item_updated'             => __( 'WordPress version updated.', 'wporg-forums' ),
					'item_link'                => _x( 'WordPress Version Link', 'navigation link block title', 'wporg-forums' ),
					'item_link_description'    => _x( 'A link to a WordPress version.', 'navigation link block description', 'wporg-forums' ),
				),
			),
			array(),
			'wordpress-version',
			'wordpress-versions'
		);

		$this->taxonomies['helphub_category']      = new HelpHub_Post_Types_Taxonomy( array( 'post', 'helphub_article' ), 'category', __( 'Category', 'wporg-forums' ), __( 'Categories', 'wporg-forums' ) );
		$this->taxonomies['helphub_major_release'] = new HelpHub_Post_Types_Taxonomy(
			'helphub_version',
			'helphub_major_release',
			__( 'Major Release', 'wporg-forums' ),
			__( 'Major Releases', 'wporg-forums' ),
			array(
				'labels' => array(
					'name'                  => __( 'Major Releases', 'wporg-forums' ),
					'singular_name'         => __( 'Major Release', 'wporg-forums' ),
					'search_items'          => __( 'Search Major Releases', 'wporg-forums' ),
					'all_items'             => __( 'All Major Releases', 'wporg-forums' ),
					'parent_item'           => __( 'Parent Major Release', 'wporg-forums' ),
					'parent_item_colon'     => __( 'Parent Major Release:', 'wporg-forums' ),
					'edit_item'             => __( 'Edit Major Release', 'wporg-forums' ),
					'view_item'             => __( 'View Major Release', 'wporg-forums' ),
					'update_item'           => __( 'Update Major Release', 'wporg-forums' ),
					'add_new_item'          => __( 'Add Major Release', 'wporg-forums' ),
					'new_item_name'         => __( 'New Major Release Name', 'wporg-forums' ),
					'not_found'             => __( 'No major releases found.', 'wporg-forums' ),
					'no_terms'              => __( 'No major releases', 'wporg-forums' ),
					'filter_by_item'        => __( 'Filter by major release', 'wporg-forums' ),
					'items_list_navigation' => __( 'Major releases list navigation', 'wporg-forums' ),
					'items_list'            => __( 'Major releases list', 'wporg-forums' ),
					'back_to_items'         => __( '&larr; Go to Major Releases', 'wporg-forums' ),
					'item_link'             => _x( 'Major Release Link', 'navigation link block title', 'wporg-forums' ),
					'item_link_description' => _x( 'A link to a major release.', 'navigation link block description', 'wporg-forums' ),
				),
			)
		);
	}

	/**
	 * Set the admin notices shown after saving an article or a WordPress version.
	 *
	 * @access public
	 * @since  1.3.0
	 *
	 * @param  array $messages Post updated messages, keyed by post type.
	 * @return array Post updated messages.
	 */
	public function updated_messages( $messages ) {
		$post = get_post();

		if ( ! $post || ! in_array( $post->post_type, array( 'helphub_article', 'helphub_version' ), true ) ) {
			return $messages;
		}

		$permalink      = get_permalink( $post );
		$preview_url    = get_preview_post_link( $post );
		$scheduled_date = '<strong>' . date_i18n( __( 'M j, Y @ G:i', 'wporg-forums' ), strtotime( $post->post_date ) ) . '</strong>';
		$revision       = isset( $_GET['revision'] ) ? wp_post_revision_title( (int) $_GET['revision'], false ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$view_link      = ' <a href="' . esc_url( $permalink ) . '">%s</a>';
		$preview_link   = ' <a target="_blank" href="' . esc_url( $preview_url ) . '">%s</a>';
		$scheduled_link = ' <a target="_blank" href="' . esc_url( $permalink ) . '">%s</a>';

		if ( 'helphub_article' === $post->post_type ) {
			$view_article      = sprintf( $view_link, __( 'View article', 'wporg-forums' ) );
			$preview_article   = sprintf( $preview_link, __( 'Preview article', 'wporg-forums' ) );
			$scheduled_article = sprintf( $scheduled_link, __( 'Preview article', 'wporg-forums' ) );

			$messages['helphub_article'] = array(
				0  => '', // Unused. Messages start at index 1.
				1  => __( 'Article updated.', 'wporg-forums' ) . $view_article,
				2  => __( 'Custom field updated.', 'wporg-forums' ),
				3  => __( 'Custom field deleted.', 'wporg-forums' ),
				4  => __( 'Article updated.', 'wporg-forums' ),
				/* translators: %s: Date and time of the revision. */
				5  => $revision ? sprintf( __( 'Article restored to revision from %s.', 'wporg-forums' ), $revision ) : false,
				6  => __( 'Article published.', 'wporg-forums' ) . $view_article,
				7  => __( 'Article saved.', 'wporg-forums' ),
				8  => __( 'Article submitted.', 'wporg-forums' ) . $preview_article,
				/* translators: %s: Scheduled date for the article. */
				9  => sprintf( __( 'Article scheduled for: %s.', 'wporg-forums' ), $scheduled_date ) . $scheduled_article,
				10 => __( 'Article draft updated.', 'wporg-forums' ) . $preview_article,
			);
		} else {
			$view_version      = sprintf( $view_link, __( 'View WordPress version', 'wporg-forums' ) );
			$preview_version   = sprintf( $preview_link, __( 'Preview WordPress version', 'wporg-forums' ) );
			$scheduled_version = sprintf( $scheduled_link, __( 'Preview WordPress version', 'wporg-forums' ) );

			$messages['helphub_version'] = array(
				0  => '', // Unused. Messages start at index 1.
				1  => __( 'WordPress version updated.', 'wporg-forums' ) . $view_version,
				2  => __( 'Custom field updated.', 'wporg-forums' ),
				3  => __( 'Custom field deleted.', 'wporg-forums' ),
				4  => __( 'WordPress version updated.', 'wporg-forums' ),
				/* translators: %s: Date and time of the revision. */
				5  => $revision ? sprintf( __( 'WordPress version restored to revision from %s.', 'wporg-forums' ), $revision ) : false,
				6  => __( 'WordPress version published.', 'wporg-forums' ) . $view_version,
				7  => __( 'WordPress version saved.', 'wporg-forums' ),
				8  => __( 'WordPress version submitted.', 'wporg-forums' ) . $preview_version,
				/* translators: %s: Scheduled date for the WordPress version. */
				9  => sprintf( __( 'WordPress version scheduled for: %s.', 'wporg-forums' ), $scheduled_date ) . $scheduled_version,
				10 => __( 'WordPress version draft updated.', 'wporg-forums' ) . $preview_version,
			);
		}

		return $messages;
	} // End updated_messages()

	/**
	 * Set the admin notices shown after bulk actions on articles or WordPress versions.
	 *
	 * @access public
	 * @since  1.3.0
	 *
	 * @param  array $bulk_messages Bulk messages, keyed by post type.
	 * @param  array $bulk_counts   Item counts for each message.
	 * @return array Bulk messages.
	 */
	public function bulk_updated_messages( $bulk_messages, $bulk_counts ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return $bulk_messages;
		}

		if ( 'helphub_article' === $screen->post_type ) {
			$bulk_messages['helphub_article'] = array(
				/* translators: %s: Number of articles. */
				'updated'   => _n( '%s article updated.', '%s articles updated.', $bulk_counts['updated'], 'wporg-forums' ),
				'locked'    => ( 1 === $bulk_counts['locked'] ) ? __( '1 article not updated, somebody is editing it.', 'wporg-forums' ) :
								/* translators: %s: Number of articles. */
								_n( '%s article not updated, somebody is editing it.', '%s articles not updated, somebody is editing them.', $bulk_counts['locked'], 'wporg-forums' ),
				/* translators: %s: Number of articles. */
				'deleted'   => _n( '%s article permanently deleted.', '%s articles permanently deleted.', $bulk_counts['deleted'], 'wporg-forums' ),
				/* translators: %s: Number of articles. */
				'trashed'   => _n( '%s article moved to the Trash.', '%s articles moved to the Trash.', $bulk_counts['trashed'], 'wporg-forums' ),
				/* translators: %s: Number of articles. */
				'untrashed' => _n( '%s article restored from the Trash.', '%s articles restored from the Trash.', $bulk_counts['untrashed'], 'wporg-forums' ),
			);
		} elseif ( 'helphub_version' === $screen->post_type ) {
			$bulk_messages['helphub_version'] = array(
				/* translators: %s: Number of WordPress versions. */
				'updated'   => _n( '%s WordPress version updated.', '%s WordPress versions updated.', $bulk_counts['updated'], 'wporg-forums' ),
				'locked'    => ( 1 === $bulk_counts['locked'] ) ? __( '1 WordPress version not updated, somebody is editing it.', 'wporg-forums' ) :
								/* translators: %s: Number of WordPress versions. */
								_n( '%s WordPress version not updated, somebody is editing it.', '%s WordPress versions not updated, somebody is editing them.', $bulk_counts['locked'], 'wporg-forums' ),
				/* translators: %s: Number of WordPress versions. */
				'deleted'   => _n( '%s WordPress version permanently deleted.', '%s WordPress versions permanently deleted.', $bulk_counts['deleted'], 'wporg-forums' ),
				/* translators: %s: Number of WordPress versions. */
				'trashed'   => _n( '%s WordPress version moved to the Trash.', '%s WordPress versions moved to the Trash.', $bulk_counts['trashed'], 'wporg-forums' ),
				/* translators: %s: Number of WordPress versions. */
				'untrashed' => _n( '%s WordPress version restored from the Trash.', '%s WordPress versions restored from the Trash.', $bulk_counts['untrashed'], 'wporg-forums' ),
			);
		}

		return $bulk_messages;
	} // End bulk_updated_messages()

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
