<?php
namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\Admin\Customizations;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Admin\Tools\Author_Cards;
use WordPressdotorg\Plugin_Directory\Admin\Tools\Stats_Report;

/**
 * The main Plugin Directory class, it handles most of the bootstrap and basic operations of the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Directory {

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
		add_action( 'init', array( $this, 'remove_other_shortcodes' ), 999 );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 2 );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'found_posts', array( $this, 'filter_found_posts' ), 10, 2 );
		add_filter( 'rest_api_allowed_post_types', array( $this, 'filter_allowed_post_types' ) );
		add_filter( 'pre_update_option_jetpack_options', array( $this, 'filter_jetpack_options' ) );
		add_filter( 'jetpack_sitemap_post_types', array( $this, 'jetpack_sitemap_post_types' ) );
		add_filter( 'jetpack_sitemap_skip_post', array( $this, 'jetpack_sitemap_skip_post' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'prevent_canonical_for_plugins' ), 9 );
		add_action( 'template_redirect', array( $this, 'custom_redirects' ), 1 );
		add_action( 'template_redirect', array( $this, 'geopattern_icon_route' ), 0 );
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ), 1 );
		add_filter( 'single_term_title', array( $this, 'filter_single_term_title' ) );
		add_filter( 'the_content', array( $this, 'filter_rel_nofollow_ugc' ) );
		add_action( 'wp_head', array( Template::class, 'json_ld_schema' ), 1 );
		add_action( 'wp_head', array( Template::class, 'hreflang_link_attributes' ), 2 );

		// Add no-index headers where appropriate.
		add_filter( 'wporg_noindex_request', [ Template::class, 'should_noindex_request' ] );

		// Fix the Canonical link when needed.
		add_action( 'wporg_canonical_url', [ Template::class, 'wporg_canonical_url' ] );

		// Cron tasks.
		new Jobs\Manager();

		// Search
		Plugin_Search::instance();

		// Add upload size limit to limit plugin ZIP file uploads to 10M
		add_filter( 'upload_size_limit', function( $size ) {
			return 10 * MB_IN_BYTES;
		} );

		// oEmbed whitlisting.
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_filter( 'oembed_providers', array( $this, 'oembed_whitelist' ) );

		// Capability mapping
		add_filter( 'map_meta_cap', array( __NAMESPACE__ . '\Capabilities', 'map_meta_cap' ), 10, 4 );

		// Load the API routes.
		add_action( 'rest_api_init', array( __NAMESPACE__ . '\API\Base', 'init' ) );

		// Allow post_modified not to be modified when we don't specifically bump it.
		add_filter( 'wp_insert_post_data', array( $this, 'filter_wp_insert_post_data' ), 10, 2 );

		add_filter( 'jetpack_active_modules', function( $modules ) {
			// Enable Jetpack Search
			#$modules[] = 'search';

			// Disable Jetpack Sitemaps on Rosetta sites.
			if ( !empty( $GLOBALS['rosetta'] ) ) {
				if ( false !== ( $i = array_search( 'sitemaps', $modules ) ) ) {
					unset( $modules[$i] );
				}
			}

			return array_unique( $modules );
		} );

/*
		// Temporarily disabled to see if this is still needed / causing issues.
		// Work around caching issues
		add_filter( 'pre_option_jetpack_sync_full__started', array( $this, 'bypass_options_cache' ), 10, 2 );
		add_filter( 'default_option_jetpack_sync_full__started', '__return_null' );
		add_filter( 'pre_option_jetpack_sync_full__params', array( $this, 'bypass_options_cache' ), 10, 2 );
		add_filter( 'default_option_jetpack_sync_full__params', '__return_null' );
		add_filter( 'pre_option_jetpack_sync_full__queue_finished', array( $this, 'bypass_options_cache' ), 10, 2 );
		add_filter( 'default_option_jetpack_sync_full__queue_finished', '__return_null' );
		add_filter( 'pre_option_jetpack_sync_full__send_started', array( $this, 'bypass_options_cache' ), 10, 2 );
		add_filter( 'default_option_jetpack_sync_full__send_started', '__return_null' );
		add_filter( 'pre_option_jetpack_sync_full__finished', array( $this, 'bypass_options_cache' ), 10, 2 );
		add_filter( 'default_option_jetpack_sync_full__finished', '__return_null' );
*/

		// Fix login URLs in admin bar
		add_filter( 'login_url', array( $this, 'fix_login_url' ), 10, 3 );

		/*
		 * Load all Admin-specific items.
		 * Cannot be included on `admin_init` to allow access to menu hooks.
		 */
		if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
			Customizations::instance();
			Author_Cards::instance();
			Stats_Report::instance();

			add_action( 'wp_insert_post_data', array( __NAMESPACE__ . '\Admin\Status_Transitions', 'can_change_post_status' ), 10, 2 );
			add_action( 'transition_post_status', array( __NAMESPACE__ . '\Admin\Status_Transitions', 'instance' ) );
		}

		register_activation_hook( PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Filters `wp_insert_post()` to respect the presented data.
	 * This function overrides `wp_insert_post()`s constant updating of
	 * the post_modified fields.
	 *
	 * @param array $data    The data to be inserted into the database.
	 * @param array $postarr The raw data passed to `wp_insert_post()`.
	 *
	 * @return array The data to insert into the database.
	 */
	public function filter_wp_insert_post_data( $data, $postarr ) {
		if ( 'plugin' === $postarr['post_type'] ) {
			$data['post_modified']     = $postarr['post_modified'];
			$data['post_modified_gmt'] = $postarr['post_modified_gmt'];
		}
		return $data;
	}

	/**
	 * Set up the Plugin Directory.
	 */
	public function init() {
		load_plugin_textdomain( 'wporg-plugins' );

		wp_cache_add_global_groups( 'wporg-plugins' );

		register_post_type( 'plugin', array(
			'labels'       => array(
				'name'               => __( 'Repo Plugins', 'wporg-plugins' ),
				'singular_name'      => __( 'Repo Plugin', 'wporg-plugins' ),
				'menu_name'          => __( 'Repo Plugins', 'wporg-plugins' ),
				'add_new'            => __( 'Add New', 'wporg-plugins' ),
				'add_new_item'       => __( 'Add New Plugin', 'wporg-plugins' ),
				'new_item'           => __( 'New Plugin', 'wporg-plugins' ),
				'view_item'          => __( 'View Plugin', 'wporg-plugins' ),
				'search_items'       => __( 'Search Plugins', 'wporg-plugins' ),
				'not_found'          => __( 'No plugins found', 'wporg-plugins' ),
				'not_found_in_trash' => __( 'No plugins found in Trash', 'wporg-plugins' ),

				// Context only available in admin, not in toolbar.
				'edit_item'          => is_admin() ? __( 'Editing Plugin:', 'wporg-plugins' ) : __( 'Edit Plugin', 'wporg-plugins' ),
			),
			'description'  => __( 'A Repo Plugin', 'wporg-plugins' ),
			'supports'     => array( 'comments', 'author', 'custom-fields' ),
			'public'       => true,
			'show_ui'      => true,
			'show_in_rest' => true,
			'has_archive'  => true,
			'rewrite'      => false,
			'menu_icon'    => 'dashicons-admin-plugins',
			'capabilities' => array(
				'edit_post'          => 'plugin_edit',
				'read_post'          => 'read',
				'edit_posts'         => 'plugin_dashboard_access',
				'edit_others_posts'  => 'plugin_edit_others',
				'publish_posts'      => 'plugin_approve',
				'read_private_posts' => 'do_not_allow',
				'delete_posts'       => is_super_admin() ? 'manage_options' : 'do_not_allow',
				'create_posts'       => 'do_not_allow',
			),
		) );

		register_taxonomy( 'plugin_section', 'plugin', array(
			'hierarchical'      => true,
			'query_var'         => 'browse',
			'rewrite'           => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
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
				'singular_name' => __( 'Plugin Category', 'wporg-plugins' ),
				'edit_item'     => __( 'Edit Category', 'wporg-plugins' ),
				'update_item'   => __( 'Update Category', 'wporg-plugins' ),
				'add_new_item'  => __( 'Add New Category', 'wporg-plugins' ),
				'new_item_name' => __( 'New Category Name', 'wporg-plugins' ),
				'search_items'  => __( 'Search Categories', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
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
			'show_ui'           => false,
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
			'show_ui'           => false,
			'show_admin_column' => false,
			'meta_box_cb'       => false,
			'capabilities'      => array(
				'assign_terms' => 'plugin_set_category',
			),
		) );

		register_taxonomy( 'plugin_contributors', array( 'plugin', 'force-count-to-include-all-post_status' ), array(
			'hierarchical'      => false,
			'query_var'         => 'plugin_contributor',
			'sort'              => true,
			'rewrite'           => false,
			'labels'            => array(
				'name'          => __( 'Contributors', 'wporg-plugins' ),
				'singular_name' => __( 'Contributor', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'assign_terms' => 'do_not_allow',
			),
		) );

		register_taxonomy( 'plugin_committers', array( 'plugin', 'force-count-to-include-all-post_status' ), array(
			'hierarchical'      => false,
			'query_var'         => 'plugin_committer',
			'rewrite'           => false,
			'labels'            => array(
				'name'          => __( 'Committers', 'wporg-plugins' ),
				'singular_name' => __( 'Committer', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'assign_terms' => 'do_not_allow',
			),
		) );

		register_taxonomy( 'plugin_support_reps', array( 'plugin', 'force-count-to-include-all-post_status' ), array(
			'hierarchical'      => false,
			'query_var'         => 'plugin_support_rep',
			'rewrite'           => false,
			'labels'            => array(
				'name'          => __( 'Support Reps', 'wporg-plugins' ),
				'singular_name' => __( 'Support Rep', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
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
				'singular_name' => __( 'Plugin Tag', 'wporg-plugins' ),
				'edit_item'     => __( 'Edit Tag', 'wporg-plugins' ),
				'update_item'   => __( 'Update Tag', 'wporg-plugins' ),
				'add_new_item'  => __( 'Add New Tag', 'wporg-plugins' ),
				'new_item_name' => __( 'New Tag Name', 'wporg-plugins' ),
				'search_items'  => __( 'Search Tags', 'wporg-plugins' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'meta_box_cb'       => false,
			'capabilities'      => array(
				'assign_terms' => 'do_not_allow',
			),
		) );

		register_post_status( 'new', array(
			'label'                     => _x( 'Pending Initial Review', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_review' ),
			'label_count'               => _n_noop( 'Pending Initial Review <span class="count">(%s)</span>', 'Pending Initial Review <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'pending', array(
			'label'                     => _x( 'Pending', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_review' ),
			'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'disabled', array(
			'label'                     => _x( 'Disabled', 'plugin status', 'wporg-plugins' ),
			'public'                    => true,
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
			'public'                    => true,
			'show_in_admin_status_list' => current_user_can( 'plugin_close' ),
			'label_count'               => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'rejected', array(
			'label'                     => _x( 'Rejected', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => current_user_can( 'plugin_reject' ),
			'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );

		/**
		 * TODO
		 * Use register_rest_field() to add array and object meta data to the API:
		 * ratings, upgrade_notice, contributors, screenshots, sections, assets_screenshots,
		 * assets_icons, assets_banners,
		 */

		register_meta( 'post', 'rating', array(
			'type'         => 'number',
			'description'  => __( 'Overall rating of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// todo 'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'active_installs', array(
			'type'              => 'integer',
			'description'       => __( 'Number of installations.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		) );

		register_meta( 'post', 'downloads', array(
			'type'              => 'integer',
			'description'       => __( 'Number of downloads.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		) );

		register_meta( 'post', 'tested', array(
			'description'  => __( 'The version of WordPress the plugin was tested with.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'requires', array(
			'description'  => __( 'The minimum version of WordPress the plugin needs to run.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'requires_php', array(
			'description'  => __( 'The minimum version of PHP the plugin needs to run.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'stable_tag', array(
			'description'  => __( 'Stable version of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'donate_link', array(
			'description'       => __( 'Link to donate to the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		) );

		register_meta( 'post', 'version', array(
			'description'  => __( 'Current stable version.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'header_name', array(
			'description'  => __( 'Name of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'header_plugin_uri', array(
			'description'       => __( 'URL to the homepage of the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		) );

		register_meta( 'post', 'header_name', array(
			'description'  => __( 'Name of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'header_author', array(
			'description'  => __( 'Name of the plugin author.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'header_author_uri', array(
			'description'       => __( 'URL to the homepage of the author.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		) );

		register_meta( 'post', 'header_description', array(
			'description'  => __( 'Description of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'assets_icons', array(
			'type'         => 'UserDefinedarray',
			'description'  => __( 'Icon images of the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'assets_banners_color', array(
			'description'  => __( 'Fallback color for the plugin.', 'wporg-plugins' ),
			'single'       => true,
			// TODO 'sanitize_callback' => 'esc_url_raw',
			'show_in_rest' => true,
		) );

		register_meta( 'post', 'support_threads', array(
			'type'              => 'integer',
			'description'       => __( 'Amount of support threads for the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		) );

		register_meta( 'post', 'support_threads_resolved', array(
			'type'              => 'integer',
			'description'       => __( 'Amount of resolved support threads for the plugin.', 'wporg-plugins' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'show_in_rest'      => true,
		) );

		// Add the browse/* views.
		add_rewrite_tag( '%browse%', '(featured|popular|beta|blocks|block|new|favorites|adopt-me|updated)' );
		add_permastruct( 'browse', 'browse/%browse%' );

		// Create an archive for a users favorites too.
		add_rewrite_rule( '^browse/favorites/([^/]+)$', 'index.php?browse=favorites&favorites_user=$matches[1]', 'top' );

		// Add duplicate search rule which will be hit before the following old-plugin tab rules
		add_rewrite_rule( '^search/([^/]+)/?$', 'index.php?s=$matches[1]', 'top' );

		// Add a rule for generated plugin icons. geopattern-icon/demo.svg | geopattern-icon/demo_abc123.svg
		add_rewrite_rule( '^geopattern-icon/([^/_]+)(_([a-f0-9]{6}))?\.svg$', 'index.php?name=$matches[1]&geopattern_icon=$matches[3]', 'top' );

		// Handle plugin admin requests
		add_rewrite_rule( '^([^/]+)/advanced/?$', 'index.php?name=$matches[1]&plugin_advanced=1', 'top' );

		// Handle the old plugin tabs URLs.
		add_rewrite_rule( '^([^/]+)/(installation|faq|screenshots|changelog|stats|developers|other_notes)/?$', 'index.php?redirect_plugin=$matches[1]&redirect_plugin_tab=$matches[2]', 'top' );

		// Handle content for broken clients that send #'s to the server
		add_rewrite_rule( '^([^/]+)/\#(.*)/?$', 'index.php?name=$matches[1]', 'top' );

		// If changing capabilities around, uncomment this.
		// Capabilities::add_roles();

		// Remove the /admin$ redirect to wp-admin
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		// disable feeds
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );

		add_filter( 'get_term', array( __NAMESPACE__ . '\I18n', 'translate_term' ) );
		add_filter( 'the_content', array( $this, 'translate_post_content' ), 1, 2 );
		add_filter( 'the_title', array( $this, 'translate_post_title' ), 1, 2 );
		add_filter( 'single_post_title', array( $this, 'translate_post_title' ), 1, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'translate_post_excerpt' ), 1, 2 );

	}

	/**
	 * Register the Shortcodes used within the content.
	 */
	public function register_shortcodes() {
		add_shortcode( 'wporg-plugins-developers', array( __NAMESPACE__ . '\Shortcodes\Developers', 'display' ) );
		add_shortcode( 'wporg-plugin-upload', array( __NAMESPACE__ . '\Shortcodes\Upload', 'display' ) );
		add_shortcode( 'wporg-plugins-screenshots', array( __NAMESPACE__ . '\Shortcodes\Screenshots', 'display' ) );
		add_shortcode( 'wporg-plugins-reviews', array( __NAMESPACE__ . '\Shortcodes\Reviews', 'display' ) );
		add_shortcode( 'readme-validator', array( __NAMESPACE__ . '\Shortcodes\Readme_Validator', 'display' ) );
		add_shortcode( 'block-validator', array( __NAMESPACE__ . '\Shortcodes\Block_Validator', 'display' ) );

		add_shortcode( Shortcodes\Release_Confirmation::SHORTCODE, array( __NAMESPACE__ . '\Shortcodes\Release_Confirmation', 'display' ) );
		add_action( 'template_redirect', array( __NAMESPACE__ . '\Shortcodes\Release_Confirmation', 'template_redirect' ) );
	}

	/**
	 * deregister any shortcodes which we haven't explicitly allowed.
	 */
	public function remove_other_shortcodes() {
		global $shortcode_tags;
		$allowed_shortcodes = array(
			'youtube',
			'vimeo',
			'wporg-plugins-developers',
			'wporg-plugin-upload',
			'wporg-plugins-screenshots',
			'wporg-plugins-reviews',
			'readme-validator',
			'block-validator',
			'release-confirmation',
		);

		$not_allowed_shortcodes = array_diff( array_keys( $shortcode_tags ), $allowed_shortcodes );
		foreach ( $not_allowed_shortcodes as $tag ) {
			remove_shortcode( $tag );
		}

		// remove special embed shortcode handling
		remove_filter( 'the_content', array( $GLOBALS['wp_embed'], 'run_shortcode' ), 8 );
	}

	/**
	 *  Register the Widgets used plugin detail pages.
	 */
	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\Widgets\Donate' );
		register_widget( __NAMESPACE__ . '\Widgets\Meta' );
		register_widget( __NAMESPACE__ . '\Widgets\Ratings' );
		register_widget( __NAMESPACE__ . '\Widgets\Support' );
		register_widget( __NAMESPACE__ . '\Widgets\Committers' );
		register_widget( __NAMESPACE__ . '\Widgets\Contributors' );
		register_widget( __NAMESPACE__ . '\Widgets\Support_Reps' );
		register_widget( __NAMESPACE__ . '\Widgets\Adopt_Me' );
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

		// browse/%
		if ( 'plugin_section' == $term->taxonomy && 'favorites' == $term->slug ) {
			return trailingslashit( home_url( 'browse/favorites/' . get_query_var( 'favorites_user' ) ) );
		} elseif ( 'plugin_section' == $term->taxonomy ) {
			return trailingslashit( home_url( 'browse/' . $term->slug ) );
		}

		// author/%
		if ( 'plugin_contributors' == $term->taxonomy ) {
			return trailingslashit( home_url( 'author/' . $term->slug ) );
		}

		return $term_link;
	}

	/**
	 * Filter content to make links rel="nofollow ugc" on plugin pages only
	 *
	 * @param string $content    The content.
	 * @return string
	 */
	public function filter_rel_nofollow_ugc( $content ) {
		if ( get_post_type() == 'plugin' ) {
			// regex copied from wp_rel_ugc(). Not calling that function because it messes with slashes.
			$content = preg_replace_callback(
				'|<a (.+?)>|i',
				function( $matches ) {
						return wp_rel_callback( $matches, 'nofollow ugc' );
				},
				$content
			);
		}

		return $content;
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

		// For any invalid values passed to browse, set it to featured instead
		if ( !empty ( $wp_query->query ['browse'] ) &&
		     !in_array( $wp_query->query['browse'], array( 'featured', 'popular', 'beta', 'blocks', 'block', 'new', 'favorites', 'adopt-me', 'updated' ) ) ) {
			 $wp_query->query['browse'] = 'featured';
			 $wp_query->query_vars['browse'] = 'featured';
		}

		// Set up custom queries for the /browse/ URLs
		switch ( $wp_query->get( 'browse' ) ) {
			case 'beta':
				$wp_query->query_vars['meta_key'] = 'last_updated';
				$wp_query->query_vars['orderby']  = 'meta_value';
				$wp_query->query_vars['order']    = 'DESC';
				break;

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

					$wp_query->query_vars['orderby'] = 'post_title';
					$wp_query->query_vars['order']   = 'ASC';
				}

				if ( ! $favorites_user || ! $wp_query->query_vars['post_name__in'] ) {
					$wp_query->query_vars['p'] = -1;
				}
				break;

			case 'updated':
				$wp_query->query_vars['orderby'] = 'modified_date';
				break;

			case 'block':
			case 'new':
				$wp_query->query_vars['orderby'] = 'post_date';
				break;
		}

		// For /browse/ requests, we conditionally need to avoid querying the taxonomy for most views (as it's handled in code above)
		if ( isset( $wp_query->query['browse'] ) && ! in_array( $wp_query->query['browse'], array( 'beta', 'blocks', 'block', 'featured', 'adopt-me' ) ) ) {
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
		if ( $wp_query->is_main_query() && $wp_query->is_author() ) {
			$user = false;
			if ( isset( $wp_query->query['author_name'] ) ) {
				$user = $wp_query->query['author_name'];
			} elseif ( ! empty( $wp_query->query['author'] ) ) {
				$user = get_user_by( 'id', $wp_query->query['author'] );
				if ( $user ) {
					$user = $user->user_nicename;
				}
			}

			$viewing_own_author_archive = is_user_logged_in() && $user && ( current_user_can( 'plugin_review' ) || 0 === strcasecmp( $user, wp_get_current_user()->user_nicename ) );

			// Author archives by default list plugins you're a contributor on.
			$wp_query->query_vars['tax_query'] = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'plugin_contributors',
					'field'    => 'slug',
					'terms'    => $user,
				),
			);

			// Author archives for self include plugins you're a committer on, not just publically a contributor
			// Plugin Reviewers also see plugins you're a committer on here.
			if ( $viewing_own_author_archive ) {
				$wp_query->query_vars['tax_query'][] = array(
					'taxonomy' => 'plugin_committers',
					'field'    => 'slug',
					'terms'    => $user,
				);
			}

			$wp_query->query_vars['orderby'] = 'post_title';
			$wp_query->query_vars['order']   = 'ASC';

			// Treat it as a taxonomy query now, not the author archive.
			$wp_query->is_author = false;
			$wp_query->is_tax    = true;

			unset( $wp_query->query_vars['author_name'], $wp_query->query_vars['author'] );
		}

		// For singular requests, or self-author profile requests allow restricted post_status items to show on the front-end.
		if ( $wp_query->is_main_query() && ( $viewing_own_author_archive || is_user_logged_in() && ! empty( $wp_query->query_vars['name'] ) ) ) {

			$wp_query->query_vars['post_status'] = array( 'approved', 'publish', 'closed', 'disabled' );

			add_filter( 'posts_results', function( $posts, $this_wp_query ) use ( $wp_query ) {
				if ( $this_wp_query != $wp_query ) {
					return $posts;
				}

				// Published, closed, or disabled plugins shouldn't be affected by cap checks.
				$restricted_access_statii = array_diff( $wp_query->query_vars['post_status'], array( 'publish', 'closed', 'disabled' ) );

				foreach ( $posts as $i => $post ) {
					// If the plugin is not in the restricted statuses list, show it
					if ( 'plugin' != $post->post_type || ! in_array( $post->post_status, $restricted_access_statii, true ) ) {
						continue;
					}

					// If the current user can view the plugin admin, show it
					if ( current_user_can( 'plugin_admin_view', $post ) ) {
						continue;
					}

					// Else hide it.
					unset( $posts[ $i ] );
				}

				return $posts;
			}, 10, 2 );
		}

		// Allow anyone to view a closed plugin directly from its page. It won't show in search results or lists.
		if ( $wp_query->is_main_query() && ! empty( $wp_query->query_vars['name'] ) && ! empty( $wp_query->query_vars['post_status'] ) ) {
			$wp_query->query_vars['post_status']   = (array) $wp_query->query_vars['post_status'];
			$wp_query->query_vars['post_status'][] = 'closed';
			$wp_query->query_vars['post_status'][] = 'disabled';
			$wp_query->query_vars['post_status']   = array_unique( $wp_query->query_vars['post_status'] );
		}

		// Sanitize / cleanup the search query a little bit.
		if ( $wp_query->is_search() ) {
			$s = $wp_query->get( 's' );
			$s = urldecode( $s );

			// If a URL-like request comes in, reduce to a slug
			if ( preg_match( '!^http.+/plugins/([^/]+)(/|$)!i', $s, $m ) ) {
				$s = $m[1];
			}

			// Jetpack Search has a limit, limit to 200char. This is intentionally using ASCII length + Multibyte substr.
			if ( strlen( $s ) > 200 ) {
				$s = mb_substr( $s, 0, 200 );
			}

			// Trim off special characters, only allowing wordy characters at the end of searches.
			$s = preg_replace( '!(\W+)$!iu', '', $s );
			// ..and whitespace
			$s = trim( $s );

			$wp_query->set( 's', $s );
		}

		// By default, all archives are sorted by active installs
		if ( $wp_query->is_archive() && empty( $wp_query->query_vars['orderby'] ) ) {
			$wp_query->query_vars['orderby']  = 'meta_value_num';
			$wp_query->query_vars['meta_key'] = '_active_installs';
		}
	}

	/**
	 * Filter to limit the total number of found posts in browse queries.
	 * Stops search crawlers from paginating through the entire DB.
	 */
	public function filter_found_posts( $found_posts, $wp_query ) {
		if ( isset( $wp_query->query['browse'] ) && is_array( $wp_query->query_vars['post_type'] ) && in_array( 'plugin', $wp_query->query_vars['post_type'] ) ) {
			return min( $found_posts, 99 * $wp_query->query_vars['posts_per_page'] ); // 99 pages
		}

		return $found_posts;
	}

	/**
	 * Filter to bypass caching for options critical to Jetpack sync to work around race conditions and other unidentified bugs.
	 * If this works and becomes a permanent solution, it probably belongs elsewhere.
	 */
	public function bypass_options_cache( $value, $option ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
			$option
		) );
		$value = maybe_unserialize( $value );

		return $value;
	}

	/**
	 * Adjust the login URL to point back to whatever part of the plugin directory we're
	 * currently looking at. This allows the redirect to come back to the same place
	 * instead of the main /support URL by default.
	 */
	public function fix_login_url( $login_url, $redirect, $force_reauth ) {
		// modify the redirect_to for the plugin directory to point to the current page
		if ( 0 === strpos( $_SERVER['REQUEST_URI'], '/plugins' ) ) {
			// Note that this is not normal because of the code in /mu-plugins/wporg-sso/class-wporg-sso.php.
			// The login_url function there expects the redirect_to as the first parameter passed into it instead of the second
			// Since we're changing this with a filter on login_url, then we have to change the login_url to the
			// place we want to redirect instead, and then let the SSO plugin do the rest.
			//
			// If the SSO code gets fixed, this will need to be modified.
			//
			// parse_url is used here to remove any additional query args from the REQUEST_URI before redirection
			// The SSO code handles the urlencoding of the redirect_to parameter
			$url_parts       = parse_url( set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );
			$constructed_url = $url_parts['scheme'] . '://' . $url_parts['host'] . ( isset( $url_parts['path'] ) ? $url_parts['path'] : '' );

			if ( class_exists( 'WPOrg_SSO' ) ) {
				$login_url = $constructed_url;
			} else {
				$login_url = add_query_arg( 'redirect_to', urlencode( $constructed_url ), $login_url );
			}
		}
		return $login_url;
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

		$post = get_post( $post_id );

		// Only translate Plugin post objects.
		if ( $post && 'plugin' === $post->post_type ) {
			return Plugin_I18n::instance()->translate( $section, $content, [ 'post_id' => $post_id ] );
		}

		return $content;
	}

	/**
	 * Returns the requested page's title, translated.
	 *
	 * @param string $title
	 * @param int    $post_id
	 * @return string
	 */
	public function translate_post_title( $title, $post_id = null ) {
		$post = get_post( $post_id );

		// Only translate Plugin post objects.
		if ( $post && $post->post_type === 'plugin' ) {
			return Plugin_I18n::instance()->translate( 'title', $title, [ 'post_id' => $post ] );
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
		$post = get_post( $post );

		// Only translate Plugin post objects.
		if ( $post && $post->post_type === 'plugin' ) {
			return Plugin_I18n::instance()->translate( 'excerpt', $excerpt, [ 'post_id' => $post ] );
		}

		return $excerpt;
	}

	/**
	 * Fetch all translated content for a given post, and push it into postmeta.
	 *
	 * @param int|string|WP_Post $plugin Plugin to update.
	 * @param int $min_translated Translations below this % threshold will not be synced to meta, to save space.
	 * @return array
	 */
	public function sync_all_translations_to_meta( $plugin, $min_translated = 40, $skip_pfx = array( 'en_' ) ) {

		$locales_to_sync = array();
		$post            = self::get_plugin_post( $plugin );
		if ( $post ) {
			$project = 'stable-readme';
			if ( ! $post->stable_tag || 'trunk' === $post->stable_tag ) {
				$project = 'dev-readme';
			}

			$translations = Plugin_I18n::instance()->find_all_translations_for_plugin( $post->post_name, $project, $min_translated ); // at least $min_translated % translated
			if ( $translations ) {
				// Eliminate translations that start with unwanted prefixes, so we don't waste space on near-duplicates like en_AU, en_CA etc.
				foreach ( $translations as $i => $_locale ) {
					foreach ( $skip_pfx as $pfx ) {
						if ( substr( $_locale, 0, strlen( $pfx ) ) === $pfx ) {
							unset( $translations[ $i ] );
						}
					}
				}
				$locales_to_sync = array_unique( $translations );
			}
		}

		if ( count( $locales_to_sync ) > 0 ) {
			foreach ( $locales_to_sync as $locale ) {
				$this->sync_translation_to_meta( $post->ID, $locale );
			}
		}

		return $locales_to_sync;
	}

	/**
	 * Fetch translated content for a given post and locale, and push it into postmeta.
	 *
	 * @param int    $post_id    Post ID to update.
	 * @param string $locale  Locale to translate.
	 */
	public function sync_translation_to_meta( $post_id, $locale ) {
		// Keep track of the original untranslated strings
		$orig_title   = get_the_title( $post_id );
		$orig_excerpt = get_the_excerpt( $post_id );
		$orig_content = get_post_field( 'post_content', $post_id );

		// Update postmeta values for the translated title, excerpt, and content, if they are available and different from the originals.
		// There is a bug here, in that no attempt is made to remove old meta values for translations that do not have new translations.
		$the_title = Plugin_I18n::instance()->translate( 'title', $orig_title, [ 'post_id' => $post_id, 'locale' => $locale ] );
		if ( $the_title && $the_title != $orig_title ) {
			update_post_meta( $post_id, 'title_' . $locale, $the_title );
		}

		$the_excerpt =  Plugin_I18n::instance()->translate( 'excerpt', $orig_excerpt, [ 'post_id' => $post_id, 'locale' => $locale ] );
		if ( $the_excerpt && $the_excerpt != $orig_excerpt ) {
			update_post_meta( $post_id, 'excerpt_' . $locale, $the_excerpt );
		}

		// Split up the content to translate it in sections.
		$the_content = array();
		$sections    = $this->split_post_content_into_pages( $orig_content );
		foreach ( $sections as $section => $section_content ) {
			$translated_section = Plugin_I18n::instance()->translate( $section, $section_content, [ 'post_id' => $post_id, 'locale' => $locale ] );
			if ( $translated_section && $translated_section != $section_content ) {
				// ES expects the section delimiters to still be present
				$the_content[ $section ] = "<!--section={$section}-->\n" . $translated_section;
			}
		}

		if ( ! empty( $the_content ) ) {
			update_post_meta( $post_id, 'content_' . $locale, implode( $the_content ) );
		}

		// Translate Block Titles. A bit more complicated as there's multiple postmeta values.
		$existing_translated_titles = array_unique( get_post_meta( $post_id, 'block_title_' . $locale ) );
		foreach ( array_unique( get_post_meta( $post_id, 'block_title' ) ) as $block_title ) {
			$translated_title = Plugin_I18n::instance()->translate( 'block_title:' . md5( $block_title ), $block_title, [ 'post_id' => $post_id, 'locale' => $locale ] );

			if ( $translated_title == $block_title ) {
				continue;
			}

			if ( false !== ( $pos = array_search( $translated_title, $existing_translated_titles, true ) ) ) {
				// If translation is meta'd, skip
				unset( $existing_translated_titles[ $pos ] );
			} else {
				// If tranlation is unknown, add it.
				add_post_meta( $post_id, 'block_title_' . $locale, $translated_title );
			}
		}
		// Delete any unknown translations.
		foreach ( $existing_translated_titles as $block_title ) {
			delete_post_meta( $post_id, 'block_title_' . $locale, $block_title );
		}
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
		$vars[] = 'redirect_plugin';
		$vars[] = 'redirect_plugin_tab';
		$vars[] = 'plugin_advanced';
		$vars[] = 'geopattern_icon';
		$vars[] = 'block_search';

		// Remove support for any query vars the Plugin Directory doesn't support/need.
		$not_needed = [
			'm', 'w', 'year', 'monthnum', 'day', 'hour', 'minute', 'second',
			'posts', 'withcomments', 'withoutcomments', 'favicon', 'cpage',
			'search', 'exact', 'sentence', 'calendar', 'more', 'tb', 'pb',
			'attachment_id', 'subpost', 'subpost_id', 'preview',
			'post_format', 'cat', 'category_name', 'tag', // We use custom cats/tags.
		];

		return array_diff( $vars, $not_needed );
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
			case 'plugin_section':
				if ( 'favorites' == $term->slug ) {
					$user = get_query_var( 'favorites_user' ) ?? $_GET['favorites_user'];
					$user = get_user_by( 'slug', $user );
					if ( $user && $user != wp_get_current_user() ) {
						$name = sprintf(
							__( 'Favorites: %s', 'wporg-plugins' ),
							esc_html( $user->display_name )
						);
					}
				}
				break;
			case 'plugin_contributors':
			case 'plugin_committers':
				$user = get_user_by( 'slug', $term->name );
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
		global $wp_query;

		// Handle a redirect for /$plugin/$tab_name/ to /$plugin/#$tab_name.
		if ( get_query_var( 'redirect_plugin' ) && get_query_var( 'redirect_plugin_tab' ) ) {
			wp_safe_redirect( site_url( get_query_var( 'redirect_plugin' ) . '/#' . get_query_var( 'redirect_plugin_tab' ) ), 301 );
			die();
		}

		// We've disabled WordPress's default 404 redirects, so we'll handle them ourselves.
		if ( is_404() ) {

			// [1] => plugins [2] => example-plugin-name [3..] => random().
			$path = explode( '/', $_SERVER['REQUEST_URI'] );

			if ( 'tags' === $path[2] ) {
				if ( isset( $path[3] ) && ! empty( $path[3] ) ) {
					wp_safe_redirect( home_url( '/search/' . urlencode( $path[3] ) . '/' ), 301 );
					die();
				} else {
					wp_safe_redirect( home_url( '/' ), 301 );
					die();
				}
			}

			// The about page is now over at /developers/.
			if ( 'about' === $path[2] ) {
				if ( isset( $path[3] ) && 'add' == $path[3] ) {
					wp_safe_redirect( home_url( '/developers/add/' ), 301 );
				} elseif ( isset( $path[3] ) && 'validator' == $path[3] ) {
					wp_safe_redirect( home_url( '/developers/readme-validator/' ), 301 );
				} else {
					wp_safe_redirect( home_url( '/developers/' ), 301 );
				}
				die();
			}

			// Browse 404s.
			if ( 'browse' === $path[2] ) {
				wp_safe_redirect( home_url( '/' ), 301 );
				die();
			}

			// The readme.txt page.
			if ( 'readme.txt' === $path[2] ) {
				status_header( 200 );
				header( 'Content-type: text/plain' );
				echo file_get_contents( __DIR__ . '/readme/readme.txt' );
				die();
			}

			// Handle any plugin redirects.
			if ( $path[2] && ( $plugin = self::get_plugin_post( $path[2] ) ) ) {
				$permalink = get_permalink( $plugin->ID );
				if ( parse_url( $permalink, PHP_URL_PATH ) != $_SERVER['REQUEST_URI'] ) {
					wp_safe_redirect( $permalink, 301 );
					die();
				}
			}

			// Otherwise, let's redirect to the search page.
			if ( isset( $path[2] ) && ! empty( $path[2] ) ) {
				wp_safe_redirect( home_url( '/search/' . urlencode( $path[2] ) . '/' ), 301 );
				die();
			}
		}

		// Redirect mixed-case plugin names to the canonical location.
		if (
			get_query_var( 'name' ) && // A sanitized lowercase value is here
			is_singular() &&
			! empty( $wp_query->query['name'] ) && // The raw value is available here.
			get_query_var( 'name' ) != $wp_query->query['name']
		) {
			$url = get_permalink();
			if ( get_query_var( 'plugin_advanced' ) ) {
				$url .= 'advanced/';
			}

			wp_safe_redirect( $url, 301 );
			die();
		}

		// If it's an old search query, handle that too.
		if ( 'search.php' == get_query_var( 'name' ) && isset( $_GET['q'] ) ) {
			wp_safe_redirect( site_url( '/search/' . urlencode( wp_unslash( $_GET['q'] ) ) . '/' ), 301 );
			die();
		}

		// New-style search links.
		if ( get_query_var( 's' ) && isset( $_GET['s'] ) ) {
			$url = site_url( '/search/' . urlencode( get_query_var( 's' ) ) . '/' );
			if ( get_query_var( 'block_search' ) ) {
				$url = add_query_arg( 'block_search', get_query_var( 'block_search' ), $url );
			}

			wp_safe_redirect( $url, 301 );
			die();
		}

		// Existing tag with no plugins.
		if (
			( is_tax() || is_category() || is_tag() ) &&
			! have_posts() &&
			! is_tax( 'plugin_section' ) // All sections have something, or intentionally don't (favorites)
		) {
			// [1] => plugins [2] => tags [3] => example-plugin-name [4..] => random().
			$path = explode( '/', $_SERVER['REQUEST_URI'] );

			wp_safe_redirect( home_url( '/search/' . urlencode( $path[3] ) . '/' ), 301 );
			die();
		}

		// Empty search query.
		// This may occur due to WordPress's 1600 character search limit.
		if (
				'search' === get_query_var( 'name' ) ||
				( isset( $_GET['s'] ) && ! get_query_var( 's' ) ) ||
				( is_search() && 0 === strlen( get_query_var( 's' ) ) )
		) {
			wp_safe_redirect( site_url( '/' ), 301 );
			die();
		}

		// Paginated front page.
		if ( is_front_page() && is_paged() ) {
			$GLOBALS['wp_query']->set_404();
			status_header( 404 );
			return;
		}

		// Disable feeds
		if ( is_feed() ) {
			if ( isset( $_GET['feed'] ) ) {
				wp_safe_redirect( esc_url_raw( remove_query_arg( 'feed' ) ), 301 );
				die();
			}

			set_query_var( 'feed', '' );

			if ( ! redirect_canonical() ) {
				// There exists no canonical location for this request according to `redirect_canonical()`.
				if ( get_query_var( 's' ) ) {
					wp_safe_redirect( home_url( '/search/' . get_query_var('s')  . '/' ), 301 );
				} else {
					// If all else fails, homepage.
					wp_safe_redirect( home_url( '/' ) );
				}
			}

			die();
		}

		if ( is_comment_feed() ) {
			wp_redirect( 'https://wordpress.org/plugins/', 301 );
			die();
		}

	}

	/**
	 * Output a SVG Geopattern for a given plugin.
	 */
	function geopattern_icon_route() {
		global $wp;

		if ( ! isset( $wp->query_vars['name'], $wp->query_vars['geopattern_icon'] ) ) {
			return;
		}

		$slug  = get_query_var( 'name' );
		$color = get_query_var( 'geopattern_icon' );

		$icon = new Plugin_Geopattern();
		$icon->setString( $slug );
		if ( strlen( $color ) === 6 && strspn( $color, 'abcdef0123456789' ) === 6 ) {
			$icon->setColor( '#' . $color );
		}

		status_header( 200 );
		header( 'Content-Type: image/svg+xml' );
		header( 'Cache-Control: public, max-age=' . YEAR_IN_SECONDS );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + YEAR_IN_SECONDS ) );

		echo $icon->toSVG();
		die();
	}

	/**
	 * The array of post types to be included in the sitemap.
	 *
	 * @param array $post_types List of included post types.
	 * @return array
	 */
	public function jetpack_sitemap_post_types( $post_types ) {
		$post_types[] = 'plugin';

		return $post_types;
	}

	/**
	 * Skip outdated plugins in Jetpack Sitemaps.
	 *
	 * @param bool $skip If this post should be excluded from Sitemaps.
	 * @param object $plugin_db_row A row from the wp_posts table.
	 * @return bool
	 */
	public function jetpack_sitemap_skip_post( $skip, $plugin_db_row ) {
		static $calls = 0;
		if ( $calls++ >= 50 ) {
			// Clear some memory caches.
			$calls = 0;
			Tools::clear_memory_heavy_variables();
		}

		if ( Template::is_plugin_outdated( $plugin_db_row->ID ) ) {
			$skip = true;
		}

		return $skip;
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
	 * Returns an array of pages based on section comments in the content.
	 *
	 * @param string $content
	 * @return array
	 */
	public function split_post_content_into_pages( $content ) {
		$_pages        = preg_split( '#<!--section=(.+?)-->#', ltrim( $content ), - 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$content_pages = array(
			'screenshots' => '[wporg-plugins-screenshots]',
			'developers'  => '[wporg-plugins-developers]',
			'reviews'     => '[wporg-plugins-reviews]',
		);

		for ( $i = 0; $i < count( $_pages ); $i += 2 ) {

			// Don't overwrite existing tabs.
			if ( ! isset( $content_pages[ $_pages[ $i ] ] ) ) {
				$content_pages[ $_pages[ $i ] ] = $_pages[ $i + 1 ] ?? '';
			}
		}

		return $content_pages;
	}

	/**
	 * Get a list of all Plugin Releases.
	 */
	public static function get_releases( $plugin ) {
		$plugin   = self::get_plugin_post( $plugin );
		$releases = get_post_meta( $plugin->ID, 'releases', true );

		// Meta doesn't exist yet? Lets fill it out.
		if ( false === $releases || ! is_array( $releases ) ) {
			update_post_meta( $plugin->ID, 'releases', [] );

			$tags = get_post_meta( $plugin->ID, 'tags', true );
			if ( $tags ) {
				foreach ( $tags as $tag_version => $tag ) {
					self::add_release( $plugin, [
						'date' => strtotime( $tag['date'] ),
						'tag'  => $tag['tag'],
						'version' => $tag_version,
						'committer' => [ $tag['author'] ],
						'confirmations_required' => 0, // Old release, assume it's released.
					] );
				}
			} else {
				// Pull from SVN directly.
				$svn_tags = Tools\SVN::ls( "https://plugins.svn.wordpress.org/{$plugin->post_name}/tags/", true ) ?: [];
				foreach ( $svn_tags as $entry ) {
					// Discard files
					if ( 'dir' !== $entry['kind'] ) {
						continue;
					}

					$tag = $entry['filename'];

					// Prefix the 0 for plugin versions like 0.1
					if ( '.' == substr( $tag, 0, 1 ) ) {
						$tag = "0{$tag}";
					}

					self::add_release( $plugin, [
						'date' => strtotime( $entry['date'] ),
						'tag'  => $entry['filename'],
						'version' => $tag,
						'committer' => [ $entry['author'] ],
						'confirmations_required' => 0, // Old release, assume it's released.
					] );
				}
			}

			$releases = get_post_meta( $plugin->ID, 'releases', true ) ?: [];
		}

		return $releases;
	}

	/**
	 * Fetch a specific release of the plugin, by tag.
	 */
	public static function get_release( $plugin, $tag ) {
		$releases = self::get_releases( $plugin );

		$filtered = wp_list_filter( $releases, compact( 'tag' ) );

		if ( $filtered ) {
			return array_shift( $filtered );
		}

		return false;
	}

	/**
	 * Add a Plugin Release to the internal storage.
	 */
	public static function add_release( $plugin, $data ) {
		if ( ! isset( $data['tag'] ) ) {
			return false;
		}
		$plugin = self::get_plugin_post( $plugin );

		$release = self::get_release( $plugin, $data['tag'] ) ?: [
			'date'                   => time(),
			'tag'                    => '',
			'version'                => '',
			'zips_built'             => false,
			'confirmations'          => [],
			// Confirmed by default if no release confiration.
			'confirmed'              => ! $plugin->release_confirmation,
			'confirmations_required' => (int) $plugin->release_confirmation,
			'committer'              => [],
			'revision'               => [],
		];

		// Fill the $release with the newish data. This could/should use wp_parse_args()?
		foreach ( $data as $k => $v ) {
			$release[ $k ] = $v;
		}

		$releases = self::get_releases( $plugin );

		// Find any other releases using this slug (as in the case of updates) and remove it.
		// Only one release can exist in any given tag.
		foreach ( $releases as $i => $r ) {
			if ( $r['tag'] === $release['tag'] ) {
				unset( $releases[ $i ] );
			}
		}

		// Add this release in
		$releases[] = $release;

		// Sort releases most recent first.
		uasort( $releases, function( $a, $b ) {
			return $b['date'] <=> $a['date'];
		} );

		return update_post_meta( $plugin->ID, 'releases', $releases );
	}

	/**
	 * Retrieve the WP_Post object representing a given plugin.
	 *
	 * @static
	 * @global \WP_Post $post WordPress post object.
	 *
	 * @param int|string|\WP_Post $plugin_slug The slug of the plugin to retrieve.
	 * @return \WP_Post|bool
	 */
	public static function get_plugin_post( $plugin_slug = null ) {
		if ( $plugin_slug instanceof \WP_Post ) {
			return $plugin_slug;
		}

		// Handle int $plugin_slug being passed. NOT numeric slugs
		if (
			is_int( $plugin_slug ) &&
			( $post = get_post( $plugin_slug ) ) &&
			( $post->ID === $plugin_slug )
		) {
			return $post;
		}

		// Use the global $post object when appropriate
		if (
			! empty( $GLOBALS['post']->post_type ) &&
			'plugin' === $GLOBALS['post']->post_type
		) {
			// Default to the global object.
			if ( is_null( $plugin_slug ) || 0 === $plugin_slug ) {
				return get_post( $GLOBALS['post']->ID );
			}

			// Avoid hitting the database if it matches.
			if ( $plugin_slug == $GLOBALS['post']->post_name ) {
				return get_post( $GLOBALS['post']->ID );
			}
		}

		$plugin_slug = sanitize_title_for_query( $plugin_slug );
		if ( ! $plugin_slug ) {
			return false;
		}

		$post    = false;
		$post_id = wp_cache_get( $plugin_slug, 'plugin-slugs' );
		if ( 0 === $post_id ) {
			// Unknown plugin slug.
			return false;
		} else if ( $post_id ) {
			$post = get_post( $post_id );
		}

		if ( ! $post ) {
			// get_post_by_slug();
			$posts = get_posts( array(
				'post_type'   => 'plugin',
				'name'        => $plugin_slug,
				'post_status' => array( 'publish', 'pending', 'disabled', 'closed', 'new', 'draft', 'approved', 'rejected' ),
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
	 *     @type string $post_status           The post status. Default 'new'.
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
		$slug  = $args['post_name'] ?: sanitize_title( $title );

		$post_date     = current_time( 'mysql' );
		$post_date_gmt = current_time( 'mysql', 1 );

		$args = wp_parse_args( $args, array(
			'post_title'        => $title,
			'post_name'         => $slug,
			'post_type'         => 'plugin',
			'post_date'         => $post_date,
			'post_date_gmt'     => $post_date_gmt,
			'post_modified'     => $post_date,
			'post_modified_gmt' => $post_date_gmt,
		) );

		$result = wp_insert_post( $args, true );

		if ( ! is_wp_error( $result ) ) {
			wp_cache_set( $result, $slug, 'plugin-slugs' );
			$result = get_post( $result );

			$owner = get_userdata( $result->post_author );

			Tools::audit_log( sprintf(
				'Submitted by <a href="%s">%s</a>.',
				esc_url( 'https://profiles.wordpress.org/' . $owner->user_nicename . '/' ),
				$owner->user_login
			), $result->ID );
		}

		return $result;
	}

}
