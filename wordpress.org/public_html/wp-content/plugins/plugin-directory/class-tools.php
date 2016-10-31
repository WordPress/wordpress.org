<?php
namespace WordPressdotorg\Plugin_Directory;
use WP_User;

/**
 * Various functions used by other processes, will make sense to move to specific classes.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Tools {

	/**
	 * Retrieve the average color of a specified image.
	 *
	 * This currently relies upon the Jetpack libraries.
	 *
	 * @static
	 *
	 * @param $file_location string URL or filepath of image.
	 * @return string|bool Average color as a hex value, False on failure.
	 */
	public static function get_image_average_color( $file_location ) {
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
	 * Returns the two latest reviews of a specific plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @todo Populate with review title/content.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return array|false
	 */
	public static function get_plugin_reviews( $plugin_slug, $number = 2 ) {
		$number = absint( $number );
		if ( $number < 1 || $number > 100 ) {
			$number = 2;
		}
		if ( false === ( $reviews = wp_cache_get( "{$plugin_slug}_last{$number}_reviews", 'wporg-plugins' ) ) ) {
			global $wpdb;

			$reviews = $wpdb->get_results( $wpdb->prepare(
			"SELECT
					post_content, post_title, post_author, post_modified,
					r.rating as post_rating
			FROM ratings r
				LEFT JOIN wporg_419_posts p ON r.post_id = p.ID
			WHERE r.object_type = 'plugin' AND r.object_slug = %s AND p.post_status = 'publish'
			ORDER BY r.review_id DESC
			LIMIT %d", $plugin_slug, $number ) );

			wp_cache_set( "{$plugin_slug}_last{$number}_reviews", $reviews, 'wporg-plugins', HOUR_IN_SECONDS );
		}

		return $reviews;
	}

	/**
	 * Retrieve a list of users who have commit to a specific plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return array The list of user_login's which have commit.
	 */
	public static function get_plugin_committers( $plugin_slug ) {
		if ( false === ( $committers = wp_cache_get( "{$plugin_slug}_committer", 'wporg-plugins' ) ) ) {
			global $wpdb;

			$committers = $wpdb->get_col( $wpdb->prepare( 'SELECT user FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE path = %s', "/{$plugin_slug}" ) );

			wp_cache_set( "{$plugin_slug}_committer", $committers, 'wporg-plugins' );
		}

		return $committers;
	}

	/**
	 * Retrieve a list of plugins a specific user has commit to.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|\WP_User $user The user.
	 * @return array The list of plugins the user has commit to.
	 */
	public static function get_users_write_access_plugins( $user ) {
		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}

		if ( ! $user->exists() ) {
			return false;
		}

		if ( false === ( $plugins = wp_cache_get( "{$user->user_login}_committer", 'wporg-plugins' ) ) ) {
			global $wpdb;

			$plugins = $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE user = %s', $user->user_login ) );
			$plugins = array_map( function ( $plugin ) {
				return trim( $plugin, '/' );
			}, $plugins );

			wp_cache_set( "{$user->user_login}_committer", $plugins, 'wporg-plugins' );
		}

		return $plugins;
	}

	/**
	 * Syncs the list of committers from the svn_access table to a taxonomy.
	 *
	 * @static
	 * @param string $plugin_slug The Plugin Slug to sync.
	 */
	public static function sync_plugin_committers_with_taxonomy( $plugin_slug ) {
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$committer_slugs = array();
		foreach ( Tools::get_plugin_committers( $plugin_slug ) as $committer ) {
			$user = get_user_by( 'login', $committer );
			if ( $user ) {
				$committer_slugs[] = $user->user_nicename;
			}
		}

		wp_set_post_terms( $post->ID, $committer_slugs, 'plugin_committers' );
	}

	/**
	 * Grant a user RW access to a plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
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

		$result = (bool) $wpdb->insert( PLUGINS_TABLE_PREFIX . 'svn_access', array(
			'path'   => "/{$plugin_slug}",
			'user'   => $user->user_login,
			'access' => 'rw',
		) );

		wp_cache_delete( "{$plugin_slug}_committer", 'wporg-plugins' );
		wp_cache_delete( "{$user->user_login}_committer", 'wporg-plugins' );
		Tools::sync_plugin_committers_with_taxonomy( $plugin_slug );

		return $result;
	}

	/**
	 * Revoke a users RW access to a plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
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

		$result = (bool) $wpdb->delete( PLUGINS_TABLE_PREFIX . 'svn_access', array(
			'path' => "/{$plugin_slug}",
			'user' => $user->user_login,
		) );

		wp_cache_delete( "{$plugin_slug}_committer", 'wporg-plugins' );
		wp_cache_delete( "{$user->user_login}_committer", 'wporg-plugins' );
		Tools::sync_plugin_committers_with_taxonomy( $plugin_slug );

		return $result;
	}

	/**
	 * Subscribe/Unsubscribe a user to a plugins commits.
	 *
	 * Plugin Committers are automatically subscribed to plugin commit
	 * emails and cannot unsubscribe.
	 *
	 * @static
	 *
	 * @param string      $plugin_slug The plugin to subscribe to.
	 * @param int|WP_User $user        Optional. The user to subscribe. Default current user.
	 * @param bool        $subscribe   Optional. Whether to subscribe (true) or unsubscribe (false).
	 *                                 Default: true.
	 * @return bool Whether the user is subscribed.
	 */
	public static function subscribe_to_plugin_commits( $plugin_slug, $user = 0, $subscribe = true ) {
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $user->exists() ) {
			return false;
		}

		$users = get_post_meta( $post->ID, '_commit_subscribed', true ) ?: array();

		if ( $subscribe ) {
			$users[] = $user->ID;
			$users = array_unique( $users );
		} else {
			if ( false !== ( $pos = array_search( $user->ID, $users, true ) ) ) {
				unset( $users[ $pos ] );
			}
		}

		update_post_meta( $post->ID, '_commit_subscribed', $users );

		return self::subscribed_to_plugin_commits( $plugin_slug, $user->ID );
	}

	/**
	 * Determine if a user is subscribed to a plugins commits.
	 *
	 * Plugin Committers are automatically subscribed to commits, and this
	 * function does not respect that status.
	 *
	 * @static
	 *
	 * @param string      $plugin_slug The plugin to subscribe to.
	 * @param int|WP_User $user        Optional. The user to check. Default current user.
	 * @return bool Whether the specified user is subscribed to commits.
	 */
	public static function subscribed_to_plugin_commits( $plugin_slug, $user = 0 ) {
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $user->exists() ) {
			return false;
		}

		$users = get_post_meta( $post->ID, '_commit_subscribed', true ) ?: array();

		return in_array( $user->ID, $users, true );
	}

	/**
	 * Determine if a plugin has been favorited by a user.
	 *
	 * @param string $plugin_slug The plugin to check.
	 * @param mixed  $user        The user to check.
	 * @return bool
	 */
	public static function favorited_plugin( $plugin_slug, $user = 0 ) {
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $user->exists() ) {
			return false;
		}

		$users_favorites = get_user_meta( $user->ID, 'plugin_favorites', true ) ?: array();

		return in_array( $post->post_name, $users_favorites, true );
	}

	/**
	 * Favorite a plugin
	 *
	 * @param string $plugin_slug The plugin to favorite
	 * @param mixed  $user        The user favorite
	 * @param bool   $favorite    Whether it's a favorite, or unfavorite.
	 * @return bool
	 */
	public static function favorite_plugin( $plugin_slug, $user = 0, $favorite = true ) {
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $user->exists() ) {
			return false;
		}

		$users_favorites = get_user_meta( $user->ID, 'plugin_favorites', true ) ?: array();

		$already_favorited = in_array( $post->post_name, $users_favorites, true );

		if ( $favorite && $already_favorited ) {
			return true;
		} elseif ( $favorite ) {
			// Add it
			$users_favorites[] = $post->post_name;
		} elseif ( ! $favorite && $already_favorited ) {
			// Remove it
			unset( $users_favorites[ array_search( $post->post_name, $users_favorites, true ) ] );
		} else {
			return true;
		}

		return update_user_meta( $user->ID, 'plugin_favorites', wp_slash( array_values( $users_favorites ) ) );

	}

	/**
	 * Retrieve a list of users who are subscribed to plugin commits.
	 *
	 * @param string $plugin_slug       The plugin to retrieve subscribers for.
	 * @param bool   $include_committers Whether to include Plugin Committers in the list. Default false. 
	 * @return array Array of \WP_User's who are subscribed.
	 */
	public static function get_plugin_subscribers( $plugin_slug, $include_committers = false ) {
		global $wpdb;

		$users = array();

		// Include the subscribers from the bbPress plugin directory until we've fully migrated.
		$bbpress_subscribers = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( 'SELECT m.meta_value FROM ' . PLUGINS_TABLE_PREFIX . 'topics t JOIN ' . PLUGINS_TABLE_PREFIX . 'meta m ON ( m.object_type = "bb_topic" AND m.object_id = t.topic_id AND m.meta_key = "commit_subscribed") WHERE t.topic_slug = %s', $plugin_slug ) ) );
		if ( $bbpress_subscribers ) {
			foreach ( array_keys( $bbpress_subscribers ) as $subscriber_id ) {
				if ( $subscriber_id && $user = get_user_by( 'id', $subscriber_id ) ) {
					$users[] = $user;
				}
			}
		}

		// Plugin Committers are always subscrived to plugin commits.
		$committers  = self::get_plugin_committers( $plugin_slug );
		foreach ( $committers as $committer ) {
			if ( $committer && $user = get_user_by( 'login', $committer ) ) {
				$users[] = $user;
			}
		}

		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return $users;
		}

		// These users are subscribed the plugin commits.
		$subscribers = get_post_meta( $post->ID, '_commit_subscribed', true ) ?: array();
		foreach ( $subscribers as $subscriber_id ) {
			if ( $subscriber_id && $user = get_user_by( 'id', $subscriber_id ) ) {
				$users[] = $user;
			}
		}

		return $users;
	}
}
