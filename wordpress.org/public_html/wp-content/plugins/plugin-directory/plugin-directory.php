<?php
/*
 * Plugin Name: Plugin Repository
 * Plugin URI: http://wordpress.org/plugins/
 * Description: Transforms a WordPress site in The Official Plugin Directory.
 * Version: 0.1
 * Author: wordpressdotorg
 * Author URI: http://wordpress.org/
 * Text Domain: wporg-plugins
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 */

class Plugin_Directory {

	function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'post_type_link', array( $this, 'package_link' ), 10, 2 );
		add_filter( 'pre_insert_term', array( $this, 'pre_insert_term_prevent' ) );
		add_action( 'pre_get_posts', array( $this, 'use_plugins_in_query' ) );
		add_filter( 'the_content', array( $this, 'filter_post_content_to_correct_page' ), 1 );
	}

	function activate() {
		global $wp_rewrite;

		// Setup the environment
		$this->init();

		// %postname% is required
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// /tags/%slug% is required for tags
		$wp_rewrite->set_tag_base( '/tags' );

		// We require the WordPress.org Ratings plugin also be active
		if ( ! is_plugin_active( 'wporg-ratings/wporg-ratings.php' ) ) {
			activate_plugin( 'wporg-ratings/wporg-ratings.php' );
		}
	
		// Enable the WordPress.org Theme Repo Theme
		foreach ( wp_get_themes() as $theme ) {
			if ( $theme->get( 'Name' ) === 'WordPress.org Plugins' ) {
				switch_theme( $theme->get_stylesheet() );
				break;
			}
		}
	
		flush_rewrite_rules();
	
		do_action( 'wporg_plugins_activation' );
	}

	function deactivate() {
		flush_rewrite_rules();
	
		do_action( 'wporg_plugins_deactivation' );
	}


	function init() {
		load_plugin_textdomain( 'wporg-plugins' );

		register_post_type( 'plugin', array(
			'labels'      => array(
				'name'               => __( 'Plugins', 'wporg-plugins' ),
				'singular_name'      => __( 'Plugin', 'wporg-plugins' ),
				'add_new'            => __( 'Add New', 'wporg-plugins' ),
				'add_new_item'       => __( 'Add New Plugin', 'wporg-plugins' ),
				'edit_item'          => __( 'Edit Plugin', 'wporg-plugins' ),
				'new_item'           => __( 'New Plugin', 'wporg-plugins' ),
				'view_item'          => __( 'View Plugin', 'wporg-plugins' ),
				'search_items'       => __( 'Search Plugins', 'wporg-plugins' ),
				'not_found'          => __( 'No plugins found', 'wporg-plugins' ),
				'not_found_in_trash' => __( 'No plugins found in Trash', 'wporg-plugins' ),
				'menu_name'          => __( 'My Plugins', 'wporg-plugins' ),
			),
			'description' => __( 'A package', 'wporg-plugins' ),
			'supports'    => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
			'taxonomies'  => array( 'post_tag', 'category' ),
			'public'      => true,
			'show_ui'     => true,
			'has_archive' => true,
			'rewrite'     => false,
			'menu_icon'   => 'dashicons-admin-plugins',
		) );

		register_post_status( 'pending', array(
			'label' => _x( 'Pending', 'plugin status', 'wporg-plugins' ),
			'public' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'disabled', array(
			'label' => _x( 'Disabled', 'plugin status', 'wporg-plugins' ),
			'public' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
		register_post_status( 'closed', array(
			'label' => _x( 'Closed', 'plugin status', 'wporg-plugins' ),
			'public' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'wporg-plugins' ),
		) );
	
		// Add the browse/* views
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
 	 * Filter the permalink for the Packages to be /post_name/
 	 *
 	 * @param string $link The generated permalink
 	 * @param string $post The package object
 	 * @return string
 	 */
	function package_link( $link, $post ) {
		if ( 'plugin' != $post->post_type ) {
			return $link;
		}
	
		return trailingslashit( home_url( $post->post_name ) );
	}

	/**
 	 * Checks if ther current users is a super admin before allowing terms to be added.
 	 *
 	 * @param string           $term The term to add or update.
 	 * @return string|WP_Error The term to add or update or WP_Error on failure.
 	 */
	function pre_insert_term_prevent( $term ) {
		if ( ! is_super_admin() ) {
			$term = new WP_Error( 'not-allowed', __( 'You are not allowed to add terms.', 'wporg-plugins' ) );
		}
	
		return $term;
	}

	function use_plugins_in_query( $wp_query ) {
		if ( ! $wp_query->is_main_query() ) {
			return;
		}

		if ( empty( $wp_query->query_vars['pagename'] ) &&
			( empty( $wp_query->query_vars['post_type'] ) || 'posts' == $wp_query->query_vars['post_type'] ) ) {
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
		if ( !empty( $wp_query->query['name'] ) ) {
			foreach ( array( 'installation', 'faq', 'screenshots', 'changelog', 'stats', 'developers', 'other_notes' ) as $plugin_field ) {
				if ( isset( $wp_query->query[ $plugin_field ] ) ) {
					$wp_query->query['content_page'] = $wp_query->query_vars['content_page'] = $plugin_field;
					unset( $wp_query->query[ $plugin_field ], $wp_query->query_vars[ $plugin_field ] );
				}
			}
		}
	}

	function filter_post_content_to_correct_page( $content ) {
		global $content_pages;

		$post = get_post();
		if ( 'plugin' != $post->post_type ) {
			return $content;
		}

		$page = get_query_var( 'content_page' );
		$content_pages = $this->split_post_content_into_pages( $content );

		if ( ! isset( $content_pages[ $page ] ) ) {
			$page = 'description';
		}

		return $content_pages[ $page ];
	}

	function split_post_content_into_pages( $content ) {
		$content_pages = array(
			'stats' => '[wporg-plugins-stats]',
			'developers' => '[wporg-plugins-developer]',
		);
		$_pages = preg_split( "#<!--section=(.+?)-->#", $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		for ( $i = 0; $i < count( $_pages ); $i += 2 ) {
			// Don't overwrite existing tabs.
			if ( ! isset( $content_pages[ $_pages[ $i ] ] ) ) {
				$content_pages[ $_pages[ $i ] ] = $_pages[ $i + 1 ];
			}
		}

		return $content_pages;
	}

}
new Plugin_Directory();

// Various functions used by other processes, will make sense to move to specific classses.
class Plugin_Directory_Tools {
	static function get_readme_data( $readme ) {
		// Uses https://github.com/rmccue/WordPress-Readme-Parser (with modifications)
		include_once __DIR__ . '/readme-parser/markdown.php';
		include_once __DIR__ . '/readme-parser/compat.php';

		$data = (object) _WordPress_org_Readme::parse_readme( $readme );

		unset( $data->sections['screenshots'] ); // Useless

		// sanitize contributors.
		foreach ( $data->contributors as $i => $name ) {
			if ( get_user_by( 'login', $name ) ) {
				continue;
			} elseif ( false !== ( $user = get_user_by( 'slug', $name ) ) ) {
				$data->contributors[] = $user->user_login;
				unset( $data->contributors[ $i ] );
			} else {
				unset( $data->contributors[ $i ] );
			}
		}

		return $data;
	}
}

// Various helpers to retrieve data not stored within WordPress
class Plugin_Directory_Template_Helpers {
	static function get_active_installs_count( $plugin_slug ) {
		global $wpdb;
	
		$count = wp_cache_get( $plugin_slug, 'plugin_active_installs' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT count FROM rev2_daily_stat_summary WHERE type = 'plugin' AND type_name = %s AND stat = 'active_installs' LIMIT 1",
				$plugin_slug
			) );
			wp_cache_add( $plugin_slug, $count, 'plugin_active_installs', 1200 );
		}
	
		if ( $count < 10 ) {
			return 0;
		}
	
		if ( $count >= 1000000 ) {
			return 1000000;
		}
	
		return strval( $count )[0] * pow( 10, floor( log10( $count ) ) );
	}

	static function get_total_downloads() {
		global $wpdb;

		$count = wp_cache_get( 'plugin_download_count', 'plugin_download_count' );
		if ( false === $count ) {
			$count = (int) $wpdb->get_var( "SELECT SUM(downloads) FROM `plugin_2_stats`" );
			wp_cache_add( 'plugin_download_count', $count, 'plugin_download_count', DAY_IN_SECONDS );
		}

		return $count;
	}

	static function get_plugin_sections() {
		$plugin_slug  = get_post()->post_name;
		$raw_sections = get_post_meta( get_the_ID(), 'sections', true );
		$raw_sections = array_unique( array_merge( $raw_sections, array( 'description', 'stats', 'support', 'reviews', 'developers' ) ) );
	
		$sections = array();
		foreach ( $raw_sections as $section_slug ) {
			$url = get_permalink();
			switch ( $section_slug ) {
				case 'description':
					$title = _x( 'Description', 'plugin tab title', 'wporg-plugins' );
					break;
				case 'installation':
					$title = _x( 'Installation', 'plugin tab title', 'wporg-plugins' );
					$url = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
				case 'faq':
					$title = _x( 'FAQ', 'plugin tab title', 'wporg-plugins' );
					$url = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
				case 'screenshots':
					$title = _x( 'Screenshots', 'plugin tab title', 'wporg-plugins' );
					$url = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
				case 'changelog':
					$title = _x( 'Changelog', 'plugin tab title', 'wporg-plugins' );
					$url = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
				case 'stats':
					$title = _x( 'Stats', 'plugin tab title', 'wporg-plugins' );
					$url = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
				case 'support':
					$title = _x( 'Support', 'plugin tab title', 'wporg-plugins' );
					$url = 'https://wordpress.org/support/plugin/' . $plugin_slug;
					break;
				case 'reviews':
					$title = _x( 'Reviews', 'plugin tab title', 'wporg-plugins' );
					$url = 'https://wordpress.org/support/view/plugin-reviews/' . $plugin_slug;
					break;
				case 'developers':
					$title = _x( 'Developers', 'plugin tab title', 'wporg-plugins' );
					$url = trailingslashit( $url ) . '/' . $section_slug . '/';
					break;
			}
			$sections[] = array(
				'slug'  => $section_slug,
				'url'   => $url,
				'title' => $title,
			);
		}
		return $sections;
	}
}
	
