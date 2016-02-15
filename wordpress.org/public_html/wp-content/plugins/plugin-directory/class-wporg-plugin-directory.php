<?php
/**
 * @package WPorg_Plugin_Directory
 */

/**
 * Class WPorg_Plugin_Directory
 */
class WPorg_Plugin_Directory {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'post_type_link', array( $this, 'package_link' ), 10, 2 );
		add_filter( 'pre_insert_term', array( $this, 'pre_insert_term_prevent' ) );
		add_action( 'pre_get_posts', array( $this, 'use_plugins_in_query' ) );
		add_filter( 'the_content', array( $this, 'filter_post_content_to_correct_page' ), 1 );
	}

	/**
	 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
	 */
	public function activate() {
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

		// Enable the WordPress.org Plugin Repo Theme.
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
	 *
	 */
	public function deactivate() {
		flush_rewrite_rules();

		do_action( 'wporg_plugins_deactivation' );
	}

	/**
	 * Set up the Plugin Directory.
	 */
	public function init() {
		load_plugin_textdomain( 'wporg-plugins' );

		register_post_type( 'plugin', array(
			'labels'      => array(
				'name'               => __( 'Plugins',          'wporg-plugins' ),
				'singular_name'      => __( 'Plugin',           'wporg-plugins' ),
				'menu_name'          => __( 'My Plugins',       'wporg-plugins' ),
				'add_new'            => __( 'Add New',          'wporg-plugins' ),
				'add_new_item'       => __( 'Add New Plugin',   'wporg-plugins' ),
				'edit_item'          => __( 'Edit Plugin',      'wporg-plugins' ),
				'new_item'           => __( 'New Plugin',       'wporg-plugins' ),
				'view_item'          => __( 'View Plugin',      'wporg-plugins' ),
				'search_items'       => __( 'Search Plugins',   'wporg-plugins' ),
				'not_found'          => __( 'No plugins found', 'wporg-plugins' ),
				'not_found_in_trash' => __( 'No plugins found in Trash', 'wporg-plugins' ),
			),
			'description' => __( 'A Repo Plugin', 'wporg-plugins' ),
			'supports'    => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
			'taxonomies'  => array( 'post_tag', 'category' ),
			'public'      => true,
			'show_ui'     => true,
			'has_archive' => true,
			'rewrite'     => false,
			'menu_icon'   => 'dashicons-admin-plugins',
		) );

		register_post_status( 'pending', array(
			'label'                     => _x( 'Pending', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'disabled', array(
			'label'                     => _x( 'Disabled', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'closed', array(
			'label'                     => _x( 'Closed', 'plugin status', 'wporg-plugins' ),
			'public'                    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'wporg-plugins' ),
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
	}

	/**
	 * Filter the permalink for the Packages to be /post_name/.
	 *
	 * @param string  $link The generated permalink.
	 * @param WP_Post $post The Plugin post object.
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
	 * @return string|WP_Error The term to add or update or WP_Error on failure.
	 */
	public function pre_insert_term_prevent( $term ) {
		if ( ! is_super_admin() ) {
			$term = new WP_Error( 'not-allowed', __( 'You are not allowed to add terms.', 'wporg-plugins' ) );
		}

		return $term;
	}

	/**
	 * @param WP_Query $wp_query The WordPress Query object.
	 */
	public function use_plugins_in_query( $wp_query ) {
		if ( ! $wp_query->is_main_query() ) {
			return;
		}

		if ( empty( $wp_query->query_vars['pagename'] ) && ( empty( $wp_query->query_vars['post_type'] ) || 'posts' == $wp_query->query_vars['post_type'] ) ) {
			$wp_query->query_vars['post_type'] = array( 'plugin' );
		}

		if ( empty( $wp_query->query ) ) {
			$wp_query->query_vars['browse'] = 'featured';
		}

		switch ( get_query_var( 'browse' ) ) {
			case 'beta':
				$wp_query->query_vars['category_name'] = 'beta';
				break;

			case 'featured':
				$wp_query->query_vars['category_name'] = 'featured';
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
	 * Returns an array of pages based on section comments in the content.
	 *
	 * @param string $content
	 * @return array
	 */
	public function split_post_content_into_pages( $content ) {
		$_pages        = preg_split( "#<!--section=(.+?)-->#", $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$content_pages = array(
			'stats'      => '[wporg-plugins-stats]',
			'developers' => '[wporg-plugins-developer]',
		);

		for ( $i = 0; $i < count( $_pages ); $i += 2 ) {

			// Don't overwrite existing tabs.
			if ( ! isset( $content_pages[ $_pages[ $i ] ] ) ) {
				$content_pages[ $_pages[ $i ] ] = $_pages[ $i + 1 ];
			}
		}

		return $content_pages;
	}
}
