<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Various functions used by other processes, will make sense to move to specific classes.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Tools {

	/**
	 * Retrieve the average color of a specified image.
	 * This currently relies upon the Jetpack libraries.
	 *
	 * @param $file_location string URL or filepath of image
	 * @return string|bool Average color as a hex value, False on failure
	 */
	static function get_image_average_color( $file_location ) {
		if ( ! class_exists( 'Tonesque' ) && function_exists( 'jetpack_require_lib' ) ) {
			jetpack_require_lib( 'tonesque' );
		}
		if ( ! class_exists( 'Tonesque' ) ) {
			return false;
		}

		$tonesque = new \Tonesque( $file_location );

		return $tonesque->color();
	}

	/**
	 * Fetch the latest 10 reviews for a given plugin from the database.
	 *
	 * This uses raw SQL to query the bbPress tables to fetch reviews.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $plugin_slug The slug of the plugin.
	 * @param array  $args        {
	 *     Optional. Query arguments.
	 *
	 *     @type int $number The amount of reviews to return. Default: 10.
	 * }
	 * @return array An array of reviews.
	 */
	public static function get_plugin_reviews( $plugin_slug, $args = array() ) {

		// Reviews are stored in the main support forum, which isn't open sourced yet.
		if ( ! defined( 'WPORGPATH' ) || ! defined( 'CUSTOM_USER_TABLE' ) ) {
			return array();
		}

		if ( false === ( $reviews = wp_cache_get( $plugin_slug, 'reviews' ) ) ) {
			global $wpdb;

			$args = wp_parse_args( $args, array(
				'number' => 10,
			) );

			// The forums are the source for users, and also where reviews live.
			$table_prefix = str_replace( 'users', '', CUSTOM_USER_TABLE );
			$forum_id     = 18; // The Review Forums ID.

			$reviews = $wpdb->get_results( $wpdb->prepare( "
				SELECT
					t.topic_id, t.topic_title, t.topic_poster, t.topic_start_time,
					p.post_text,
					ratings.rating,
					tm_wp.meta_value as wp_version
				FROM {$table_prefix}topics AS t
				JOIN {$table_prefix}meta AS tm ON ( tm.object_type = 'bb_topic' AND t.topic_id = tm.object_id AND tm.meta_key = 'is_plugin' )
				JOIN {$table_prefix}posts as p ON ( t.topic_id = p.topic_id AND post_status = 0 AND post_position = 1 )
				JOIN ratings ON (t.topic_id = ratings.review_id )
				LEFT JOIN {$table_prefix}meta AS tm_wp ON ( tm_wp.object_type = 'bb_topic' AND t.topic_id = tm_wp.object_id AND tm_wp.meta_key = 'wp_version' )
				WHERE t.forum_id = %d AND t.topic_status = 0 AND t.topic_sticky = 0 AND tm.meta_value = %s
				ORDER BY t.topic_start_time DESC
				LIMIT %d",
				$forum_id,
				$plugin_slug,
				absint( $args['number'] )
			) );

			wp_cache_set( $plugin_slug, $reviews, 'reviews' );
		}

		return $reviews;
	}

	/**
	 * Retrieve a list of users who have commit to a specific plugin.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return array The list of user_login's which have commit.
	 */
	public static function get_plugin_committers( $plugin_slug ) {
		global $wpdb;

		if ( false === ( $committers = wp_cache_get( "{$plugin_slug}_committer", 'wporg-plugins' ) ) ) {
			$committers = $wpdb->get_col( $wpdb->prepare( 'SELECT user FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE path = %s', "/{$plugin_slug}" ) );

			wp_cache_set( "{$plugin_slug}_committer", $committers, 'wporg-plugins' );
		}

		return $committers;
	}

	/**
	 * Retrieve a list of plugins a specific user has commit to.
	 *
	 * @param int|\WP_User $user The user.
	 * @return array The list of plugins the user has commit to.
	 */
	public static function get_users_write_access_plugins( $user ) {
		global $wpdb;
		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}
		if ( ! $user->exists() ) {
			return false;
		}

		if ( false === ( $plugins = wp_cache_get( "{$user->user_login}_committer", 'wporg-plugins' ) ) ) {
			$plugins = $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE user = %s', $user->user_login ) );
			$plugins = array_map( function( $plugin ) { return trim( $plugin, '/' ); }, $plugins );

			wp_cache_set( "{$user->user_login}_committer", $plugins, 'wporg-plugins' );
		}

		return $plugins;

	}

	/**
	 * Grant a user RW access to a plugin.
	 *
	 * @param string          $plugin_slug The plugin slug.
	 * @param string|\WP_User $user        The user to grant access to.
	 * @return bool
	 */
	public static function grant_plugin_committer( $plugin_slug, $user ) {
		global $wpdb;

		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}

		if ( ! $user->exists() || ! Plugin_Directory::get_plugin_post( $plugin_slug ) ) {
			return false;
		}

		$existing_committers = self::get_plugin_committers( $plugin_slug );
		if ( in_array( $user->user_login, $existing_committers, true ) ) {
			// User already has write access.
			return true;
		}

		wp_cache_delete( "{$plugin_slug}_committer", 'wporg-plugins' );
		wp_cache_delete( "{$user->user_login}_committer", 'wporg-plugins' );

		return (bool) $wpdb->insert(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array(
				'path'   => "/{$plugin_slug}",
				'user'   => $user->user_login,
				'access' => 'rw',
			)
		);
	}

	/**
	 * Revoke a users RW access to a plugin.
	 *
	 * @param string          $plugin_slug The plugin slug.
	 * @param string|\WP_User $user        The user to revoke access of.
	 * @return bool
	 */
	public static function revoke_plugin_committer( $plugin_slug, $user ) {
		global $wpdb;

		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}

		if ( ! $user->exists() || ! Plugin_Directory::get_plugin_post( $plugin_slug ) ) {
			return false;
		}

		wp_cache_delete( "{$plugin_slug}_committer", 'wporg-plugins' );
		wp_cache_delete( "{$user->user_login}_committer", 'wporg-plugins' );

		return $wpdb->delete(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array(
				'path' => "/{$plugin_slug}",
				'user' => $user->user_login,
			)
		);
	}
}
