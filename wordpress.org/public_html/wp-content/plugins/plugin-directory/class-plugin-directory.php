<?php
namespace WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Admin\Customizations;
use WordPressdotorg\Plugin_Directory\CLI\Tag_To_Category;

/**
 * The main Plugin Directory class, it handles most of the bootstrap and basic operations of the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Directory {

	/**
	 * Local cache for translated content injected into meta.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $i18n_meta = array();

	/**
	 * Fetch the instance of the Plugin_Directory class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Plugin_Directory();
	}

	/**
	 * Plugin_Directory constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 2 );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'rest_api_allowed_post_types', array( $this, 'filter_allowed_post_types' ) );
		add_filter( 'pre_update_option_jetpack_options', array( $this, 'filter_jetpack_options' ) );
		add_action( 'template_redirect', array( $this, 'prevent_canonical_for_plugins' ), 9 );
		add_action( 'template_redirect', array( $this, 'custom_redirects' ) );
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_filter( 'single_term_title', array( $this, 'filter_single_term_title' ) );

		// oEmbed whitlisting.
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_filter( 'oembed_providers', array( $this, 'oembed_whitelist' ) );

		// Shim in postmeta support for data which doesn't yet live in postmeta.
		add_filter( 'get_post_metadata', array( $this, 'filter_shim_postmeta' ), 10, 3 );

		add_filter( 'map_meta_cap', array( __NAMESPACE__ . '\Capabilities', 'map_meta_cap' ), 10, 4 );

		// Load the API routes.
		add_action( 'rest_api_init', array( __NAMESPACE__ . '\API\Base', 'load_routes' ) );

		/*
		 * Load all Admin-specific items.
		 * Cannot be included on `admin_init` to allow access to menu hooks.
		 */
		if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
			Customizations::instance();

			add_action( 'wp_insert_post_data',    array( __NAMESPACE__ . '\Admin\Status_Transitions', 'can_change_post_status' ), 10, 2 );
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
			'labels'       => array(
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
			'supports'        => array( 'comments', 'author' ),
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
			),
		) );

		register_taxonomy( 'plugin_section', 'plugin', array(
			'hierarchical'      => true,
			'query_var'         => 'browse',
			'rewrite'           => false,
			'public'            => true,
			'show_ui'           => current_user_can( 'plugin_set_section' ),
			'show_admin_column' => current_user_can( 'plugin_set_section' ),
			'capabilities'      => array(
				'assign_terms' => 'plugin_set_section',
			),
			'labels'            => array(
				'name' => __( 'Browse', 'wporg-plugins' ),
			),
		) );

		register_taxonomy( 'plugin_category', 'plugin', array(
			'hierarchical'      => true, /* for tax_input[] handling on post saves. */
			'query_var'         => 'plugin_category',
			'rewrite'           => array(
				'hierarchical' => false,
				'slug'         => 'category',
				'with_front'   => false,
				'ep_mask'      => EP_TAGS,
			),
			'labels'            => array(
				'name'          => __( 'Plugin Categories', 'wporg-plugins' ),
				'singular_name' => __( 'Plugin Category',   'wporg-plugins' ),
				'edit_item'     => __( 'Edit Category',     'wporg-plugins' ),
				'update_item'   => __( 'Update Category',   'wporg-plugins' ),
				'add_new_item'  => __( 'Add New Category',  'wporg-plugins' ),
				'new_item_name' => __( 'New Category Name', 'wporg-plugins' ),
				'search_items'  => __( 'Search Categories', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'assign_terms' => 'plugin_set_category',
			),
		) );

		register_taxonomy( 'plugin_built_for', 'plugin', array(
			'hierarchical'      => true, /* for tax_input[] handling on post saves. */
			'query_var'         => 'plugin_built_for',
			'rewrite'           => false,
			'labels'            => array(
				'name' => __( 'Built For', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'meta_box_cb'       => false,
			'capabilities'      => array(
				'assign_terms' => 'plugin_set_category',
			),
		) );

		register_taxonomy( 'plugin_business_model', 'plugin', array(
			'hierarchical'      => true, /* for tax_input[] handling on post saves. */
			'query_var'         => 'plugin_business_model',
			'rewrite'           => false,
			'labels'            => array(
				'name' => __( 'Business Model', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'meta_box_cb'       => false,
			'capabilities'      => array(
				'assign_terms' => 'plugin_set_category',
			),
		) );

		register_taxonomy( 'plugin_contributors', array( 'plugin', 'force-count-to-include-all-post_status' ), array(
			'hierarchical'      => false,
			'query_var'         => 'plugin_contributor',
			'rewrite'           => false,
			'labels'            => array(
				'name' => __( 'Authors', 'wporg-plugins' ),
				'singular_name' => __( 'Author', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'capabilities'      => array(
				'assign_terms' => 'do_not_allow',
			),
		) );

		register_taxonomy( 'plugin_committers', array( 'plugin', 'force-count-to-include-all-post_status' ), array(
			'hierarchical'      => false,
			'query_var'         => 'plugin_committer',
			'rewrite'           => false,
			'labels'            => array(
				'name' => __( 'Committers', 'wporg-plugins' ),
				'singular_name' => __( 'Committer', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'capabilities'      => array(
				'assign_terms' => 'do_not_allow',
			),
		) );

		register_taxonomy( 'plugin_tags', array( 'plugin', 'force-count-to-include-all-post_status' ), array(
			'hierarchical'      => false,
			'query_var'         => 'plugin_tags',
			'rewrite'           => array(
				'hierarchical' => false,
				'slug'         => 'tags',
				'with_front'   => false,
				'ep_mask'      => EP_TAGS,
			),
			'labels'            => array(
				'name'          => __( 'Plugin Tags', 'wporg-plugins' ),
				'singular_name' => __( 'Plugin Tag',   'wporg-plugins' ),
				'edit_item'     => __( 'Edit Tag',     'wporg-plugins' ),
				'update_item'   => __( 'Update Tag',   'wporg-plugins' ),
				'add_new_item'  => __( 'Add New Tag',  'wporg-plugins' ),
				'new_item_name' => __( 'New Tag Name', 'wporg-plugins' ),
				'search_items'  => __( 'Search Tags', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => is_super_admin(),
			'show_admin_column' => false,
			'meta_box_cb'       => false,
			'capabilities'      => array(
				'assign_terms' => 'do_not_allow',
			),
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
		register_post_status( 'approved', array(
			'label'                     => _x( 'Approved', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_approve' ),
			'label_count'               => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'wporg-plugins' ),
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

		// /browse/ should be the popular archive view.
		add_rewrite_rule( '^browse$', 'index.php?browse=popular', 'top' );

		// Create an archive for a users favorites too.
		add_rewrite_rule( '^browse/favorites/([^/]+)$', 'index.php?browse=favorites&favorites_user=$matches[1]', 'top' );

		// Handle plugin admin requests
		add_rewrite_rule( '^([^/]+)/admin/?$', 'index.php?name=$matches[1]&plugin_admin=1', 'top' );

		// Add duplicate search rule which will be hit before the following old-plugin tab rules
		add_rewrite_rule( '^search/([^/]+)/?$', 'index.php?s=$matches[1]', 'top' );

		// Handle the old plugin tabs URLs.
		add_rewrite_rule( '^([^/]+)/(installation|faq|screenshots|changelog|stats|developers|other_notes)/?$', 'index.php?redirect_plugin_tab=$matches[1]/#$matches[2]', 'top' );

		// If changing capabilities around, uncomment this.
		//Capabilities::add_roles();

		// When this plugin is used in the context of a Rosetta site, handle it gracefully.
		if ( 'wordpress.org' != $_SERVER['HTTP_HOST'] && defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
			add_filter( 'option_home',    array( $this, 'rosetta_network_localize_url' ) );
			add_filter( 'option_siteurl', array( $this, 'rosetta_network_localize_url' ) );
		}

		if ( 'en_US' != get_locale() ) {
			add_filter( 'get_term', array( __NAMESPACE__ . '\I18n', 'translate_term' ) );
			add_filter( 'the_content', array( $this, 'translate_post_content' ), 1, 2 );
			add_filter( 'the_title', array( $this, 'translate_post_title' ), 1, 2 );
			add_filter( 'get_the_excerpt', array( $this, 'translate_post_excerpt' ), 1, 2 );
		}

		// Instantiate our copy of the Jetpack_Search class.
		if ( class_exists( 'Jetpack' ) && ! class_exists( 'Jetpack_Search' ) ) {
			require_once( __DIR__ . '/libs/site-search/jetpack-search.php' );
			\Jetpack_Search::instance();
		}

		// When Jetpack syncs, we want to add filters to inject additional metadata for Jetpack, so it syncs for ElasticSearch indexing.
		add_action( 'shutdown', array( $this, 'append_meta_for_jetpack' ), 8 );
	}

	/**
	 * Register the Shortcodes used within the content.
	 */
	public function register_shortcodes() {
		add_shortcode( 'wporg-plugins-developers',  array( __NAMESPACE__ . '\Shortcodes\Developers',  'display' ) );
		add_shortcode( 'wporg-plugin-upload',       array( __NAMESPACE__ . '\Shortcodes\Upload',      'display' ) );
		add_shortcode( 'wporg-plugins-screenshots', array( __NAMESPACE__ . '\Shortcodes\Screenshots', 'display' ) );
		add_shortcode( 'wporg-plugins-reviews',     array( __NAMESPACE__ . '\Shortcodes\Reviews',     'display' ) );
		add_shortcode( 'readme-validator',          array( __NAMESPACE__ . '\Shortcodes\Readme_Validator',     'display' ) );
	}

	/**
	 *  Register the Widgets used plugin detail pages.
	 */
	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\Widgets\Donate'  );
		register_widget( __NAMESPACE__ . '\Widgets\Meta'    );
		register_widget( __NAMESPACE__ . '\Widgets\Ratings' );
		register_widget( __NAMESPACE__ . '\Widgets\Support' );
	}

	/**
	 * Upon plugin activation, set up the current site for acting
	 * as the plugin directory.
	 *
	 * Setting up the site requires setting up the theme and proper
	 * rewrite permastructs.
	 *
	 * @global \WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function activate() {
		global $wp_rewrite;

		// Setup the environment.
		$this->init();

		// %postname% is required.
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// /tags/ & /category/ shouldn't conflict
		$wp_rewrite->set_tag_base( '/post-tags' );
		$wp_rewrite->set_category_base( '/post-categories' );

		// Add our custom capabilitie and roles.
		Capabilities::add_roles();

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
	 *          $site = get_blog_details( WPORG_PLUGIN_DIRECTORY_BLOGID, false );
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
	public function filter_post_type_link( $link, $post ) {
		if ( 'plugin' !== $post->post_type ) {
			return $link;
		}

		return trailingslashit( home_url( $post->post_name ) );
	}

	/**
	 * Filter the permalink for terms to be more useful.
	 *
	 * @param string   $term_link The generated term link.
	 * @param \WP_Term $term      The term the link is for.
	 * @return string|false
	 */
	public function filter_term_link( $term_link, $term ) {
		if ( 'plugin_business_model' == $term->taxonomy ) {
			return false;
		}
		if ( 'plugin_built_for' == $term->taxonomy ) {
			// Term slug = Post Slug = /%postname%/
			return trailingslashit( home_url( $term->slug ) );
		}

		return $term_link;
	}

	/**
	 * @param \WP_Query $wp_query The WordPress Query object.
	 */
	public function pre_get_posts( $wp_query ) {
		if ( is_admin() ) {
			return;
		}

		// Unless otherwise specified, we start off by querying for publish'd plugins.
		if ( empty( $wp_query->query_vars['pagename'] ) && ( empty( $wp_query->query_vars['post_type'] ) || 'post' == $wp_query->query_vars['post_type'] ) ) {
			$wp_query->query_vars['post_type']   = array( 'plugin' );
			$wp_query->query_vars['post_status'] = array( 'publish' );
		}

		// By default, if no query is made, we're querying /browse/featured/
		if ( empty( $wp_query->query ) ) {
			$wp_query->query_vars['browse'] = 'featured';
		}

		// Set up custom queries for the /browse/ URLs
		switch ( $wp_query->get( 'browse' ) ) {
			case 'favorites':
				$favorites_user = wp_get_current_user();
				if ( ! empty( $wp_query->query_vars['favorites_user'] ) ) {
					$favorites_user = $wp_query->query_vars['favorites_user'];
				} elseif ( ! empty( $_GET['favorites_user'] ) ) {
					$favorites_user = $_GET['favorites_user'];
				}

				if ( ! $favorites_user instanceof \WP_User ) {
					$favorites_user = get_user_by( 'slug', $favorites_user );
				}

				if ( $favorites_user ) {
					$wp_query->query_vars['favorites_user'] = $favorites_user->user_nicename;
					$wp_query->query_vars['post_name__in']  = get_user_meta( $favorites_user->ID, 'plugin_favorites', true );
				}

				if ( ! $favorites_user || ! $wp_query->query_vars['post_name__in'] ) {
					$wp_query->query_vars['p'] = -1;
				}
				break;

			case 'new':
				$wp_query->query_vars['orderby'] = 'post_date';
				break;
		}

		// For /browse/ requests, we conditionally need to avoid querying the taxonomy for most views (as it's handled in code above)
		if ( isset( $wp_query->query['browse'] ) && 'beta' != $wp_query->query['browse'] && 'featured' != $wp_query->query['browse'] ) {
			unset( $wp_query->query_vars['browse'] );

			add_filter( 'the_posts', function( $posts, $wp_query ) {

				// Fix the queried object for the archive view.
				if ( ! $wp_query->queried_object && isset( $wp_query->query['browse'] ) ) {
					$wp_query->query_vars['browse'] = $wp_query->query['browse'];
					$wp_query->queried_object       = get_term_by( 'slug', $wp_query->query['browse'], 'plugin_section' );
				}

				return $posts;
			}, 10, 2 );
		}

		// Holds a truthful value when viewing an author archive for the current user, or a plugin reviewer viewing an author archive
		$viewing_own_author_archive = false;

		// Author Archives need to be created
		if ( isset( $wp_query->query['author_name'] ) || isset( $wp_query->query['author'] ) ) {
			$user = isset( $wp_query->query['author_name'] ) ? $wp_query->query['author_name'] : (get_user_by( 'id', $wp_query->query['author'])->user_nicename);

			$viewing_own_author_archive = is_user_logged_in() && ( current_user_can( 'plugin_review' ) || 0 === strcasecmp( $user, wp_get_current_user()->user_nicename ) );

			// Author archives by default list plugins you're a contributor on.
			$wp_query->query_vars['tax_query'] = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'plugin_contributors',
					'field' => 'slug',
					'terms' => $user
				)
			);

			// Author archives for self include plugins you're a committer on, not just publically a contributor
			// Plugin Reviewers also see plugins you're a committer on here.
			if ( $viewing_own_author_archive ) {
				$wp_query->query_vars['tax_query'][] = array(
					'taxonomy' => 'plugin_committers',
					'field' => 'slug',
					'terms' => $user
				);
			}

			// TODO: Make plugins owned by `post_author = $current_user_id` show up here when they're not-publish?

			$wp_query->query_vars['orderby'] = 'post_title';
			$wp_query->query_vars['order'] = 'ASC';

			// Treat it as a taxonomy query now, not the author archive.
			$wp_query->is_author = false;
			$wp_query->is_tax = true;

			unset( $wp_query->query_vars['author_name'], $wp_query->query_vars['author'] );
		}

		// For singular requests, or self-author profile requests allow restricted post_status items to show on the front-end.
		if ( $viewing_own_author_archive || ( is_user_logged_in() && !empty( $wp_query->query_vars['name'] ) ) ) {

			$wp_query->query_vars['post_status'] = array( 'pending', 'approved', 'publish', 'closed', 'disabled' );

			add_filter( 'posts_results', function( $posts, $this_wp_query ) use( $wp_query ) {
				if ( $this_wp_query != $wp_query ) {
					return $posts;
				}

				// TODO: Switch this to the capabilities systems
				$restricted_access_statii = array_diff( $wp_query->query_vars['post_status'], array( 'publish' ) );
				foreach ( $posts as $i => $post ) {
					// If the plugin is not in the restricted statuses list, show it
					if ( 'plugin' != $post->post_type || ! in_array( $post->post_status, $restricted_access_statii, true ) ) {
						continue;
					}

					// Plugin Reviewers can see all sorts of plugins
					if ( current_user_can( 'plugin_review' ) ) {
						continue;
					}

					// Original submitter can always see
					if ( $post->post_author == get_current_user_id() ) {
						continue;
					}

					// Committers (user_login) can always see
					if ( in_array( wp_get_current_user()->user_login, (array) Tools::get_plugin_committers( $post->post_name ), true ) ) {
						continue;
					}

					// Contributors (user_nicename) can always see
					if ( in_array( wp_get_current_user()->user_nicename, (array) wp_list_pluck( get_the_terms( $post, 'plugin_contributors' ), 'slug' ), true ) ) {
						continue;
					}

					// everyone else can't.
					unset( $posts[ $i ] );
				}

				return $posts;
			}, 10, 2 );
		}

		// By default, all archives are sorted by active installs
		if ( $wp_query->is_archive() && empty( $wp_query->query_vars['orderby'] ) ) {
			$wp_query->query_vars['orderby']  = 'meta_value_num';
			$wp_query->query_vars['meta_key'] = '_active_installs';
		}
	}

	/**
	 * Returns the requested page's content, translated.
	 *
	 * @param string $content Post content.
	 * @param string $section Optional. Which readme section to translate.
	 * @param int    $post_id Optional. Post ID. Default: 0.
	 * @return string
	 */
	public function translate_post_content( $content, $section = null, $post_id = 0 ) {
		if ( is_null( $section ) ) {
			return $content;
		}
		return Plugin_I18n::instance()->translate( $section, $content, [ 'post_id' => $post_id ] );
	}

	/**
	 * Returns the requested page's content, translated.
	 *
	 * @param string $title
	 * @param int    $post_id
	 * @return string
	 */
	public function translate_post_title( $title, $post_id = null ) {
		$post = get_post();

		if ( $post instanceof \WP_Post && $post_id === $post->ID ) {
			$title = Plugin_I18n::instance()->translate( 'title', $title, [ 'post_id' => $post ] );
		}

		return $title;
	}

	/**
	 * Returns the requested page's excerpt, translated.
	 *
	 * @param string       $excerpt
	 * @param int|\WP_Post $post
	 * @return string
	 */
	public function translate_post_excerpt( $excerpt, $post ) {
		return Plugin_I18n::instance()->translate( 'excerpt', $excerpt, [ 'post_id' => $post ] );
	}

	/**
	 * Shutdown action that will add a filter to inject additional postmeta containing translated content if Jetpack
	 * is syncing.
	 */
	public function append_meta_for_jetpack() {

		/*
		 * Guess if a Jetpack sync is scheduled to run. It runs during shutdown at a lower priority than this action,
		 * so we can get in first.Fetching the extra meta to inject is expensive, so we only want to do this if a sync
		 * is likely.
		 */
		if ( class_exists( 'Jetpack' ) && ! empty( \Jetpack::$instance->sync->sync ) ) {
			add_filter( 'wporg_plugins_custom_meta_fields', array( $this, 'filter_post_meta_i18n' ), 10, 2 );
		}
	}

	/**
	 * Filter for wporg_plugins_custom_meta_fields to inject translated content for ES.
	 *
	 * @global string $locale Current locale.
	 *
	 * @param array $meta
	 * @param int   $post_id
	 * @return array
	 */
	public function filter_post_meta_i18n( $meta, $post_id ) {

		// Prevent recursion and repeat runs.
		remove_filter( 'wporg_plugins_custom_meta_fields', array( $this, 'filter_post_meta_i18n' ) );

		if ( empty( $this->i18n_meta[ $post_id ] ) ) {

			$locales_to_sync = array( 
				// Default locales to translate, just in case we can't determine the available ones
				'fr_FR',
				'es_ES',
			);
			$post = get_post( $post_id );
			if ( $post ) {
				$translations = Plugin_I18n::find_all_translations_for_plugin( $post->post_name, 'stable-readme', 10 ); // at least 10% translated
				if ( $translations )
					$locales_to_sync = $translations;
			}

			global $locale;
			$_locale = $locale;

			foreach ( $locales_to_sync as $locale ) {
				$the_title = Plugin_I18n::instance()->translate( 'title', get_the_title( $post_id ), [ 'post_id' => $post_id ] );
				if ( $the_title && $the_title != get_the_title( $post_id ) ) {
					$this->i18n_meta[ $post_id ][ 'title_' . $locale ] = $the_title;
				}

				$the_excerpt = $this->translate_post_excerpt( get_the_excerpt( $post_id ), $post_id );
				if ( $the_excerpt && $the_excerpt != get_the_excerpt( $post_id ) ) {
					$this->i18n_meta[ $post_id ][ 'excerpt_' . $locale ] = $the_excerpt;
				}

				// Split up the content to translate it in sections.
				$the_content = array();
				$sections = $this->split_post_content_into_pages( get_the_content( $post_id ) );
				foreach ( $sections as $section => $section_content ) {
					$translated_section = $this->translate_post_content( $section_content, $section, $post_id );
					if ( $translated_section && $translated_section != $section_content ) {
						$the_content[] = $translated_section;
					}
				}
				if ( !empty( $the_content ) )
					$this->i18n_meta[ $post_id ][ 'content_' . $locale ] = implode( $the_content );
			}

			$locale = $_locale;

		}

		if ( is_array( $this->i18n_meta[ $post_id ] ) )
			$meta = array_merge( $meta, array_keys( $this->i18n_meta[ $post_id ] ) );

		add_filter( 'wporg_plugins_custom_meta_fields', array( $this, 'filter_post_meta_i18n' ), 10, 2 );

		return $meta;
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
	 * Filters the available public query vars to add our custom parameters.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public function filter_query_vars( $vars ) {
		$vars[] = 'favorites_user';
		$vars[] = 'redirect_plugin_tab';
		$vars[] = 'plugin_admin';

		return $vars;
	}

	/**
	 * Filters the term names for archive headers to be more useful.
	 *
	 * @param string $name The Term Name.
	 * @return string The Term Name.
	 */
	public function filter_single_term_title( $name ) {
		$term = get_queried_object();
		if ( ! $term || ! isset( $term->taxonomy ) ) {
			return $name;
		}

		switch ( $term->taxonomy ) {
			case 'plugin_contributors':
			case 'plugin_committers':
				$user = get_user_by( 'slug', $term->slug );
				$name = $user->display_name;
				break;
		}

		return $name;
	}

	/**
	 * Filter for pre_update_option_jetpack_options to ensure CPT posts are seen as public and searchable by TP
	 *
	 * @param mixed $new_value
	 * @return mixed
	 */
	public function filter_jetpack_options( $new_value ) {
		if ( is_array( $new_value ) && array_key_exists( 'public', $new_value ) ) {
			$new_value['public'] = 1;
		}

		return $new_value;
	}

	/**
	 * Prevents Canonical redirecting to other plugins on 404's.
	 */
	function prevent_canonical_for_plugins() {
		if ( is_404() ) {
			remove_action( 'template_redirect', 'redirect_canonical' );
		}
	}

	/**
	 * Handles all the custom redirects needed in the Plugin Directory.
	 */
	function custom_redirects() {

		// Handle a redirect for /$plugin/$tab_name/ to /$plugin/#$tab_name.
		if ( get_query_var( 'redirect_plugin_tab' ) ) {
			wp_safe_redirect( site_url( get_query_var( 'redirect_plugin_tab' ) ) );
			die();
		}

		// We've disabled WordPress's default 404 redirects, so we'll handle them ourselves.
		if ( is_404() ) {

			// [1] => plugins [2] => example-plugin-name [2..] => random().
			$path = explode( '/', $_SERVER['REQUEST_URI'] );

			if ( 'tags' === $path[2] ) {
				if ( isset( $path[3] ) ) {
					wp_safe_redirect( home_url( '/search/' . urlencode( $path[3] ) . '/' ) );
					die();
				} else {
					wp_safe_redirect( home_url( '/' ) );
					die();
				}
			}

			// The about page is now over at /developers/.
			if ( 'about' === $path[2] ) {
				wp_safe_redirect( home_url( '/developers/' . ( ( isset( $path[3] ) && 'add' == $path[3] ) ? 'add/' : '' ) ) );
				die();
			}

			// Otherwise, handle a plugin redirect.
			if ( $plugin = self::get_plugin_post( $path[2] ) ) {
				$permalink = get_permalink( $plugin->ID );
				if ( parse_url( $permalink, PHP_URL_PATH ) != $_SERVER['REQUEST_URI'] ) {
					wp_safe_redirect( $permalink );
					die();
				}
			}
		}

		// If it's an old search query, handle that too.
		if ( 'search.php' == get_query_var( 'name' ) && isset( $_GET['q'] ) ) {
			wp_safe_redirect( site_url( '/search/' . urlencode( wp_unslash( $_GET['q'] ) ) . '/' ) );
			die();
		}

		// new-style Search links.
		if ( get_query_var( 's' ) && isset( $_GET['s'] ) ) {
			wp_safe_redirect( site_url( '/search/' . urlencode( get_query_var( 's' ) ) . '/' ) );
			die();
		}

		// TODO: Switch this to the capabilities systems, check if post_author should access
		// Filter access to the plugin administration area. Only certain users are allowed access.
		if ( get_query_var( 'plugin_admin' ) && ! current_user_can( 'plugin_review' ) ) {
			$post = Plugin_Directory::get_plugin_post( get_query_var( 'name' ) );
			if (
				// Logged out users can't access plugin admin
				! is_user_logged_in() ||
				// Allow access to Committers OR Contributors.
				! (
					// Committers can access plugin admin
					in_array( wp_get_current_user()->user_login, (array) Tools::get_plugin_committers( $post->post_name ), true ) ||
					// Contributors can access plugin admin (but will have a more limited access)
					in_array( wp_get_current_user()->user_nicename, (array) wp_list_pluck( get_the_terms( $post, 'plugin_contributors' ), 'slug' ), true )
				)
			) {
				wp_safe_redirect( get_permalink( $post ) );
				die();
			}
		}
	}

	/**
	 * Whitelists the oembed providers whitelist.
	 *
	 * Limited to providers that add video support to plugin readme files.
	 *
	 * @param array $providers An array of popular oEmbed providers.
	 * @return array
	 */
	public function oembed_whitelist( $providers ) {
		return array_filter( $providers, function ( $provider ) {
			$whitelist = array(
				'youtube.com',
				'vimeo.com',
				'wordpress.com',
				'wordpress.tv',
				'vine.co',
				'soundcloud.com',
				'instagram.com',
				'mixcloud.com',
				'cloudup.com',
			);

			foreach ( $whitelist as $url ) {
				if ( false !== strpos( $provider[0], $url ) ) {
					return true;
				}
			}

			return false;
		} );
	}

	/**
	 * Shim in some postmeta values which get retrieved from other locations temporarily.
	 *
	 * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
	 *                                     or an array of values.
	 * @param int               $object_id Object ID.
	 * @param string            $meta_key  Meta key.
	 * @return array
	 */
	public function filter_shim_postmeta( $value, $object_id, $meta_key ) {
		switch ( $meta_key ) {
			case 'downloads':
				$post  = get_post( $object_id );
				$count = Template::get_downloads_count( $post );
				$value = array( $count );
				break;

			case 'rating':
				$post = get_post( $object_id );
				// The WordPress.org global ratings functions
				if ( ! method_exists( '\WPORG_Ratings', 'get_avg_rating' ) ) {
					break;
				}

				$rating = \WPORG_Ratings::get_avg_rating( 'plugin', $post->post_name );
				$value  = array( $rating );
				break;

			case 'ratings':
				$post = get_post( $object_id );
				if ( ! method_exists( '\WPORG_Ratings', 'get_rating_counts' ) ) {
					break;
				}

				$ratings = \WPORG_Ratings::get_rating_counts( 'plugin', $post->post_name );
				$value   = array( $ratings );
				break;

			case 'tested':
				// For the tested field, we bump up the minor release to the latest compatible minor release.
				if ( function_exists( 'wporg_get_version_equivalents' ) ) {

					// As we're on a pre-filter, we'll have to detach, fetch and reattach..
					remove_filter( 'get_post_metadata', array( $this, 'filter_shim_postmeta' ) );
					$value = get_metadata( 'post', $object_id, $meta_key );
					add_filter( 'get_post_metadata', array( $this, 'filter_shim_postmeta' ), 10, 3 );

					foreach ( wporg_get_version_equivalents() as $latest_compatible_version => $compatible_with ) {
						if ( in_array( (string)$value[0], $compatible_with, true ) ) {
							$value[0] = $latest_compatible_version;
							break;
						}
					}
				}
				break;

			case false:

				// In the event $meta_key is false, the caller wants all meta fields, so we'll append our custom ones here too.
				remove_filter( 'get_post_metadata', array( $this, 'filter_shim_postmeta' ) );

				// Fetch the existing ones from the database
				$value = get_metadata( 'post', $object_id, $meta_key );

				// Re-attach ourselves for next time!
				add_filter( 'get_post_metadata', array( $this, 'filter_shim_postmeta' ), 10, 3 );

				$custom_meta_fields = array( 'downloads', 'rating', 'ratings', 'tested' );
				$custom_meta_fields = apply_filters( 'wporg_plugins_custom_meta_fields', $custom_meta_fields, $object_id );

				foreach ( $custom_meta_fields as $key ) {

					// When WordPress calls `get_post_meta( $post_id, false )` it expects an array of maybe_serialize()'d data.
					$shimed_data = $this->filter_shim_postmeta( false, $object_id, $key );
					if ( $shimed_data ) {
						$value[ $key ][0] = (string) maybe_serialize( $shimed_data[0] );
					}
				}
				break;

			default:
				if ( isset( $this->i18n_meta[ $object_id ][ $meta_key ] ) ) {
					return array( $this->i18n_meta[ $object_id ][ $meta_key ] );
				}
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
			'developers'  => '[wporg-plugins-developers]',
			'reviews'     => '[wporg-plugins-reviews]',
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
	 * @static
	 * @global \WP_Post $post WordPress post object.
	 *
	 * @param string|\WP_Post $plugin_slug The slug of the plugin to retrieve.
	 * @return \WP_Post|bool
	 */
	public static function get_plugin_post( $plugin_slug ) {
		global $post;

		if ( $plugin_slug instanceof \WP_Post ) {
			return $plugin_slug;
		}

		// Use the global $post object when it matches to avoid hitting the database.
		if ( ! empty( $post ) && 'plugin' == $post->post_type && $plugin_slug == $post->post_name ) {
			return $post;
		}

		$plugin_slug = sanitize_title_for_query( $plugin_slug );

		if ( false !== ( $post_id = wp_cache_get( $plugin_slug, 'plugin-slugs' ) ) && ( $post = get_post( $post_id ) ) ) {
			// We have a $post.
		} else {

			// get_post_by_slug();
			$posts = get_posts( array(
				'post_type'   => 'plugin',
				'name'        => $plugin_slug,
				'post_status' => array( 'publish', 'pending', 'disabled', 'closed', 'draft', 'approved' ),
			) );

			if ( ! $posts ) {
				$post = false;
				wp_cache_add( 0, $plugin_slug, 'plugin-slugs' );
			} else {
				$post = reset( $posts );
				wp_cache_add( $post->ID, $plugin_slug, 'plugin-slugs' );
			}
		}

		return $post;
	}

	/**
	 * Create a new post entry for a given plugin slug.
	 * 
	 * @static
	 *
	 * @param array $args {
	 *     An array of elements that make up a post to insert.
	 *
	 *     @type int    $ID                    The post ID. If equal to something other than 0,
	 *                                         the post with that ID will be updated. Default 0.
	 *     @type int    $post_author           The ID of the user who added the post. Default is
	 *                                         the current user ID.
	 *     @type string $post_date             The date of the post. Default is the current time.
	 *     @type string $post_date_gmt         The date of the post in the GMT timezone. Default is
	 *                                         the value of `$post_date`.
	 *     @type mixed  $post_content          The post content. Default empty.
	 *     @type string $post_content_filtered The filtered post content. Default empty.
	 *     @type string $post_title            The post title. Default empty.
	 *     @type string $post_excerpt          The post excerpt. Default empty.
	 *     @type string $post_status           The post status. Default 'draft'.
	 *     @type string $post_type             The post type. Default 'post'.
	 *     @type string $comment_status        Whether the post can accept comments. Accepts 'open' or 'closed'.
	 *                                         Default is the value of 'default_comment_status' option.
	 *     @type string $ping_status           Whether the post can accept pings. Accepts 'open' or 'closed'.
	 *                                         Default is the value of 'default_ping_status' option.
	 *     @type string $post_password         The password to access the post. Default empty.
	 *     @type string $post_name             The post name. Default is the sanitized post title
	 *                                         when creating a new post.
	 *     @type string $to_ping               Space or carriage return-separated list of URLs to ping.
	 *                                         Default empty.
	 *     @type string $pinged                Space or carriage return-separated list of URLs that have
	 *                                         been pinged. Default empty.
	 *     @type string $post_modified         The date when the post was last modified. Default is
	 *                                         the current time.
	 *     @type string $post_modified_gmt     The date when the post was last modified in the GMT
	 *                                         timezone. Default is the current time.
	 *     @type int    $post_parent           Set this for the post it belongs to, if any. Default 0.
	 *     @type int    $menu_order            The order the post should be displayed in. Default 0.
	 *     @type string $post_mime_type        The mime type of the post. Default empty.
	 *     @type string $guid                  Global Unique ID for referencing the post. Default empty.
	 *     @type array  $post_category         Array of category names, slugs, or IDs.
	 *                                         Defaults to value of the 'default_category' option.
	 *     @type array  $tax_input             Array of taxonomy terms keyed by their taxonomy name. Default empty.
	 *     @type array  $meta_input            Array of post meta values keyed by their post meta key. Default empty.
	 * }
	 * @return \WP_Post|\WP_Error
	 */
	public static function create_plugin_post( array $args ) {
		$title = $args['post_title'] ?: $args['post_name'];
		$slug  = $args['post_name']  ?: sanitize_title( $title );

		$args = wp_parse_args( $args, array(
			'post_title'        => $title,
			'post_name'         => $slug,
			'post_type'         => 'plugin',
			'post_date'         => '',
			'post_date_gmt'     => '',
			'post_modified'     => '',
			'post_modified_gmt' => '',
		) );

		$result = wp_insert_post( $args, true );

		if ( ! is_wp_error( $result ) ) {
			wp_cache_set( $result, $slug, 'plugin-slugs' );
			$result = get_post( $result );
		}

		return $result;
	}
}
