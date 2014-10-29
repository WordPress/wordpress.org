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
		add_filter( 'handbook_post_types', array( __CLASS__, 'filter_handbook_post_types' ) );
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Initialization
	 *
	 * @access public
	 */
	public static function do_init() {
		add_filter( 'user_has_cap', array( __CLASS__, 'adjust_handbook_editor_caps' ), 11 );

		add_filter( 'the_content', array( __CLASS__, 'autolink_credits' ) );

		// Add the handbook's 'Watch' action link.
		if ( class_exists( 'WPorg_Handbook_Watchlist' ) && method_exists( 'WPorg_Handbook_Watchlist', 'display_action_link' ) ) {
			add_action( 'wporg_action_links', array( 'WPorg_Handbook_Watchlist', 'display_action_link' ) );
		}
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
	 * For specific credit pages, link @usernames references to their profiles on
	 * profiles.wordpress.org.
	 *
	 * Simplistic matching. Does not verify that the @username is a legitimate
	 * WP.org user.
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
