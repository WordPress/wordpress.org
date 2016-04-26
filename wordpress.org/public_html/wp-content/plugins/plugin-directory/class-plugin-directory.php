<?php
namespace WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Admin\Customizations;

/**
 * The main Plugin Directory class, it handles most of the bootstrap and basic operations of the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Directory {

	/**
	 * Fetch the instance of the Plugin_Directory class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Plugin_Directory();
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_filter( 'post_type_link', array( $this, 'package_link' ), 10, 2 );
		add_filter( 'pre_insert_term', array( $this, 'pre_insert_term_prevent' ) );
		add_action( 'pre_get_posts', array( $this, 'use_plugins_in_query' ) );
		add_filter( 'the_content', array( $this, 'filter_post_content_to_correct_page' ), 1 );
		add_filter( 'rest_api_allowed_post_types', array( $this, 'filter_allowed_post_types' ) );
		add_filter( 'pre_update_option_jetpack_options', array( $this, 'filter_jetpack_options' ) );

		add_filter( 'map_meta_cap', array( __NAMESPACE__ . '\Capabilities', 'map_meta_cap' ), 10, 4 );

		// Shim in postmeta support for data which doesn't yet live in postmeta
		add_filter( 'get_post_metadata', array( $this, 'filter_shim_postmeta' ), 10, 3 );

		// Load the API routes
		add_action( 'rest_api_init', array( __NAMESPACE__ . '\API\Base', 'load_routes' ) );

		// Load all Admin-specific items.
		// Cannot be included on `admin_init` to allow access to menu hooks
		if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
			Customizations::instance();

			add_action( 'wp_insert_post_data', array( __NAMESPACE__ . '\Admin\Status_Transitions', 'can_change_post_status' ), 10, 2 );
			add_action( 'transition_post_status', array( __NAMESPACE__ . '\Admin\Status_Transitions', 'instance' ) );
		}

		register_activation_hook( PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Set up the Plugin Directory.
	 */
	public function init() {
		load_plugin_textdomain( 'wporg-plugins' );

		wp_cache_add_global_groups( 'wporg-plugins' );

		register_post_type( 'plugin', array(
			'labels'          => array(
				'name'               => __( 'Plugins',                   'wporg-plugins' ),
				'singular_name'      => __( 'Plugin',                    'wporg-plugins' ),
				'menu_name'          => __( 'My Plugins',                'wporg-plugins' ),
				'add_new'            => __( 'Add New',                   'wporg-plugins' ),
				'add_new_item'       => __( 'Add New Plugin',            'wporg-plugins' ),
				'new_item'           => __( 'New Plugin',                'wporg-plugins' ),
				'view_item'          => __( 'View Plugin',               'wporg-plugins' ),
				'search_items'       => __( 'Search Plugins',            'wporg-plugins' ),
				'not_found'          => __( 'No plugins found',          'wporg-plugins' ),
				'not_found_in_trash' => __( 'No plugins found in Trash', 'wporg-plugins' ),

				// Context only available in admin, not in toolbar.
				'edit_item'          => is_admin() ? __( 'Editing Plugin: %s', 'wporg-plugins' ) : __( 'Edit Plugin', 'wporg-plugins' ),
			),
			'description'     => __( 'A Repo Plugin', 'wporg-plugins' ),
			'supports'        => array( 'comments' ),
			'public'          => true,
			'show_ui'         => true,
			'has_archive'     => true,
			'rewrite'         => false,
			'menu_icon'       => 'dashicons-admin-plugins',
			'capabilities'    => array(
				'edit_post'          => 'plugin_edit',
				'read_post'          => 'read',
				'edit_posts'         => 'plugin_dashboard_access',
				'edit_others_posts'  => 'plugin_edit_others',
				'publish_posts'      => 'plugin_approve',
				'read_private_posts' => 'do_not_allow',
				'delete_posts'       => 'do_not_allow',
				'create_posts'       => 'do_not_allow',
			)
		) );

		register_taxonomy( 'plugin_category', 'plugin', array(
			'hierarchical'      => true,
			'query_var'         => 'plugin_category',
			'rewrite'           => false,
			'public'            => false,
			'show_ui'           => current_user_can( 'plugin_set_category' ),
			'show_admin_column' => current_user_can( 'plugin_set_category' ),
			'meta_box_cb'       => 'post_categories_meta_box',
			'capabilities'      => array(
				'assign_terms' => 'manage_categories',
			)
		) );

		register_taxonomy( 'plugin_tag', 'plugin', array(
			'hierarchical'      => true, /* for tax_input[] handling on post saves. */
			'query_var'         => 'plugin_tag',
			'rewrite'           => array(
				'hierarchical' => false,
				'slug'         => 'tags',
				'with_front'   => false,
				'ep_mask'      => EP_TAGS,
			),
			'labels'            => array(
				'name'          => __( 'Plugin Tags',  'wporg-plugins' ),
				'singular_name' => __( 'Plugin Tag',   'wporg-plugins' ),
				'edit_item'     => __( 'Edit Tag',     'wporg-plugins' ),
				'update_item'   => __( 'Update Tag',   'wporg-plugins' ),
				'add_new_item'  => __( 'Add New Tag',  'wporg-plugins' ),
				'new_item_name' => __( 'New Tag Name', 'wporg-plugins' ),
				'search_items'  => __( 'Search Tags',  'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'meta_box_cb'       => array( __NAMESPACE__ . '\Admin\Metabox\Plugin_Tags', 'display' ),
			'capabilities'      => array(
				'assign_terms' => 'plugin_set_tags'
			)
		) );

		register_post_status( 'pending', array(
			'label'                     => _x( 'Pending', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_review' ),
			'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'disabled', array(
			'label'                     => _x( 'Disabled', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_disable' ),
			'label_count'               => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'closed', array(
			'label'                     => _x( 'Closed', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_close' ),
			'label_count'               => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'rejected', array(
			'label'                     => _x( 'Rejected', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_reject' ),
			'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );

		// Add the browse/* views.
		add_rewrite_tag( '%browse%', '(featured|popular|beta|new|favorites)' );
		add_permastruct( 'browse', 'browse/%browse%' );

		add_rewrite_endpoint( 'installation', EP_PERMALINK );
		add_rewrite_endpoint( 'faq',          EP_PERMALINK );
		add_rewrite_endpoint( 'screenshots',  EP_PERMALINK );
		add_rewrite_endpoint( 'changelog',    EP_PERMALINK );
		add_rewrite_endpoint( 'stats',        EP_PERMALINK );
		add_rewrite_endpoint( 'developers',   EP_PERMALINK );
		add_rewrite_endpoint( 'other_notes',  EP_PERMALINK );

		// If changing capabilities around, uncomment this.
		//Capabilities::add_roles();

		// When this plugin is used in the context of a Rosetta site, handle it gracefully
		if ( 'wordpress.org' != $_SERVER['HTTP_HOST'] && defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
			add_filter( 'option_home',    array( $this, 'rosetta_network_localize_url' ) );
			add_filter( 'option_siteurl', array( $this, 'rosetta_network_localize_url' ) );
		}

		// Instantiate our copy of the Jetpack_Search class.
		if ( class_exists( 'Jetpack' ) && ! class_exists( 'Jetpack_Search' ) ) {
			require_once( __DIR__ . '/libs/site-search/jetpack-search.php' );
			\Jetpack_Search::instance();
		}
	}

	/**
	 * Register the Shortcodes used within the content.
	 */
	public function register_shortcodes() {
		add_shortcode( 'wporg-plugin-upload',       array( __NAMESPACE__ . '\Shortcodes\Upload',      'display' ) );
		add_shortcode( 'wporg-plugins-screenshots', array( __NAMESPACE__ . '\Shortcodes\Screenshots', 'display' ) );
	//	add_shortcode( 'wporg-plugins-stats',       array( __NAMESPACE__ . '\Shortcodes\Stats',       'display' ) );
	//	add_shortcode( 'wporg-plugins-developer',   array( __NAMESPACE__ . '\Shortcodes\Developer',   'display' ) );
	}

	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\Widgets\Metadata' );
		register_widget( __NAMESPACE__ . '\Widgets\Ratings' );
	}

	/**
	 * Upon plugin activation, set up the current site for acting
	 * as the plugin directory.
	 *
	 * Setting up the site requires setting up the theme and proper
	 * rewrite permastructs.
	 */
	public function activate() {

		/**
		 * @var \WP_Rewrite $wp_rewrite WordPress rewrite component.
		 */
		global $wp_rewrite;

		// Setup the environment.
		$this->init();

		// %postname% is required.
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// /tags/%slug% is required for tags.
		$wp_rewrite->set_tag_base( '/tags' );

		// We require the WordPress.org Ratings plugin also be active.
		if ( ! is_plugin_active( 'wporg-ratings/wporg-ratings.php' ) ) {
			activate_plugin( 'wporg-ratings/wporg-ratings.php' );
		}

		/**
		 * Enable the WordPress.org Plugin Repo Theme.
		 *
		 * @var \WP_Theme $theme
		 */
		foreach ( wp_get_themes() as $theme ) {
			if ( $theme->get( 'Name' ) === 'WordPress.org Plugins' ) {
				switch_theme( $theme->get_stylesheet() );
				break;
			}
		}

		flush_rewrite_rules();

		do_action( 'wporg_plugins_activation' );
	}

	/**
	 * Clean up options & rewrite rules after plugin deactivation.
	 */
	public function deactivate() {
		flush_rewrite_rules();

		do_action( 'wporg_plugins_deactivation' );
	}

	/**
	 * Filter the URLs to use the current localized domain name, rather than WordPress.org.
	 *
	 * The Plugin Directory is available at multiple URLs (internationalised domains), this method allows
	 * for the one blog (a single blog_id) to be presented at multiple URLs yet have correct localised links.
	 *
	 * This method works in conjunction with a filter in sunrise.php, duplicated here for transparency:
	 *
	 * // Make the Plugin Directory available at /plugins/ on all rosetta sites.
	 * function wporg_plugins_on_rosetta_domains( $site, $domain, $path, $segments ) {
	 *     // All non-rosetta networks define DOMAIN_CURRENT_SITE in wp-config.php
	 *     if ( ! defined( 'DOMAIN_CURRENT_SITE' ) && 'wordpress.org' != $domain && '/plugins/' == substr( $path . '/', 0, 9 ) ) {
	 *          $site = get_blog_details( WPORG_PLUGIN_DIRECTORY_BLOGID );
	 *          if ( $site ) {
	 *              $site = clone $site;
	 *              // 6 = The Rosetta network, this causes the site to be loaded as part of the Rosetta network
	 *              $site->site_id = 6;
	 *              return $site;
	 *          }
	 *     }
	 *
	 *     return $site;
	 * }
	 * add_filter( 'pre_get_site_by_path', 'wporg_plugins_on_rosetta_domains', 10, 4 );
	 *
	 * @param string $url The URL to be localized.
	 * @return string
	 */
	public function rosetta_network_localize_url( $url ) {
		static $localized_url = null;

		if ( is_null( $localized_url ) ) {
			$localized_url = 'https://' . preg_replace( '![^a-z.-]+!', '', $_SERVER['HTTP_HOST'] );
		}

		return preg_replace( '!^[https]+://wordpress\.org!i', $localized_url, $url );
	}

	/**
	 * Filter the permalink for the Plugins to be /plugin-name/.
	 *
	 * @param string   $link The generated permalink.
	 * @param \WP_Post $post The Plugin post object.
	 * @return string
	 */
	public function package_link( $link, $post ) {
		if ( 'plugin' !== $post->post_type ) {
			return $link;
		}

		return trailingslashit( home_url( $post->post_name ) );
	}

	/**
	 * Checks if the current users is a super admin before allowing terms to be added.
	 *
	 * @param string $term The term to add or update.
	 * @return string|\WP_Error The term to add or update or WP_Error on failure.
	 */
	public function pre_insert_term_prevent( $term ) {
		if ( ! is_super_admin() ) {
			$term = new \WP_Error( 'not-allowed', __( 'You are not allowed to add terms.', 'wporg-plugins' ) );
		}

		return $term;
	}

	/**
	 * @param \WP_Query $wp_query The WordPress Query object.
	 */
	public function use_plugins_in_query( $wp_query ) {
		if ( is_admin() || ! $wp_query->is_main_query() ) {
			return;
		}

		if ( empty( $wp_query->query_vars['pagename'] ) && ( empty( $wp_query->query_vars['post_type'] ) || 'post' == $wp_query->query_vars['post_type'] ) ) {
			$wp_query->query_vars['post_type'] = array( 'plugin' );
		}

		if ( empty( $wp_query->query ) ) {
			$wp_query->query_vars['browse'] = 'featured';
		}

		switch ( get_query_var( 'browse' ) ) {
			case 'beta':
				$wp_query->query_vars['plugin_category'] = 'beta';
				break;

			case 'featured':
				$wp_query->query_vars['plugin_category'] = 'featured';
				break;

			case 'favorites':
				break;

			case 'popular':
				break;
		}

		// Re-route the Endpoints to the `content_page` query var.
		if ( ! empty( $wp_query->query['name'] ) ) {
			$plugin_fields = array(
				'installation',
				'faq',
				'screenshots',
				'changelog',
				'stats',
				'developers',
				'other_notes'
			);

			foreach ( $plugin_fields as $plugin_field ) {
				if ( isset( $wp_query->query[ $plugin_field ] ) ) {
					$wp_query->query['content_page'] = $wp_query->query_vars['content_page'] = $plugin_field;
					unset( $wp_query->query[ $plugin_field ], $wp_query->query_vars[ $plugin_field ] );
				}
			}
		}
	}

	/**
	 * Returns the requested page's content.
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_post_content_to_correct_page( $content ) {
		if ( 'plugin' === get_post()->post_type ) {
			$page = get_query_var( 'content_page' );

			$content_pages = $this->split_post_content_into_pages( $content );
			if ( ! isset( $content_pages[ $page ] ) ) {
				$page = 'description';
			}

			$content = $content_pages[ $page ];
		}

		return $content;
	}

	/**
	 * Filter for rest_api_allowed_post_types to enable JP syncing of the CPT
	 *
	 * @param array $allowed_post_types
	 * @return array
	 */
	public function filter_allowed_post_types( $allowed_post_types ) {
		$allowed_post_types[] = 'plugin';
		return $allowed_post_types;
	}

	/**
	 * Filter for pre_update_option_jetpack_options to ensure CPT posts are seen as public and searchable by TP
	 *
	 * @param mixed $new_value
	 * @return mixed
	 */
	public function filter_jetpack_options( $new_value ) {
		if ( is_array( $new_value ) && array_key_exists( 'public', $new_value ) )
			$new_value['public'] = 1;

		return $new_value;
	}


	/**
	 * Shim in some postmeta values which get retrieved from other locations temporarily.
	 *
	 * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
	 *                                     or an array of values.
	 * @param int               $object_id Object ID.
	 * @param string            $meta_key  Meta key.
	 */
	public function filter_shim_postmeta( $value, $object_id, $meta_key ) {
		switch ( $meta_key ) {
			case 'downloads':
				$post = get_post( $object_id );
				$count = Template::get_downloads_count( $post );

				return array( $count );				
				break;
			case 'rating':
				$post = get_post( $object_id );
				// The WordPress.org global ratings functions
				if ( ! function_exists( 'wporg_get_rating_avg' ) ) {
					break;
				}
				$rating = wporg_get_rating_avg( 'plugin', $post->post_name );

				return array( $rating );
				break;
			case 'ratings':
				$post = get_post( $object_id );
				if ( ! function_exists( 'wporg_get_rating_counts' ) ) {
					break;
				}
				$ratings = wporg_get_rating_counts( 'plugin', $post->post_name );

				return array( $ratings );
				break;
		}
		return $value;
	}

	/**
	 * Returns an array of pages based on section comments in the content.
	 *
	 * @param string $content
	 * @return array
	 */
	public function split_post_content_into_pages( $content ) {
		$_pages        = preg_split( "#<!--section=(.+?)-->#", $content, - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$content_pages = array(
			'screenshots' => '[wporg-plugins-screenshots]',
			'stats'       => '[wporg-plugins-stats]',
			'developers'  => '[wporg-plugins-developer]',
		);

		for ( $i = 0; $i < count( $_pages ); $i += 2 ) {

			// Don't overwrite existing tabs.
			if ( ! isset( $content_pages[ $_pages[ $i ] ] ) ) {
				$content_pages[ $_pages[ $i ] ] = $_pages[ $i + 1 ];
			}
		}

		return $content_pages;
	}

	/**
	 * Retrieve the WP_Post object representing a given plugin.
	 *
	 * @param $plugin_slug string|\WP_Post The slug of the plugin to retrieve.
	 * @return \WP_Post|bool
	 */
	static public function get_plugin_post( $plugin_slug ) {
		global $post;
		if ( $plugin_slug instanceof \WP_Post ) {
			return $plugin_slug;
		}
		// Use the global $post object when it matches to avoid hitting the database.
		if ( !empty( $post ) && 'plugin' == $post->post_type && $plugin_slug == $post->post_name ) {
			return $post;
		}

		// get_post_by_slug();
		$posts = get_posts( array(
			'post_type'   => 'plugin',
			'name'        => $plugin_slug,
			'post_status' => array( 'publish', 'pending', 'disabled', 'closed' ),
		) );
		if ( ! $posts ) {
			return false;
		}

		return reset( $posts );
	}

	/**
	 * Create a new post entry for a given plugin slug.
	 *
	 * @param array $plugin_info {
	 *     Array of initial plugin post data, all fields are optional.
	 *
	 *     @type string $title       The title of the plugin.
	 *     @type string $slug        The slug of the plugin.
	 *     @type string $status      The status of the plugin ( 'publish', 'pending', 'disabled', 'closed' ).
	 *     @type int    $author      The ID of the plugin author.
	 *     @type string $description The short description of the plugin.
	 *     @type string $content     The long description of the plugin.
	 *     @type array  $tags        The tags associated with the plugin.
	 *     @type array  $tags        The meta information of the plugin.
	 * }
	 * @return \WP_Post|\WP_Error
	 */
	static public function create_plugin_post( array $plugin_info ) {
		$title   = ! empty( $plugin_info['title'] )       ? $plugin_info['title']       : '';
		$slug    = ! empty( $plugin_info['slug'] )        ? $plugin_info['slug']        : sanitize_title( $title );
		$status  = ! empty( $plugin_info['status'] )      ? $plugin_info['status']      : 'pending';
		$author  = ! empty( $plugin_info['author'] )      ? $plugin_info['author']      : 0;
		$desc    = ! empty( $plugin_info['description'] ) ? $plugin_info['description'] : '';
		$content = ! empty( $plugin_info['content'] )     ? $plugin_info['content']     : '';
		$tags    = ! empty( $plugin_info['tags'] )        ? $plugin_info['tags']        : array();
		$meta    = ! empty( $plugin_info['meta'] )        ? $plugin_info['meta']        : array();

		$id = wp_insert_post( array(
			'post_type'    => 'plugin',
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => $title ?: $slug,
			'post_author'  => $author,
			'post_content' => $content,
			'post_excerpt' => $desc,
			'tags_input'   => $tags,
			'meta_input'   => $meta,
		), true );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return get_post( $id );
	}
}
