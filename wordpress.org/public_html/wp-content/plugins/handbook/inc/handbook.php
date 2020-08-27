<?php
/**
 * Class for primary functionality of a handbook.
 *
 * @package handbook
 */

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
		$this->label = apply_filters( 'handbook_label', $this->label, $this->post_type );

		$this->setting_name = $this->post_type . '_name';

		add_filter( 'user_has_cap',                       array( $this, 'grant_handbook_caps' ) );
		add_action( 'widgets_init',                       array( $this, 'register_post_type' ) );
		add_filter( 'post_type_link',                     array( $this, 'post_type_link' ), 10, 2 );
		add_action( 'template_redirect',                  array( $this, 'redirect_handbook_root_page' ) );
		add_filter( 'template_include',                   array( $this, 'template_include' ) );
		add_filter( 'pre_get_posts',                      array( $this, 'pre_get_posts' ) );
		add_action( 'widgets_init',                       array( $this, 'handbook_sidebar' ), 11 ); // After P2
		add_action( 'wporg_email_changes_for_post_types', array( $this, 'wporg_email_changes_for_post_types' ) );
		add_action( 'p2_action_links',                    array( $this, 'disable_p2_resolved_posts_action_links' ) );
		add_action( 'admin_init',                         array( $this, 'add_name_setting' ) );
		add_filter( 'body_class',                         array( $this, 'add_body_class' ) );
		add_filter( 'post_class',                         array( $this, 'add_post_class' ) );
		add_filter( 'o2_process_the_content',             array( $this, 'disable_o2_processing' ) );
		add_filter( 'o2_application_container',           array( $this, 'o2_application_container' ) );
		add_filter( 'o2_view_type',                       array( $this, 'o2_view_type' ) );
		add_filter( 'o2_post_fragment',                   array( $this, 'o2_post_fragment' ) );
		add_filter( 'comments_open',                      array( $this, 'comments_open' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects',                array( $this, 'highlight_menu_handbook_link' ) );
		add_filter( 'display_post_states',                array( $this, 'display_post_states' ), 10, 2 );
	}

	/**
	 * Adds 'Handbook Front Page' post state indicator for handbook landing pages.
	 *
	 * @param string[] $post_states An array of post display states.
	 * @param WP_Post  $post        The current post object.
	 * @return string[]
	 */
	function display_post_states( $post_states, $post ) {
		if ( $this->post_is_landing_page( $post ) ) {
			$post_states[] = __( 'Handbook Front Page', 'wporg' );
		}
		return $post_states;
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
		if ( is_singular() && wporg_is_handbook( $this->post_type ) ) {
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
			'show_in_rest'      => true,
			'capability_type'   => 'handbook_page',
			'map_meta_cap'      => true,
			'has_archive'       => true,
			'hierarchical'      => true,
			'menu_icon'         => 'dashicons-book',
			'menu_position'     => 11,
			'rewrite' => array(
				'feeds'         => false,
				'slug'          => $slug,
				'with_front'    => false,
			),
			'delete_with_user'  => false,
			'supports'          => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions', 'wpcom-markdown' ),
		);
		// Allow customization of the default post type configuration via filter.
		$config = apply_filters( 'handbook_post_type_defaults', $default_config, $slug );

		$this->label = $config['labels']['name'];

		register_post_type( $this->post_type, $config );
	}

	/**
	 * Determines if the given values correspond to a post that acts as the
	 * landing page for this handbook.
	 *
	 * @param int|WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return bool True if the given information would make such a post the
	 *              handbook's landing page.
	 */
	protected function post_is_landing_page( $post = null ) {
		$is_landing_page = false;

		$post_type = get_post_type( $post );
		$slug      = get_post_field( 'post_name', $post );

		if (
			$post_type === $this->post_type
		&&
			(
				$post_type === $slug
			||
				$post_type === "{$slug}-handbook"
			||
				'handbook' === $slug
			||
				'welcome'  === $slug
			)
		&&
			! wp_get_post_parent_id( $post )
		) {
			$is_landing_page = true;
		}

		return $is_landing_page;
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
		if ( $this->post_is_landing_page( $post ) ) {
			$post_link = get_post_type_archive_link( $post_type );
		}

		return $post_link;
	}

	/**
	 * For a handbook page acting as the root page for the handbook, redirect to the
	 * post type archive link for the handbook.
	 */
	function redirect_handbook_root_page() {
		global $wp_query;

		if ( is_singular( $this->post_type )
			&&
			! is_preview()
			&&
			! $wp_query->is_handbook_root
			&&
			$this->post_is_landing_page( get_queried_object_id() )
		) {
			wp_safe_redirect( get_post_type_archive_link( $this->post_type ), 301 );
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
		global $wp_query;

		// Don't override Embeds
		if ( is_embed() ) {
			return $template;
		}

		$handbook_templates = array();

		// For singular handbook pages not of the 'handbook' post type.
		if ( is_singular( $this->post_type ) && 'handbook' !== $this->post_type ) {
			$handbook_templates = array( "single-{$this->post_type}.php", 'single-handbook.php' );
		}
		// For handbook landing page.
		elseif ( $wp_query->is_handbook_root && get_query_var( 'handbook' ) === $this->post_type ) {
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

	function pre_get_posts( $query ) {
		// Bail early if query is not for this handbook's post type.
		if ( get_query_var( 'post_type' ) !== $this->post_type ) {
			// Request is obviously not for a handbook root page. (Though if the request is
			// for some other handbook's root page will be determined by that handbook.)
			if ( empty( get_query_var( 'handbook' ) ) ) {
				$query->is_handbook_root = false;
			}
			return;
		}

		$query->is_handbook_root = false;

		if ( $query->is_main_query() && ! $query->is_admin && ! $query->is_search && $query->is_post_type_archive( $this->post_type ) ) {
			// If the post type has a page to act as an archive index page, get that.
			$page = get_page_by_path( $this->post_type, OBJECT, $this->post_type );
			if ( ! $page ) {
				$slug = substr( $this->post_type, 0, -9 );
				$page = get_page_by_path( $slug, OBJECT, $this->post_type );
			}
			if ( ! $page ) {
				$page = get_page_by_path( 'handbook', OBJECT, $this->post_type );
			}
			if ( ! $page ) {
				$page = get_page_by_path( 'welcome', OBJECT, $this->post_type );
			}
			if ( $page ) {
				$query->set( 'p', $page->ID );
				$query->is_handbook_root     = true;

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
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		);

		$sidebar_args = apply_filters( 'wporg_handbook_sidebar_args', $sidebar_args, $this );

		register_sidebar( $sidebar_args );

		require_once dirname( WPORG_HANDBOOK_PLUGIN_FILE ) . '/inc/widgets.php';
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

	/**
	 * Disables handbook post content processing by the o2 plugin.
	 *
	 * @param bool $process_with_o2 Is o2 about to process the post content?
	 * @return bool
	 */
	function disable_o2_processing( $process_with_o2 ) {
		return ( is_singular() && $this->post_type === get_post_type() ) ? false : $process_with_o2;
	}

	/**
	 * Use the correct ID for the content container element.
	 *
	 * @param string $container The container element ID.
	 * @return string
	 */
	function o2_application_container( $container ) {
		return ( is_singular() && $this->post_type === get_post_type() ) ? '#primary' : $container;
	}

	/**
	 * Tell o2 to use the 'single' view type for handbook pages. This removes a lot of the meta
	 * cruft around the content.
	 *
	 * @param string $view_type The o2 view type.
	 * @return string
	 */
	function o2_view_type( $view_type ) {
		return ( is_singular() && $this->post_type === get_post_type() ) ? 'single' : $view_type;
	}

	/**
	 * Tell o2 to treat the handbook page the same as it would a normal page.
	 *
	 * @param array $post_fragment The o2 post fragment
	 * @return array
	 */
	function o2_post_fragment( $post_fragment ) {
		$post = get_post( $post_fragment['id'] );
		if ( ! $post ) {
			return $post_fragment;
		}

		if ( $post->post_type === $this->post_type ) {
			$post_fragment['isPage'] = true;
		}

		return $post_fragment;
	}

	/**
	 * Don't show the comment form on handbook pages.
	 *
	 * @param bool $open Whether the comments are open or not.
	 * @param WP_Post|int $post_id The current post.
	 * @return bool
	 */
	function comments_open( $open, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $open;
		}

		if ( $post->post_type === $this->post_type ) {
			return false;
		}

		return $open;
	}

	/**
	 * Highlights a menu link to the handbook home page when on any constituent
	 * handbook page.
	 *
	 * Assuming the handbook page isn't already directly linked in the menu,
	 * preference is given to highlight a link to the front page of the current
	 * handbook. Barring the presence of such a link, it will check to see if
	 * there is a link to a 'handbook' or 'handbooks' page, which could be the
	 * case for multi-handbook sites.
	 *
	 * @param array $menu_items Array of sorted menu items.
	 * @return array
	 */
	function highlight_menu_handbook_link( $menu_items ) {
		// Must be on a handbook page that isn't the handbook landing page (which will already be handled).
		if ( ! is_page( array( 'handbook', 'handbooks' ) ) && ( ! wporg_is_handbook() || wporg_is_handbook_landing_page() ) ) {
			return $menu_items;
		}

		// Menu must not have an item that is already noted as being current.
		$current_menu_item = wp_filter_object_list( $menu_items, array( 'current' => true ) );
		if ( $current_menu_item ) {
			return $menu_items;
		}

		// Menu must have an item that links to handbook home page.
		$root_handbook_menu_item = wp_filter_object_list( $menu_items, array( 'url' => wporg_get_current_handbook_home_url() ) );
		if ( ! $root_handbook_menu_item ) {
			// Or it must have an item that links to a 'handbook' or 'handbooks' page.
			$page_slug = is_page( 'handbooks' ) ? 'handbooks' : 'handbook';
			$page = get_page_by_path( $page_slug );
			if ( $page ) {
				$root_handbook_menu_item = wp_filter_object_list( $menu_items, array( 'object_id' => $page->ID ) );
			}
		}
		if ( ! $root_handbook_menu_item ) {
			return $menu_items;
		}

		// Add current-menu-item class to the handbook menu item.
		reset( $root_handbook_menu_item );
		$handbook_item_index = key( $root_handbook_menu_item );
		$menu_items[ $handbook_item_index ]->classes[] = 'current-menu-item';

		return $menu_items;
	}

}
