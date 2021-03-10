<?php
namespace WordPressdotorg\Plugin_Directory;

use WP_Query;
use WP_User;
use WordPressdotorg\Plugin_Directory\Email\Committer_Added as Committer_Added_Email;
use WordPressdotorg\Plugin_Directory\Email\Support_Rep_Added as Support_Rep_Added_Email;

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
	 * Returns the latest reviews of a specific plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @todo Populate with review title/content.
	 *
	 * @param mixed $post The plugin slug, WP_Post, or ID.
	 * @return array|false
	 */
	public static function get_plugin_reviews( $post, $number = 6 ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		if ( ! $post ) {
			return false;
		}

		$number = absint( $number );
		if ( $number < 1 || $number > 100 ) {
			$number = 2;
		}

		$reviews = wp_cache_get( "{$post->post_name}_last{$number}", 'plugin-reviews' );
		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && false === $reviews ) {
			global $wpdb;

			$reviews = $wpdb->get_results( $wpdb->prepare(
				"SELECT
					ID, post_name, post_content, post_title, post_author, post_modified,
					r.rating as post_rating
				FROM ratings r
					LEFT JOIN " . $wpdb->base_prefix . WPORG_SUPPORT_FORUMS_BLOGID . "_posts p ON r.post_id = p.ID
				WHERE r.object_type = 'plugin' AND r.object_slug = %s AND p.post_status = 'publish'
				ORDER BY r.review_id DESC
				LIMIT %d",
				$post->post_name,
				$number
			) );

			wp_cache_set( "{$post->post_name}_last{$number}", $reviews, 'plugin-reviews', HOUR_IN_SECONDS );
		}

		return $reviews;
	}

	/**
	 * Retrieve a list of users who have commit to a specific plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param mixed  $post      The plugin slug, WP_Post, or ID.
	 * @param bool   $use_cache If we should use the cache, or fetch new data.
	 * @return array The list of user_login's which have commit.
	 */
	public static function get_plugin_committers( $post, $use_cache = true ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		if ( ! $post ) {
			return [];
		}

		$committers = wp_cache_get( $post->post_name, 'plugin-committers' );

		if ( false === $committers || ! $use_cache ) {
			global $wpdb;

			$committers = $wpdb->get_col( $wpdb->prepare(
				'SELECT user FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE path = %s',
				'/' . $post->post_name
			) );

			wp_cache_set( $post->post_name, $committers, 'plugin-committers', 12 * HOUR_IN_SECONDS );
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
		$user = new WP_User( $user );
		if ( ! $user->exists() ) {
			return false;
		}

		if ( false === ( $plugins = wp_cache_get( $user->user_login, 'committer-plugins' ) ) ) {
			global $wpdb;

			$plugins = $wpdb->get_col( $wpdb->prepare(
				'SELECT path FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE user = %s',
				$user->user_login
			) );
			$plugins = array_map( function ( $plugin ) {
				return trim( $plugin, '/' );
			}, $plugins );

			// Account for rare users with write access to '/'.
			$plugins = array_filter( $plugins );

			wp_cache_set( $user->user_login, $plugins, 'committer-plugins', 12 * HOUR_IN_SECONDS );
		}

		return $plugins;
	}

	/**
	 * Syncs the list of committers from the svn_access table to a taxonomy.
	 *
	 * @static
	 * @param mixed $post The plugin slug, WP_Post, or ID.
	 */
	public static function sync_plugin_committers_with_taxonomy( $post ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		if ( ! $post ) {
			return false;
		}

		$committer_slugs = [];
		foreach ( Tools::get_plugin_committers( $post, false /* Do not use the cache */ ) as $committer ) {
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
	 * @param mixed           $post The plugin slug, WP_Post, or ID.
	 * @param string|\WP_User $user The user to grant access to.
	 * @return bool
	 */
	public static function grant_plugin_committer( $post, $user ) {
		global $wpdb;

		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$existing_committers = Tools::get_plugin_committers( $post );
		if ( in_array( $user->user_login, $existing_committers, true ) ) {
			// User already has write access.
			return true;
		}

		$result = (bool) $wpdb->insert(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			[
				'path'   => '/' . $post->post_name,
				'user'   => $user->user_login,
				'access' => 'rw',
			]
		);

		wp_cache_delete( $post->post_name, 'plugin-committers' );
		wp_cache_delete( $user->user_login, 'committer-plugins' );
		Tools::sync_plugin_committers_with_taxonomy( $post );

		Tools::audit_log(
			sprintf(
				'Added <a href="%s">%s</a> as a committer.',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				$user->user_login
			),
			$post
		);

		// Only notify if the current process is interactive - a user is logged in.
		$should_notify = (bool) get_current_user_id();
		// Don't notify if a plugin admin is taking action on a plugin they're not (yet) a committer for.
		if ( current_user_can( 'plugin_approve' ) && ! in_array( wp_get_current_user()->user_login, $existing_committers, true ) ) {
			$should_notify = false;
		}

		if ( $should_notify ) {
			$existing_committers[] = $user->user_login;

			$email = new Committer_Added_Email(
				$post,
				$existing_committers,
				[
					'committer' => $user,
				]
			);
			$email->send();
		}

		return $result;
	}

	/**
	 * Revoke a users RW access to a plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param mixed           $post The plugin slug, WP_Post, or ID.
	 * @param string|\WP_User $user The user to revoke access of.
	 * @return bool
	 */
	public static function revoke_plugin_committer( $post, $user ) {
		global $wpdb;

		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$result = (bool) $wpdb->delete(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			[
				'path' => '/' . $post->post_name,
				'user' => $user->user_login,
			]
		);

		wp_cache_delete( $post->post_name, 'plugin-committers' );
		wp_cache_delete( $user->user_login, 'committer-plugins' );
		Tools::sync_plugin_committers_with_taxonomy( $post );

		Tools::audit_log(
			sprintf(
				'Removed <a href="%s">%s</a> as a committer.',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				$user->user_login
			),
			$post
		);

		return $result;
	}

	/**
	 * Retrieve a list of plugins for which a user is a support rep.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string|WP_User $user The user.
	 * @return array         Array of WP_Post objects.
	 */
	public static function get_support_rep_plugins( $user ) {
		global $wpdb;

		$user = new WP_User( $user );
		if ( ! $user->exists() ) {
			return [];
		}

		$plugins = wp_cache_get( $user->user_nicename, 'support-rep-plugins' );

		if ( false === $plugins ) {
			$plugins = get_posts( [
				'fields'         => 'ids',
				'post_type'      => 'plugin',
				'post_status'    => 'publish',
				'posts_per_page' => 250,
				'no_found_rows'  => true,
				'tax_query' => [
					[
						'taxonomy' => 'plugin_support_reps',
						'field'    => 'slug',
						'terms'    => $user->user_nicename,
					],
				],
			] );

			wp_cache_set( $user->user_nicename, $plugins, 'support-rep-plugins', 12 * HOUR_IN_SECONDS );
		}

		// ID's to objects.
		if ( $plugins && is_int( $plugins[0] ) ) {
			$plugins = array_map( 'get_post', $plugins );
		}

		return $plugins;
	}

	/**
	 * Retrieve a list of support reps for a specific plugin.
	 *
	 * @static
	 *
	 * @param mixed $post The plugin slug, WP_Post, or ID.
	 * @return array The list of user_nicename's which are support reps.
	 */
	public static function get_plugin_support_reps( $post ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		if ( ! $post ) {
			return [];
		}

		$support_reps = wp_cache_get( $post->post_name, 'plugin-support-reps' );

		if ( false === $support_reps ) {
			$support_reps = wp_get_object_terms( $post->ID, 'plugin_support_reps', [ 'fields' => 'names' ] );

			wp_cache_set( $post->post_name, $support_reps, 'plugin-support-reps', 12 * HOUR_IN_SECONDS );
		}

		return $support_reps;
	}

	/**
	 * Add a user as a support rep for a plugin.
	 *
	 * @static
	 *
	 * @param mixed           $post The plugin slug, WP_Post, or ID.
	 * @param string|\WP_User $user The user to add.
	 * @return bool
	 */
	public static function add_plugin_support_rep( $post, $user ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$result = wp_add_object_terms( $post->ID, $user->user_nicename, 'plugin_support_reps' );

		wp_cache_delete( $post->post_name, 'plugin-support-reps' );
		wp_cache_delete( $user->user_nicename, 'support-rep-plugins' );

		Tools::audit_log(
			sprintf(
				'Added <a href="%s">%s</a> as a support rep.',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				$user->user_login
			),
			$post
		);

		$committers = Tools::get_plugin_committers( $post );

		// Only notify if the current process is interactive - a user is logged in.
		$should_notify = (bool) get_current_user_id();
		// Don't notify if a plugin admin is taking action on a plugin they're not a committer for.
		if ( current_user_can( 'plugin_approve' ) && ! in_array( wp_get_current_user()->user_login, $committers, true ) ) {
			$should_notify = false;
		}

		if ( $should_notify ) {
			$email = new Support_Rep_Added_Email(
				$post,
				$committers,
				[
					'rep' => $user,
				]
			);
			$email->send();
		}

		return $result;
	}

	/**
	 * Remove a user as a support rep for a plugin.
	 *
	 * @static
	 *
	 * @param mixed           $post The plugin slug, WP_Post, or ID.
	 * @param string|\WP_User $user The user to remove.
	 * @return bool
	 */
	public static function remove_plugin_support_rep( $post, $user ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$result = wp_remove_object_terms( $post->ID, $user->user_nicename, 'plugin_support_reps' );

		wp_cache_delete( $post->post_name, 'plugin-support-reps' );
		wp_cache_delete( $user->user_nicename, 'support-rep-plugins' );

		Tools::audit_log(
			sprintf(
				'Removed <a href="%s">%s</a> as a support rep.',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				$user->user_login
			),
			$post
		);

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
	 * @param mixed       $post      The plugin slug, WP_Post, or ID.
	 * @param int|WP_User $user      The user to subscribe. Optional. Default current user.
	 * @param bool        $subscribe Whether to subscribe (true) or unsubscribe (false).
	 *                                 Optional. Default: true.
	 * @return bool Whether the user is subscribed.
	 */
	public static function subscribe_to_plugin_commits( $post, $user = 0, $subscribe = true ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$users = get_post_meta( $post->ID, '_commit_subscribed', true ) ?: [];

		if ( $subscribe ) {
			$users[] = $user->ID;
			$users   = array_unique( $users );
		} else {
			if ( false !== ( $pos = array_search( $user->ID, $users, true ) ) ) {
				unset( $users[ $pos ] );
			}
		}

		update_post_meta( $post->ID, '_commit_subscribed', $users );

		return Tools::subscribed_to_plugin_commits( $post, $user->ID );
	}

	/**
	 * Determine if a user is subscribed to a plugins commits.
	 *
	 * Plugin Committers are automatically subscribed to commits, and this
	 * function does not respect that status.
	 *
	 * @static
	 *
	 * @param mixed       $post The plugin slug, WP_Post, or ID.
	 * @param int|WP_User $user The user to check. Optional. Default current user.
	 * @return bool Whether the specified user is subscribed to commits.
	 */
	public static function subscribed_to_plugin_commits( $post, $user = 0 ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$users = get_post_meta( $post->ID, '_commit_subscribed', true ) ?: [];

		return in_array( $user->ID, $users, true );
	}

	/**
	 * Determine if a plugin has been favorited by a user.
	 *
	 * @param mixed $post The plugin to check.
	 * @param mixed $user The user to check.
	 * @return bool
	 */
	public static function favorited_plugin( $post, $user = 0 ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$users_favorites = get_user_meta( $user->ID, 'plugin_favorites', true ) ?: [];

		return in_array( $post->post_name, $users_favorites, true );
	}

	/**
	 * Favorite a plugin
	 *
	 * @param mixed  $post     The plugin to favorite
	 * @param mixed  $user     The user favoriting. Optional. Default current user.
	 * @param bool   $favorite Whether it's a favorite, or unfavorite. Optional. Default true
	 * @return bool
	 */
	public static function favorite_plugin( $post, $user = 0, $favorite = true ) {
		$post = Plugin_Directory::get_plugin_post( $post );
		$user = new WP_User( $user ?: get_current_user_id() );
		if ( ! $post || ! $user->exists() ) {
			return false;
		}

		$users_favorites   = get_user_meta( $user->ID, 'plugin_favorites', true ) ?: [];
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
	 * @param mixed  $post               The plugin to retrieve subscribers for.
	 * @param bool   $include_committers Whether to include Plugin Committers in the list. Default false.
	 * @return array Array of \WP_User's who are subscribed.
	 */
	public static function get_plugin_subscribers( $post, $include_committers = false ) {
		global $wpdb;
		$post = Plugin_Directory::get_plugin_post( $post );
		if ( ! $post ) {
			return [];
		}

		$users = [];

		// Plugin Committers are always subscrived to plugin commits.
		$committers = Tools::get_plugin_committers( $post );
		foreach ( $committers as $committer ) {
			if ( $committer && $user = get_user_by( 'login', $committer ) ) {
				$users[] = $user;
			}
		}

		// These users are subscribed the plugin commits.
		$subscribers = get_post_meta( $post->ID, '_commit_subscribed', true ) ?: [];
		foreach ( $subscribers as $subscriber_id ) {
			if ( $subscriber_id && $user = get_user_by( 'id', $subscriber_id ) ) {
				$users[] = $user;
			}
		}

		return $users;
	}

	/**
	 * Clear caches for memory management.
	 *
	 * @static
	 * @global \wpdb            $wpdb
	 * @global \WP_Object_Cache $wp_object_cache
	 */
	public static function clear_memory_heavy_variables() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = [];

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->cache          = [];
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->memcache_debug = [];
		}
	}

	/**
	 * Add an Audit Internal Note for a plugin.
	 *
	 * @param mixed  $post A Post ID, Plugin Slug or, WP_Post object.
	 * @param string $note The note to audit log entry to add.
	 * @param WP_User $user The user which performed the action. Optional.
	 */
	public static function audit_log( $note, $post = null, $user = false ) {
		$post = Plugin_Directory::get_plugin_post( $post );

		if ( ! $note || ! $post ) {
			return false;
		}
		if ( ! $user || ! ( $user instanceof \WP_User ) ) {
			$user = wp_get_current_user();
		}

		return wp_insert_comment( [
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_author_url'   => $user->user_url,
			'comment_author_IP'    => $_SERVER['REMOTE_ADDR'],
			'comment_type'         => 'internal-note',
			'comment_post_ID'      => $post->ID,
			'user_id'              => $user->ID,
			'comment_content'      => $note,
		] );
	}
}
