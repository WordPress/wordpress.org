<?php
/**
 * Code Reference configuration and customization of the Handbook plugin.
 *
 * @package wporg-developer
 */

/**
 * Class to handle handbook configuration and behavior.
 */
class Devhub_Handbooks {

	/**
	 * Handbook names.
	 *
	 * @var array
	 * @access public
	 */
	public static $post_types = [];

	/**
	 * Hidden handbook post types.
	 *
	 * Note: Hidden only from users who aren't logged in.
	 *
	 * @var array
	 * @access public
	 */
	public static $hidden_handbooks = [];

	/**
	 * Initializer
	 *
	 * @access public
	 */
	public static function init() {
		add_filter( 'handbook_label', array( __CLASS__, 'change_handbook_label' ), 10, 2 );
		add_filter( 'handbook_post_type_defaults', array( __CLASS__, 'filter_handbook_post_type_defaults' ), 10, 2 );
		add_filter( 'handbook_post_types', array( __CLASS__, 'filter_handbook_post_types' ) );
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Initialization
	 *
	 * @access public
	 */
	public static function do_init() {
		add_filter( 'query_vars',  array( __CLASS__, 'add_query_vars' ) );

		add_action( 'template_redirect', array( __CLASS__, 'redirect_hidden_handbooks' ), 1 );

		add_action( 'pre_get_posts',  array( __CLASS__, 'pre_get_posts' ), 9 );

		add_action( 'after_switch_theme', array( __CLASS__, 'add_roles' ) );

		add_filter( 'user_has_cap', array( __CLASS__, 'adjust_handbook_editor_caps' ), 11 );

		add_filter( 'the_content', array( __CLASS__, 'autolink_credits' ) );

		// Add class to REST API handbook reference pages.
		add_filter( 'body_class', array( __CLASS__, 'add_rest_api_handbook_reference_class' ) );

		// Add the handbook's 'Watch' action link.
		if ( class_exists( 'WPorg_Handbook_Watchlist' ) && method_exists( 'WPorg_Handbook_Watchlist', 'display_action_link' ) ) {
			add_action( 'wporg_action_links', array( 'WPorg_Handbook_Watchlist', 'display_action_link' ) );
		}
	}

	/**
	 * Add public query vars for handbooks.
	 *
	 * @param array   $public_query_vars The array of whitelisted query variables.
	 * @return array Array with public query vars.
	 */
	public static function add_query_vars( $public_query_vars ) {
		$public_query_vars[] = 'current_handbook';
		$public_query_vars[] = 'current_handbook_home_url';
		$public_query_vars[] = 'current_handbook_name';

		return $public_query_vars;
	}

	/**
	 * Redirects handbooks that should be inaccessible to visitors who aren't logged in.
	 */
	public static function redirect_hidden_handbooks() {
		if ( ! self::$hidden_handbooks || get_current_user_id() || ! function_exists( 'wporg_is_handbook' ) || ! wporg_is_handbook() ) {
			return;
		}

		if ( in_array( wporg_get_current_handbook(), self::$hidden_handbooks ) ) {
			wp_safe_redirect( home_url() );
			exit();
		}	
	}

	/**
	 * Add handbook query vars to the current query.
	 *
	 * @param \WP_Query $query
	 */
	public static function pre_get_posts( $query ) {
		$query->is_handbook = function_exists( 'wporg_is_handbook' ) && wporg_is_handbook();

		$current_handbook = function_exists( 'wporg_get_current_handbook' ) ? (string) wporg_get_current_handbook() : '';
		$query->set( 'current_handbook', $current_handbook );

		$current_handbook_home_url = function_exists( 'wporg_get_current_handbook_home_url' ) ? (string) wporg_get_current_handbook_home_url() : '';
		$query->set( 'current_handbook_home_url', $current_handbook_home_url );

		$current_handbook_name = function_exists( 'wporg_get_current_handbook_name' ) ? (string) wporg_get_current_handbook_name() : '';
		$query->set( 'current_handbook_name', $current_handbook_name );
	}

	/**
	 * Filter handbook post types to create handbooks for: apis, plugins, themes.
	 *
	 * @access public
	 *
	 * @param  array $types The default handbook types.
	 * @return array
	*/
	public static function filter_handbook_post_types( $types ) {
		if ( ! self::$post_types ) {
			self::$post_types = apply_filters( 'devhub_handbook_post_types', [ 'apis', 'plugin', 'theme' ] );
		}

		return self::$post_types;
	}

	/**
	 * Create the handbook_editor role which can only edit handbooks.
	 *
	 * @access public
	 *
	 */
	public static function add_roles() {
		add_role(
			'handbook_editor',
			__( 'Handbook Editor', 'wporg' ),
			array(
				'moderate_comments'             => true,
				'upload_files'                  => true,
				'unfiltered_html'               => true,
				'read'                          => true,
				'edit_handbook_pages'           => true,
				'edit_others_handbook_pages'    => true,
				'edit_published_handbook_pages' => true,
				'edit_private_handbook_pages'   => true,
				'read_private_handbook_pages'   => true,
			)
		);
	}

	/**
	 * Adjusts handbook capabilities for roles.
	 *
	 * Undoes some capability assignments by the handbook plugin since only
	 * administrators, editors, and handbook_editors can manipulate handbooks.
	 *
	 * @access public
	 *
	 * @param  array $caps Array of user capabilities.
	 * @return array
	 */
	public static function adjust_handbook_editor_caps( $caps ) {
		if ( ! is_user_member_of_blog() || ! class_exists( 'WPorg_Handbook' ) ) {
			return $caps;
		}

		// Get current user's role.
		$role = wp_get_current_user()->roles[0];

		// Unset caps set by handbook plugin.
		// Only administrators, editors, and handbook_editors can manipulate handbooks.
		if ( ! in_array( $role, array( 'administrator', 'editor', 'handbook_editor' ) ) ) {
			foreach ( \WPorg_Handbook::caps() as $cap ) {
				unset( $caps[ $cap ] );
			}

			foreach ( \WPorg_Handbook::editor_caps() as $cap ) {
				unset( $caps[ $cap ] );
			}
		}

		return $caps;
	}

	/**
	 * Overrides default handbook post type configuration.
	 *
	 * Specifically, uses a plural slug while retaining pre-existing singular post
	 * type name.
	 *
	 * @access public
	 *
	 * @param  array  $defaults  The default post type configuration.
	 * @param  string $slug      The handbook post type slug.
	 * @return array
	 */
	public static function filter_handbook_post_type_defaults( $defaults, $slug ) {
		// Pluralize slug for plugin and theme handbooks.
		if ( in_array( $slug, array( 'plugin', 'theme' ) ) ) {
			$defaults['rewrite'] = array(
				'feeds'      => false,
				'slug'       => $slug . 's',
				'with_front' => false,
			);
		}

		$defaults['show_in_rest'] = true;

		return $defaults;
	}

	/**
	 * For specific credit pages, link @usernames references to their profiles on
	 * profiles.wordpress.org.
	 *
	 * Simplistic matching. Does not verify that the @username is a legitimate
	 * WordPress.org user.
	 *
	 * @param  string $content Post content
	 * @return string
	 */
	public static function autolink_credits( $content ) {
		// Only apply to the 'credits' (themes handbook) and 'credits-2' (plugin
		// handbook) pages
		if ( is_single( 'credits' ) || is_single( 'credits-2' ) ) {
			$content = preg_replace_callback(
				'/\B@([\w\-]+)/i',
				function ( $matches ) {
					return sprintf(
						'<a href="https://profiles.wordpress.org/%s/">@%s</a>',
						esc_attr( $matches[1] ),
						esc_html( $matches[1] )
					);
				},
				$content
			);
		}

		return $content;
	}

	/**
	 * Adds class to REST API handbook reference sub-pages.
	 *
	 * Due to special formatting of particular pages in the REST API handbook, a
	 * class is needed to target CSS rules.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function add_rest_api_handbook_reference_class( $classes ) {
		if (
			is_single()
			&&
			'rest-api-handbook' === get_query_var( 'current_handbook' )
			&&
			( ( $parent = wp_get_post_parent_id( get_the_ID() ) ) && ( 'reference' === get_post( $parent )->post_name ) )
		) {
			$classes[] = 'rest-api-handbook-reference';
		}

		return $classes;
	}

	/**
	 * Overrides the default handbook label when post type name does not directly
	 * translate to post type label.
	 *
	 * @param string $label     The default label, which is merely a sanitized
	 *                          version of the handbook name.
	 * @param string $post_type The handbook post type.
	 * @return string
	 */
	public static function change_handbook_label( $label, $post_type ) {
		if ( 'apis-handbook' === $post_type ) {
			$label = __( 'Common APIs Handbook', 'wporg' );
		}

		return $label;
	}

} // Devhub_Handbooks

Devhub_Handbooks::init();
