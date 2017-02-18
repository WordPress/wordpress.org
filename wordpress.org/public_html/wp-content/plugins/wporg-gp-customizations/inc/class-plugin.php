<?php

namespace WordPressdotorg\GlotPress\Customizations;

use WP_CLI;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	function plugins_loaded() {
		add_filter( 'gp_url_profile', array( $this, 'worg_profile_url' ), 10, 2 );
		add_filter( 'gp_projects', array( $this, 'natural_sort_projects' ), 10, 2 );
		add_action( 'gp_project_created', array( $this, 'update_projects_last_updated' ) );
		add_action( 'gp_project_saved', array( $this, 'update_projects_last_updated' ) );
		add_filter( 'pre_handle_404', array( $this, 'short_circuit_handle_404' ) );
		add_action( 'wp_default_scripts', array( $this, 'bump_script_versions' ) );
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		add_filter( 'body_class', array( $this, 'wporg_add_make_site_body_class' ) );
		add_filter( 'wporg_translate_language_pack_theme_args', array( $this, 'set_version_for_twentyseventeen_language_pack' ), 10, 2 );

		// Toolbar.
		add_action( 'admin_bar_menu', array( $this, 'add_profile_settings_to_admin_bar' ) );
		add_action( 'admin_bar_init', array( $this, 'show_admin_bar' ) );
		add_action( 'add_admin_bar_menus', array( $this, 'remove_admin_bar_menus' ) );

		add_action( 'template_redirect', array( $this, 'jetpack_stats' ), 1 );

		// Load the API endpoints.
		add_action( 'rest_api_init', array( __NAMESPACE__ . '\REST_API\Base', 'load_endpoints' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Adds support for Jetpack Stats.
	 */
	public function jetpack_stats() {
		if ( ! function_exists( 'stats_hide_smile_css' ) ) {
			return;
		}

		add_action( 'gp_head', 'stats_hide_smile_css' );
		add_action( 'gp_head', 'stats_admin_bar_head', 100 );
		add_action( 'gp_footer', 'stats_footer', 101 );
	}

	/**
	 * Renders the toolbar.
	 */
	public function show_admin_bar() {
		add_action( 'gp_head', 'wp_admin_bar_header' );
		add_action( 'gp_head', '_admin_bar_bump_cb' );

		gp_enqueue_script( 'admin-bar' );
		gp_enqueue_style( 'admin-bar' );

		add_action( 'gp_footer', 'wp_admin_bar_render', 1000 );
	}

	/**
	 * Adds the linkt to profile settings to the user actions toolbar menu.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public function add_profile_settings_to_admin_bar( $wp_admin_bar ) {
		$logout_node = $wp_admin_bar->get_node( 'logout' );
		$wp_admin_bar->remove_node( 'logout' );

		$wp_admin_bar->add_node( [
			'parent' => 'user-actions',
			'id'     => 'gp-profile-settings',
			'title'  => 'Translate Settings',
			'href'   => gp_url( '/settings' ),
		] );

		if ( $logout_node ) {
			$wp_admin_bar->add_node( $logout_node ); // Ensures that logout is the last action.
		}
	}

	/**
	 * Removes default toolbar menus which are not needed.
	 */
	public function remove_admin_bar_menus() {
		remove_action( 'admin_bar_menu', 'wp_admin_bar_search_menu', 4 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu', 70 );
		remove_action( 'admin_bar_menu', 'wp_admin_bar_edit_menu', 80 );
	}

	/**
	 * Defines a version for Twenty Seventeen which isn't in the directory yet.
	 *
	 * @param array  $args WP-CLI arguments.
	 * @param string $slug Slug of a theme.
	 * @return array Filtered WP-CLI arguments.
	 */
	public function set_version_for_twentyseventeen_language_pack( $args, $slug ) {
		if ( 'twentyseventeen' !== $slug || ! empty( $args['version'] ) ) {
			return $args;
		}

		$args['version'] = '1.0';

		return $args;
	}

	/**
	 * Registers a menu location for a sub-navigation.
	 */
	public function after_setup_theme() {
		register_nav_menu( 'wporg_header_subsite_nav', 'WordPress.org Header Sub-Navigation' );
	}

	/**
	 * Adds the CSS classes from make/polyglots to the body to sync the headline icon.
	 */
	function wporg_add_make_site_body_class( $classes ) {
		$classes[] = 'wporg-make';
		$classes[] = 'make-polyglots';
		return $classes;
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	public function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate init-locale', __NAMESPACE__ . '\CLI\Init_Locale' );
		WP_CLI::add_command( 'wporg-translate language-pack', __NAMESPACE__ . '\CLI\Language_Pack' );
	}

	/**
	 * Changes the versions of some default scripts for cache bust.
	 *
	 * @see https://wordpress.slack.com/archives/meta-i18n/p1460626195000251
	 *
	 * @param WP_Scripts &$scripts WP_Scripts instance, passed by reference.
	 */
	public function bump_script_versions( &$scripts ) {
		foreach ( [ 'gp-editor', 'gp-glossary' ] as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) && '20160329' === $scripts->registered[ $handle ]->ver ) {
				$scripts->registered[ $handle ]->ver = '20160329a';
			}
		}
	}

	/**
	 * Short circuits WordPress' status handler to prevent unnecessary headers
	 * which prevent caching.
	 *
	 * @return bool True if a request for GlotPress, false if not.
	 */
	public function short_circuit_handle_404() {
		if ( is_glotpress() ) {
			return true;
		}

		return false;
	}

	/**
	 * Stores the timestamp of the last update for projects.
	 *
	 * Used by the Rosetta Roles plugin to invalidate local caches.
	 */
	public function update_projects_last_updated() {
		update_option( 'wporg_projects_last_updated', time() );
	}

	/**
	 * Returns the profile.wordpress.org URL of a user.
	 *
	 * @param string $url      Current profile URL.
	 * @param string $nicename The URL-friendly user name.
	 * @return string profile.wordpress.org URL
	 */
	public function worg_profile_url( $url, $nicename ) {
		return 'https://profiles.wordpress.org/' . $nicename;
	}

	/**
	 * Natural sorting for sub-projects, and attach whitelisted meta.
	 *
	 * @param GP_Project[] $sub_projects List of sub-projects.
	 * @param int          $parent_id    Parent project ID.
	 * @return GP_Project[] Filtered sub-projects.
	 */
	public function natural_sort_projects( $sub_projects, $parent_id ) {
		if ( in_array( $parent_id, array( 1, 13, 58 ) ) ) { // 1 = WordPress, 13 = BuddyPress, 58 = bbPress
			usort( $sub_projects, function( $a, $b ) {
				return - strcasecmp( $a->name, $b->name );
			} );
		}

		if ( in_array( $parent_id, array( 17, 523 ) ) ) { // 17 = Plugins, 523 = Themes
			usort( $sub_projects, function( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			} );
		}

		// Attach wp-themes meta keys
		if ( 523 == $parent_id ) {
			foreach ( $sub_projects as $project ) {
				$project->non_db_field_names = array_merge( $project->non_db_field_names, array( 'version', 'screenshot' ) );
				$project->version = gp_get_meta( 'wp-themes', $project->id, 'version' );
				$project->screenshot = esc_url( gp_get_meta( 'wp-themes', $project->id, 'screenshot' ) );
			}
		}

		return $sub_projects;
	}
}
