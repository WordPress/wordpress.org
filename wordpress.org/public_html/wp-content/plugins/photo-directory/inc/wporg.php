<?php
/**
 * WordPress.org-specific customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class WPorg {

	/**
	 * Initializes component.
	 */
	public static function init() {
		// Restrict access to the site to capes, site members, and those explicitly allowed via filter.
		//add_action( 'init', [ __CLASS__, 'redirect_if_not_allowed' ], 1 );

		// Don't swap author link with w.org profile link.
		remove_filter( 'author_link', 'wporg_author_link_to_profiles', 10 );
	}

	/**
	 * Determines if the current user is allowed to access the site.
	 *
	 * During the initial testing phase, only these users are allowed access to
	 * the site:
	 * - Caped superusers
	 * - Anyone explicitly added as a user (of any role) on the site
	 * - Anyhow explicitly allowed via the 'wporg_photos_can_user_access_site'
	 *   filter
	 *
	 * @return bool True if the current user can access the site, else false.
	 */
	public static function is_user_allowed_to_access_site() {
		$allowed = false;

		$user = wp_get_current_user();
		$is_caped = function_exists( 'is_caped' ) && is_caped( $user->ID );

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if (
			// Allow caped users.
			$is_caped
		||
			// Allow explicit users of the site.
			is_user_member_of_blog()
		||
			// Allow superadmins.
			current_user_can( 'manage_options' )
		||
			// Allow anyone explicitly allowed.
			/**
			 * Filters whether a given user should be allowed to access the site
			 * (if they are't already allowed by virtue of being a caped user or
			 * a member of the site).
			 *
			 * @param bool $can_access Can the user access the site? Default false.
			 * @param int  $user_id    The user ID.
			 */
			apply_filters( 'wporg_photos_can_user_access_site', false, $user->ID )
		) {
			$allowed = true;
		}

		return $allowed;
	}

	/**
	 * Prevent access to the entire site unless user is explicitly allowed.
	 */
	public static function redirect_if_not_allowed() {
		// Don't redirect WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Don't redirect cron.
		if ( wp_doing_cron() ) {
			return;
		}

		// Don't redirect the admin.
		if ( is_admin() ) {
			return;
		}

		// Don't redirect the login page.
		if ( false !== stripos( wp_login_url(), $_SERVER['SCRIPT_NAME'] ) ) {
			return;
		}

		$do_redirect = false;

		// Redirect if user is not allowed to access the site.
		if ( ! $do_redirect ) {
			$do_redirect = ! self::is_user_allowed_to_access_site();
		}

		// Perform redirect if appropriate.
		if ( $do_redirect ) {
			wp_redirect( 'https://wordpress.org' );
		}
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\WPorg', 'init' ] );
