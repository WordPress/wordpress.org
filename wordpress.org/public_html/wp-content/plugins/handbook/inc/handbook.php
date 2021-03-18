<?php
/**
 * Class for primary functionality of a handbook.
 *
 * @package handbook
 */

class WPorg_Handbook {

	/**
	 * The handbook post type.
	 *
	 * @var string
	 */
	public $post_type = '';

	/**
	 * The handbook's settings.
	 *
	 * @var array
	 */
	public $settings = [];

	/**
	 * The name of the setting for the handbook's name.
	 *
	 * @var string
	 */
	public $setting_name = '';

	/**
	 * The memoized and filtered label text for the handbook.
	 *
	 * @var string
	 */
	protected $label = '';

	/**
	 * The associated importer object, if warranted.
	 */
	protected $importer;

	/**
	 * Returns the custom handbook-related capabilities granted to site users.
	 *
	 * @return array
	 */
	public static function caps() {
		return [
			'edit_handbook_pages',
			'edit_others_handbook_pages',
			'edit_published_handbook_pages',
		];
	}

	/**
	 * Returns the custom capabilities granted to handbook editors.
	 *
	 * @return array
	 */
	public static function editor_caps() {
		return [
			'publish_handbook_pages',
			'delete_handbook_pages',
			'delete_others_handbook_pages',
			'delete_published_handbook_pages',
			'delete_private_handbook_pages',
			'edit_private_handbook_pages',
			'read_private_handbook_pages',
		];
	}

	/**
	 * Returns handbook default config.
	 *
	 * @return array
	 */
	public static function get_default_handbook_config() {
		/**
		 * Filters default handbook configuration array.
		 *
		 * @param array $config {
		 *     Associative array of handbook configuration.
		 *
		 *     @type string $cron_interval The cron interval for which an imported handbook gets
		 *                                 imported, e.g. 'hourly', 'daily'. If defined as an
		 *                                 unrecognized interval, 'hourly' will be used.
		 *                                 Default '15_minutes'.
		 *     @type string $label The label for the handbook. Default is the
		 *                         post type slug converted to titlecase (e.g.
		 *                         plugin-handbok => "Plugin Handbook").
		 *     @type string manifest       The URL to the manifest JSON file for an imported
		 *                                 handbook.
		 *     @type string $slug  The slug for the post type. Default is the
		 *                         post type.
		 * }
		 */
		return (array) apply_filters( 'handbook_default_handbook_config', [
			'cron_interval' => '15_minutes',
			'label'         => '',
			'manifest'      => '',
			'slug'          => '',
		] );
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
	public static function get_name( $post_type = 'handbook', $raw = false ) {
		// Prefer explicitly configured handbook name.
		$name = get_option( $post_type . '_name' );

		// If handbook name isn't set, try configured label.
		if ( ! $raw && ! $name ) {
			$config = WPorg_Handbook_Init::get_handbooks_config( $post_type );
			if ( ! empty( $config['label'] ) ) {
				$name = $config['label'];
			}
		}

		// If handbook name isn't set, try root relative site path.
		if ( ! $raw && ! $name ) {
			if ( is_multisite() ) {
				$name = trim( get_blog_details()->path, '/' );
			} else {
				$name = trim( parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
			}

			// If no name defined yet, try handbook post type if not standard.
			if ( ! $name && ( 'handbook' !== $post_type ) ) {
				$name = str_replace( '-handbook', '', $post_type );
			}

			$name .= ' Handbook';
			$name = ucfirst( $name );
		}

		return trim( $name );
	}

	/**
	 * Constructor
	 *
	 * @param string $type   The post type for the handbook.
	 * @param array  $config The config array for the handbook.
	 */
	public function __construct( $type, $config = [] ) {
		$this->post_type = sanitize_title( $type );

		$config = $this->config = wp_parse_args( $config, self::get_default_handbook_config() );

		$this->label = apply_filters(
			'handbook_label',
			$config['label'] ?: ucwords( str_replace( [ '-', '_' ], ' ', $this->post_type ) ),
			$this->post_type
		);

		$this->setting_name = $this->post_type . '_name';

		add_action( 'after_handbooks_init',               [ $this, 'init_importer' ] );
		add_filter( 'user_has_cap',                       [ $this, 'grant_handbook_caps' ] );
		add_action( 'widgets_init',                       [ $this, 'register_post_type' ] );
		add_filter( 'post_type_link',                     [ $this, 'post_type_link' ], 10, 2 );
		add_action( 'template_redirect',                  [ $this, 'redirect_handbook_root_page' ] );
		add_filter( 'template_include',                   [ $this, 'template_include' ] );
		add_filter( 'pre_get_posts',                      [ $this, 'pre_get_posts' ] );
		add_filter( 'posts_pre_query',                    [ $this, 'posts_pre_query' ], 10, 2 );
		add_action( 'widgets_init',                       [ $this, 'handbook_sidebar' ], 11 ); // After P2
		add_action( 'wporg_email_changes_for_post_types', [ $this, 'wporg_email_changes_for_post_types' ] );
		add_action( 'p2_action_links',                    [ $this, 'disable_p2_resolved_posts_action_links' ] );
		add_action( 'admin_init',                         [ $this, 'add_name_setting' ] );
		add_filter( 'body_class',                         [ $this, 'add_body_class' ] );
		add_filter( 'post_class',                         [ $this, 'add_post_class' ] );
		add_filter( 'o2_process_the_content',             [ $this, 'disable_o2_processing' ] );
		add_filter( 'o2_application_container',           [ $this, 'o2_application_container' ] );
		add_filter( 'o2_view_type',                       [ $this, 'o2_view_type' ] );
		add_filter( 'o2_post_fragment',                   [ $this, 'o2_post_fragment' ] );
		add_filter( 'comments_open',                      [ $this, 'comments_open' ], 10, 2 );
		add_filter( 'wp_nav_menu_objects',                [ $this, 'highlight_menu_handbook_link' ] );
		add_filter( 'display_post_states',                [ $this, 'display_post_states' ], 10, 2 );
	}

	/**
	 * Returns the configuration array for handbooks.
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Returns the handbook's importer object, if applicable.
	 *
	 * @return WPorg_Handbook_Importer|null
	 */
	public function get_importer() {
		return $this->importer;
	}

	/**
	 * Initializes the importer, if applicable.
	 */
	public function init_importer() {
		$config = $this->get_config();

		if ( class_exists( 'WPorg_Handbook_Importer' ) ) {
			if ( WPorg_Handbook_Importer::is_handbook_imported( $this->post_type ) ) {
				$this->importer = new WPorg_Handbook_Importer( $this );
			}
		} elseif ( is_admin() && ( $config['manifest'] ?: false ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>' . __( 'Error: The <strong>WPORG Markdown Importer</strong> plugin needs to be activated in order to allow importing of handbooks.', 'wporg' ) . '</p></div>';
			} );
		}
	}

	/**
	 * Adds 'Handbook Front Page' post state indicator for handbook landing pages.
	 *
	 * @param string[] $post_states An array of post display states.
	 * @param WP_Post  $post        The current post object.
	 * @return string[]
	 */
	public function display_post_states( $post_states, $post ) {
		if ( $this->post_is_landing_page( $post ) ) {
			$post_states[] = __( 'Handbook Front Page', 'wporg' );
		}
		return $post_states;
	}

	/**
	 * Adds custom handbook-related classes to body tag.
	 *
	 * * Adds 'single-handbook' class for any handbook page.
	 * * Adds 'post-type-archive-handbook' for any handbook archive page.
	 * * Adds 'handbook-landing-page' class for page acting as a handbook landing
	 *   page.
	 *
	 * @param array $classes Array of body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		if ( is_singular() && wporg_is_handbook( $this->post_type ) ) {
			$classes[] = 'single-handbook';
		}

		if ( is_archive() && wporg_is_handbook( $this->post_type ) ) {
			$classes[] = 'post-type-archive-handbook';
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
	public function add_post_class( $classes ) {
		if ( $this->post_type === get_post_type() ) {
			$classes[] = 'type-handbook';
		}

		return $classes;
	}

	/**
	 * Adds the setting for the handbook's name.
	 */
	public function add_name_setting() {
		register_setting( 'general', $this->setting_name, 'esc_attr' );

		$label = ( 'handbook' === $this->post_type ) ?
			__( 'Handbook name', 'wporg' ) :
			sprintf( __( 'Handbook name (%s)', 'wporg' ), str_replace( '-handbook', '', $this->post_type ) );

		add_settings_field(
			$this->setting_name,
			'<label for="' . esc_attr( $this->setting_name ) . '">' . $label . '</label>',
			[ $this, 'name_setting_html' ],
			'general'
		);
	}

	/**
	 * Outputs the HTML for the input field for the handbook's name.
	 */
	public function name_setting_html() {
		$value = get_option( $this->setting_name, '' );
		echo '<input type="text" id="' . esc_attr( $this->setting_name ) . '" name="' . esc_attr( $this->setting_name ) . '" value="' . esc_attr( $value ) . '" class="regular-text ltr" />';
	}

	/**
	 * Grants handbook caps to the current user.
	 *
	 * @return array
	 */
	public function grant_handbook_caps( $caps ) {
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

	/**
	 * Registers handbook post types.
	 */
	public function register_post_type() {
		$config = $this->get_config();

		if ( ! empty( $config['slug'] ) ) {
			$slug = $config['slug'];
		} elseif ( 'handbook' !== $this->post_type ) {
			$slug = str_replace( '-handbook', '', $this->post_type );
		} else {
			$slug = 'handbook';
		}

		$default_config = [
			'labels' => [
				'name'          => $this->label,
				'singular_name' => sprintf( __( '%s Page', 'wporg' ), $this->label ),
				'menu_name'     => $this->label,
				'all_items'     => sprintf( __( '%s Pages', 'wporg' ), $this->label ),
			],
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'capability_type'   => 'handbook_page',
			'map_meta_cap'      => true,
			'has_archive'       => true,
			'hierarchical'      => true,
			'menu_icon'         => 'dashicons-book',
			'menu_position'     => 11,
			'rewrite' => [
				'feeds'         => false,
				'slug'          => $slug,
				'with_front'    => false,
			],
			'delete_with_user'  => false,
			'supports'          => [ 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions', 'wpcom-markdown' ],
		];
		// Allow customization of the default post type configuration via filter.
		$config = (array) apply_filters( 'handbook_post_type_defaults', $default_config, $slug );

		// Override the presumed label with a potentially customized value.
		if ( ! empty( $config['labels']['name'] ) ) {
			$this->label = $config['labels']['name'];
		}

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
	public function post_is_landing_page( $post = null ) {
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
	public function post_type_link( $post_link, $post ) {
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
	public function redirect_handbook_root_page() {
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
	public function template_include( $template ) {
		global $wp_query;

		// Don't override Embeds
		if ( is_embed() ) {
			return $template;
		}

		$handbook_templates = [];

		// For singular handbook pages not of the 'handbook' post type.
		if ( is_singular( $this->post_type ) && 'handbook' !== $this->post_type ) {
			$handbook_templates = [ "single-{$this->post_type}.php", 'single-handbook.php' ];
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

	/**
	 * Pre-emptively sets the query posts to the handbook landing page when
	 * appropriate.
	 *
	 * @param array $posts    Posts.
	 * @param WP_Query $query Query object.
	 * @return array
	 */
	public function posts_pre_query( $posts, $query ) {
		if ( $query->is_main_query() && ! $query->is_admin && ! $query->is_search && $query->is_handbook_root ) {
			$posts = [ $query->is_handbook_root ];
		}

		return $posts;
	}

	/**
	 * Performs query object adjustments for handbook requests prior to querying
	 * for posts.
	 */
	public function pre_get_posts( $query ) {
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
				$slug = str_replace( '-handbook', '', $this->post_type );
				if ( $slug !== $this->post_type ) {
					$page = get_page_by_path( $slug, OBJECT, $this->post_type );
				}
			}
			if ( ! $page && 'handbook' !== $this->post_type ) {
				$page = get_page_by_path( 'handbook', OBJECT, $this->post_type );
			}
			if ( ! $page && 'welcome' !== $this->post_type ) {
				$page = get_page_by_path( 'welcome', OBJECT, $this->post_type );
			}
			if ( $page ) {
				$query->set( 'page_id', $page->ID );
				$query->is_handbook_root     = $page;

				$query->is_archive           = false;
				$query->is_post_type_archive = false;
				$query->is_single            = true;
				$query->is_singular          = true;
			}
			$query->set( 'handbook', $this->post_type );
		}
	}

	/**
	 * Registers sidebar and widgets for handbook pages.
	 */
	public function handbook_sidebar() {
		$sidebar_args = [
			'id'            => $this->post_type,
			'name'          => sprintf( __( '%s Sidebar', 'wporg' ), $this->label ),
			'description'   => sprintf( __( 'Used on %s pages', 'wporg' ), $this->label ),
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		];

		$sidebar_args = apply_filters( 'wporg_handbook_sidebar_args', $sidebar_args, $this );

		register_sidebar( $sidebar_args );

		require_once dirname( WPORG_HANDBOOK_PLUGIN_FILE ) . '/inc/widgets.php';
		register_widget( 'WPorg_Handbook_Pages_Widget' );
	}

	/**
	 * Amends list of post types for which users can opt into receiving emails
	 * about changes.
	 *
	 * @param array $post_types Post types.
	 * @return array
	 */
	public function wporg_email_changes_for_post_types( $post_types ) {
		if ( ! in_array( $this->post_type, $post_types ) ) {
			$post_types[] = $this->post_type;
		}

		return $post_types;
	}

	/**
	 * Disable the P2 Resolved Posts plugin's action links (e.g. "Flag Unresolved"),
	 * if that plugin is active.
	 */
	public function disable_p2_resolved_posts_action_links() {
		if ( ( $this->post_type === get_post_type() ) && class_exists( 'P2_Resolved_Posts' ) && isset( $GLOBALS['p2_resolved_posts'] ) && is_object( $GLOBALS['p2_resolved_posts'] ) ) {
			remove_filter( 'p2_action_links', [ P2_Resolved_Posts::instance(), 'p2_action_links' ], 100 );
		}
	}

	/**
	 * Disables handbook post content processing by the o2 plugin.
	 *
	 * @param bool $process_with_o2 Is o2 about to process the post content?
	 * @return bool
	 */
	public function disable_o2_processing( $process_with_o2 ) {
		return ( is_singular() && $this->post_type === get_post_type() ) ? false : $process_with_o2;
	}

	/**
	 * Use the correct ID for the content container element.
	 *
	 * @param string $container The container element ID.
	 * @return string
	 */
	public function o2_application_container( $container ) {
		return ( is_singular() && $this->post_type === get_post_type() ) ? '#primary' : $container;
	}

	/**
	 * Tell o2 to use the 'single' view type for handbook pages. This removes a lot of the meta
	 * cruft around the content.
	 *
	 * @param string $view_type The o2 view type.
	 * @return string
	 */
	public function o2_view_type( $view_type ) {
		return ( is_singular() && $this->post_type === get_post_type() ) ? 'single' : $view_type;
	}

	/**
	 * Tell o2 to treat the handbook page the same as it would a normal page.
	 *
	 * @param array $post_fragment The o2 post fragment
	 * @return array
	 */
	public function o2_post_fragment( $post_fragment ) {
		if ( empty( $post_fragment['id'] ) ) {
			return $post_fragment;
		}

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
	public function comments_open( $open, $post_id ) {
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
	public function highlight_menu_handbook_link( $menu_items ) {
		// Must be on a handbook page that isn't the handbook landing page (which will already be handled).
		if ( ! is_page( [ 'handbook', 'handbooks' ] ) && ( ! wporg_is_handbook() || wporg_is_handbook_landing_page() ) ) {
			return $menu_items;
		}

		// Menu must not have an item that is already noted as being current.
		$current_menu_item = wp_filter_object_list( $menu_items, [ 'current' => true ] );
		if ( $current_menu_item ) {
			return $menu_items;
		}

		// Menu must have an item that links to handbook home page.
		$root_handbook_menu_item = wp_filter_object_list( $menu_items, [ 'url' => wporg_get_current_handbook_home_url() ] );
		if ( ! $root_handbook_menu_item ) {
			// Or it must have an item that links to a 'handbook' or 'handbooks' page.
			$page_slug = is_page( 'handbooks' ) ? 'handbooks' : 'handbook';
			$page = get_page_by_path( $page_slug );
			if ( $page ) {
				$root_handbook_menu_item = wp_filter_object_list( $menu_items, [ 'object_id' => $page->ID ] );
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
