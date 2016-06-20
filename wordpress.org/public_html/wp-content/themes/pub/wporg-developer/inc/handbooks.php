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
	public static $post_types = array( 'plugin', 'theme' );

	/**
	 * Initializer
	 *
	 * @access public
	 */
	public static function init() {
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

		add_action( 'pre_get_posts',  array( __CLASS__, 'pre_get_posts' ), 9 );

		add_action( 'after_switch_theme', array( __CLASS__, 'add_roles' ) );

		add_filter( 'user_has_cap', array( __CLASS__, 'adjust_handbook_editor_caps' ), 11 );

		add_filter( 'the_content', array( __CLASS__, 'autolink_credits' ) );

		// Add the handbook's 'Watch' action link.
		if ( class_exists( 'WPorg_Handbook_Watchlist' ) && method_exists( 'WPorg_Handbook_Watchlist', 'display_action_link' ) ) {
			add_action( 'wporg_action_links', array( 'WPorg_Handbook_Watchlist', 'display_action_link' ) );
		}

		// Modify SyntaxHighlighter Evolved code output to facilitate code collapse/expand.
		if ( ! is_admin() ) {
			add_filter( 'syntaxhighlighter_htmlresult', array( __CLASS__, 'syntaxhighlighter_htmlresult' ) );
		}
	}

	/**
	 * Add public query vars for handbooks.
	 *
	 * @param array   $public_query_vars The array of whitelisted query variables.
	 * @return array Array with public query vars.
	 */
	public static function add_query_vars( $public_query_vars ) {
		$public_query_vars['is_handbook'] = false;
		$public_query_vars['current_handbook'] = false;
		$public_query_vars['current_handbook_home_url'] = false;
		$public_query_vars['current_handbook_name'] = '';

		return $public_query_vars;
	}

	/**
	 * Add handbook query vars to the current query.
	 *
	 * @param \WP_Query $query
	 */
	public static function pre_get_posts( $query ) {
		$is_handbook = function_exists( 'wporg_is_handbook' ) ? wporg_is_handbook() : false;
		$query->set( 'is_handbook', $is_handbook );

		$current_handbook = function_exists( 'wporg_get_current_handbook' ) ? wporg_get_current_handbook() : false;
		$query->set( 'current_handbook', $current_handbook );

		$current_handbook_home_url = function_exists( 'wporg_get_current_handbook_home_url' ) ? wporg_get_current_handbook_home_url() : false;
		$query->set( 'current_handbook_home_url', $current_handbook_home_url );

		$current_handbook_name = function_exists( 'wporg_get_current_handbook_name' ) ? wporg_get_current_handbook_name() : '';
		$query->set( 'current_handbook_name', $current_handbook_name );
	}

	/**
	 * If a syntax highlighted code block exceeds a given number of lines, wrap the
	 * markup with other markup to trigger the code expansion/collapse JS handling
	 * already implemented for the code reference.
	 *
	 * @param string  $text The pending result of the syntax highlighting.
	 * @return string
	 */
	public static function syntaxhighlighter_htmlresult( $text ) {
		$new_text      = '';
		// Collapse is handled for >10 lines. But just go ahead and show the full
		// code if that is just barely being exceeded (no one wants to expand to
		// see one or two more lines).
		$lines_to_show = 12;
		$do_collapse   = ( substr_count( $text, "\n" ) - 1 ) > $lines_to_show;

		if ( $do_collapse )  {
			$new_text .= '<section class="source-content">';
			$new_text .= '<div class="source-code-container">';
		}

		$new_text .= $text;

		if ( $do_collapse ) {
			$new_text .= '</div>';
			$new_text .= '<p class="source-code-links"><span>';
			$new_text .= '<a href="#" class="show-complete-source">' . __( 'Expand full source code', 'wporg' ) . '</a>';
			$new_text .= '<a href="#" class="less-complete-source">' . __( 'Collapse full source code', 'wporg' ) . '</a>';
			$new_text .= '</span></p>';
			$new_text .= '</section>';
		}

		return $new_text;
	}

	/**
	 * Filter handbook post types to create handbooks for: plugins, themes.
	 *
	 * @access public
	 *
	 * @param  array $types The default handbook types.
	 * @return array
	*/
	public static function filter_handbook_post_types( $types ) {
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
	 * @param  string $post_type The post type name.
	 * @return array
	 */
	public static function filter_handbook_post_type_defaults( $defaults, $post_type ) {
		$defaults['rewrite'] = array(
			'feeds'      => false,
			'slug'       => $post_type . 's',
			'with_front' => false,
		);

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
						'<a href="https://profiles.wordpress.org/%s">@%s</a>',
						esc_attr( $matches[1] ),
						esc_html( $matches[1] )
					);
				},
				$content
			);
		}

		return $content;
	}

} // Devhub_Handbooks

Devhub_Handbooks::init();
