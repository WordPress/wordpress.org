<?php
/**
 * Plugin Name: Handbook
 * Description: Features for a handbook, complete with glossary and table of contents
 * Author: Nacin
 */

require_once dirname( __FILE__ ) . '/inc/callout-boxes.php';
require_once dirname( __FILE__ ) . '/inc/glossary.php';
require_once dirname( __FILE__ ) . '/inc/navigation.php';
require_once dirname( __FILE__ ) . '/inc/table-of-contents.php';
require_once dirname( __FILE__ ) . '/inc/template-tags.php';
require_once dirname( __FILE__ ) . '/inc/email-post-changes.php';
require_once dirname( __FILE__ ) . '/inc/watchlist.php';

WPorg_Handbook_Glossary::init();

/**
 * Initialize our handbooks
 *
 */
class WPorg_Handbook_Init {

	public static function get_post_types() {
		return (array) apply_filters( 'handbook_post_types', array( 'handbook' ) );
	}

	static function init() {

		$post_types = self::get_post_types();

		new WPorg_Handbook_TOC( $post_types );

		foreach ( $post_types as $type ) {
			new WPorg_Handbook( $type );
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	static public function enqueue_styles() {
		wp_enqueue_style( 'wporg-handbook-css', plugins_url( '/stylesheets/callout-boxes.css', __FILE__ ), array(), '20160715' );
	}

	static public function enqueue_scripts() {
		wp_enqueue_script( 'wporg-handbook', plugins_url( '/scripts/handbook.js', __FILE__ ), array( 'jquery' ), '20150930' );
	}

}

add_action( 'after_setup_theme', array( 'WPorg_Handbook_Init', 'init' ) );

class WPorg_Handbook {

	public $post_type = '';
	public $setting_name = '';

	protected $label = '';

	static function caps() {
		return array(
			'edit_handbook_pages', 'edit_others_handbook_pages',
			'edit_published_handbook_pages',
		);
	}

	static function editor_caps() {
		return array(
			'publish_handbook_pages',
			'delete_handbook_pages', 'delete_others_handbook_pages',
			'delete_published_handbook_pages', 'delete_private_handbook_pages',
			'edit_private_handbook_pages', 'read_private_handbook_pages',
		);
	}

	/**
	 * Returns the handbook name.
	 *
	 * If one isn't set via settings, one is generated.
	 *
	 * @param  string $post_type Optional. Handbook post type.
	 * @param  bool   $raw       Optional. Return only explicitly set name without attempting to generate default name?
	 * @return string
	 */
	static function get_name( $post_type = 'handbook', $raw = false ) {
		// Prefer explicitly configured handbook name.
		$name = get_option( $post_type . '_name' );

		// If handbook name isn't set, try root relative site path.
		if ( ! $raw && empty( $name ) ) {
			if ( is_multisite() ) {
				$name = trim( get_blog_details()->path, '/' );
			} else {
				$name = trim( parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
			}

			// If no name defined yet, try handbook post type if not standard.
			if ( empty( $name ) && ( 'handbook' != $post_type ) ) {
				$name = ucfirst( substr( $post_type, 0, -9 ) );
			}

			$name .= ' Handbook';
		}

		return trim( $name );
	}

	function __construct( $type ) {
		if ( 'handbook' != $type ) {
			$this->post_type = $type . '-handbook';
		} else {
			$this->post_type = $type;
		}

		$this->label = ucwords( str_replace( array( '-', '_' ), ' ', $this->post_type ) );

		$this->setting_name = $this->post_type . '_name';

		add_filter( 'user_has_cap',                       array( $this, 'grant_handbook_caps' ) );
		add_action( 'widgets_init',                       array( $this, 'register_post_type' ) );
		add_filter( 'post_type_link',                     array( $this, 'post_type_link' ), 10, 2 );
		add_action( 'template_redirect',                  array( $this, 'redirect_handbook_root_page' ) );
		add_filter( 'template_include',                   array( $this, 'template_include' ) );
		add_filter( 'query_vars',                         array( $this, 'add_query_vars' ) );
		add_filter( 'pre_get_posts',                      array( $this, 'pre_get_posts' ) );
		add_action( 'widgets_init',                       array( $this, 'handbook_sidebar' ), 11 ); // After P2
		add_action( 'wporg_email_changes_for_post_types', array( $this, 'wporg_email_changes_for_post_types' ) );
		add_action( 'p2_action_links',                    array( $this, 'disable_p2_resolved_posts_action_links' ) );
		add_action( 'admin_init',                         array( $this, 'add_name_setting' ) );
		add_filter( 'body_class',                         array( $this, 'add_body_class' ) );
		add_filter( 'post_class',                         array( $this, 'add_post_class' ) );
	}

	/**
	 * Adds custom handbook-related classes to body tag.
	 *
	 * * Adds 'single-handbook' class for any handbook page.
	 * * Adds 'handbook-landing-page' class for page acting as a handbook landing
	 *   page.
	 *
	 * @param array $classes Array of body classes.
	 * @return array
	 */
	function add_body_class( $classes ) {
		if ( wporg_is_handbook( $this->post_type ) ) {
			$classes[] = 'single-handbook';
		}

		if ( wporg_is_handbook_landing_page() ) {
			$classes[] = 'handbook-landing-page';
		}

		return $classes;
	}

	/**
	 * Adds 'type-handbook' class to the list of post classes to a handbook post
	 * when appropriate.
	 *
	 * @param array $classes Array of post classes.
	 * @return array
	 */
	function add_post_class( $classes ) {
		if ( $this->post_type === get_post_type() ) {
			$classes[] = 'type-handbook';
		}

		return $classes;
	}

	function add_name_setting() {
		register_setting( 'general', $this->setting_name, 'esc_attr' );

		$label = ( 'handbook' == $this->post_type ) ?
			__( 'Handbook name', 'wporg' ) :
			sprintf( __( 'Handbook name (%s)', 'wporg' ), substr( $this->post_type, 0, -9 ) );

		add_settings_field(
			$this->setting_name,
			'<label for="' . esc_attr( $this->setting_name ) . '">' . $label . '</label>',
			array( $this, 'name_setting_html' ),
			'general'
		);
	}

	function name_setting_html() {
		$value = get_option( $this->setting_name, '' );
		echo '<input type="text" id="' . esc_attr( $this->setting_name ) . '" name="' . esc_attr( $this->setting_name ) . '" value="' . esc_attr( $value ) . '" class="regular-text ltr" />';
	}

	function grant_handbook_caps( $caps ) {
		if ( ! is_user_member_of_blog() ) {
			return $caps;
		}

		foreach ( self::caps() as $cap ) {
			$caps[ $cap ] = true;
		}

		if ( ! empty( $caps['edit_pages'] ) ) {
			foreach ( self::editor_caps() as $cap ) {
				$caps[ $cap ] = true;
			}
		}

		return $caps;
	}

	function register_post_type() {
		if ( 'handbook' != $this->post_type ) {
			$slug = substr( $this->post_type, 0, -9 );
		} else {
			$slug = 'handbook';
		}

		$default_config = array(
			'labels' => array(
				'name'          => $this->label,
				'singular_name' => sprintf( __( '%s Page', 'wporg' ), $this->label ),
				'menu_name'     => $this->label,
				'all_items'     => sprintf( __( '%s Pages', 'wporg' ), $this->label ),
			),
			'public'            => true,
			'show_ui'           => true,
			'capability_type'   => 'handbook_page',
			'map_meta_cap'      => true,
			'has_archive'       => true,
			'hierarchical'      => true,
			'menu_position'     => 11,
			'rewrite' => array(
				'feeds'         => false,
				'slug'          => $slug,
				'with_front'    => false,
			),
			'delete_with_user'  => false,
			'supports'          => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions' ),
		);
		// Allow customization of the default post type configuration via filter.
		$config = apply_filters( 'handbook_post_type_defaults', $default_config, $slug );

		$this->label = $config['labels']['name'];

		register_post_type( $this->post_type, $config );
	}

	/**
	 * For a handbook page acting as the root page for the handbook, change its
	 * permalink to be the equivalent of the post type archive link.
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 */
	function post_type_link( $post_link, $post ) {
		$post_type = get_post_type( $post );

		// Only change links for this handbook's post type.
		if ( $post_type === $this->post_type ) {
			// Verify post is not a child page and that its slug matches the criteria to
			// be a handbook root page.
			$post_slug = get_post_field( 'post_name', $post );
			if ( ( $post_slug === $post_type || "{$post_slug}-handbook" === $post_type ) && ! wp_get_post_parent_id( $post ) ) {
				$post_link = get_post_type_archive_link( $post_type );
			}
		}

		return $post_link;
	}

	/**
	 * For a handbook page acting as the root page for the handbook, redirect to the
	 * post type archive link for the handbook.
	 */
	function redirect_handbook_root_page() {
		if ( is_singular( $this->post_type )
			&&
			! get_query_var( 'is_handbook_root' )
			&&
			in_array( get_query_var( 'name' ), array( $this->post_type, substr( $this->post_type, 0, -9 ) ) )
		) {
			wp_safe_redirect( get_post_type_archive_link( $this->post_type ) );
			exit;
		}
	}

	/**
	 * Use 'single-handbook.php' as the fallback template for handbooks.
	 *
	 * Applies to handbooks using a post type other than 'handbook', as well as
	 * the handbook root page.
	 *
	 * @param string $template The path of the template to include.
	 * @return string
	 */
	function template_include( $template ) {
		$handbook_templates = array();

		// For singular handbook pages not of the 'handbook' post type.
		if ( is_singular( $this->post_type ) && 'handbook' !== $this->post_type ) {
			$handbook_templates = array( "single-{$this->post_type}.php", 'single-handbook.php' );
		}
		// For handbook landing page.
		elseif ( get_query_var( 'is_handbook_root' ) && get_query_var( 'handbook' ) === $this->post_type ) {
			if ( 'handbook' !== $this->post_type ) {
				$handbook_templates[] = "single-{$this->post_type}.php";
			}
			$handbook_templates[] = 'single-handbook.php';
		}

		if ( $handbook_templates ) {
			if ( $handbook_template = locate_template( $handbook_templates ) ) {
				$template = $handbook_template;
			}
		}

		return $template;
	}

	/**
	 * Add public query vars for handbooks.
	 *
	 * @param array  $public_query_vars The array of whitelisted query variables.
	 * @return array Array with public query vars.
	 */
	function add_query_vars( $public_query_vars ) {
		$public_query_vars['is_handbook_root'] = false;

		return $public_query_vars;
	}

	function pre_get_posts( $query ) {
		if ( $query->is_main_query() && ! $query->is_admin && ! $query->is_search && $query->is_post_type_archive( $this->post_type ) ) {
			// If the post type has a page to act as an archive index page, get that.
			$page = get_page_by_path( $this->post_type, OBJECT, $this->post_type );
			if ( ! $page ) {
				$slug = substr( $this->post_type, 0, -9 );
				$page = get_page_by_path( $slug, OBJECT, $this->post_type );
			}
			if ( $page ) {
				$query->set( 'p', $page->ID );
				$query->set('is_handbook_root', true );

				$query->is_archive           = false;
				$query->is_post_type_archive = false;
				$query->is_single            = true;
				$query->is_singular          = true;
			}
			$query->set( 'handbook', $this->post_type );
		}
	}

	function handbook_sidebar() {
		$sidebar_args = array(
			'id'          => $this->post_type,
			'name'        => sprintf( __( '%s Sidebar', 'wporg' ), $this->label ),
			'description' => sprintf( __( 'Used on %s pages', 'wporg' ), $this->label ),
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h1 class="widget-title">',
			'after_title'   => '</h1>',
		);

		// P2 usage does not necessitate the custom markup used above.
		// This can be removed once all P2s are converted to o2.
		if ( class_exists( 'P2' ) ) {
			foreach ( array( 'before_widget', 'after_widget', 'before_title', 'after_title' ) as $key ) {
				unset( $sidebar_args[ $key ] );
			}
		}

		register_sidebar( $sidebar_args );

		require_once dirname( __FILE__ ) . '/inc/widgets.php';
		register_widget( 'WPorg_Handbook_Pages_Widget' );
	}

	function wporg_email_changes_for_post_types( $post_types ) {
		if ( ! in_array( $this->post_type, $post_types ) ) {
			$post_types[] = $this->post_type;
		}

		return $post_types;
	}

	/**
	 * Disable the P2 Resolved Posts plugin's action links (e.g. "Flag Unresolved"),
	 * if that plugin is active.
	 */
	function disable_p2_resolved_posts_action_links() {
		if ( ( $this->post_type == get_post_type() ) && class_exists( 'P2_Resolved_Posts' ) && isset( $GLOBALS['p2_resolved_posts'] ) && is_object( $GLOBALS['p2_resolved_posts'] ) ) {
			remove_filter( 'p2_action_links', array( P2_Resolved_Posts::instance(), 'p2_action_links' ), 100 );
		}
	}

}
