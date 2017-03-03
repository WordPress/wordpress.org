<?php
/**
 * Hooks for the support forums at https://wordpress.org/support only.
 */

namespace WordPressdotorg\Forums;

class Support_Compat {

	var $loaded     = false;
	var $query      = null;
	var $user       = null;

	/**
	 * Forums to be hidden from main forum listing.
	 *
	 * @var array
	 */
	const HIDDEN_FORUMS = array(
		21261, // Themes and Templates
		21262, // Plugins and Hacks
		21267, // Your WordPress
		21271, // Meetups
		21272, // Reviews
	);

	public function __construct() {
		if ( ! $this->loaded ) {
			// Intercept feed requests prior to bbp_request_feed_trap.
			add_filter( 'bbp_request', array( $this, 'request' ), 9 );

			// Add views for plugin committer/contributor.
			add_filter( 'query_vars',            array( $this, 'add_query_var' ) );
			add_action( 'bbp_add_rewrite_rules', array( $this, 'add_rewrite_rules' ) );

			// We have to add the custom view before bbPress runs its own action
			// on parse_query at priority 2.
			add_action( 'parse_query',           array( $this, 'parse_query' ), 0 );

			// Exclude compat forums from forum dropdown.
			add_filter( 'bbp_after_get_dropdown_parse_args', array( $this, 'get_dropdown' ) );

			// Topic resolution modifications.
			add_filter( 'wporg_bbp_topic_resolution_is_enabled_on_forum', array( $this, 'is_enabled_on_forum' ), 10, 2 );

			// Load plugin/theme subscriptions.
			add_action( 'plugins_loaded', array( $this, 'load_compat_subscriptions' ), 9 );

			// Adjust breadcrumbs for plugin and theme related topics.
			add_filter( 'bbp_breadcrumbs', array( $this, 'breadcrumbs' ) );

			// Redirect old topic ids to topic permalinks.
			add_action( 'template_redirect', array( $this, 'redirect_old_topic_id' ), 9 );

			// Exclude certain forums from forum queries.
			add_action( 'pre_get_posts', array( $this, 'exclude_hidden_forums' ), 1 );

			$this->loaded = true;
		}
	}

	/**
	 * Excludes certain forums from queries for forums.
	 *
	 * Notes:
	 * - The forums themselves are still meant to be directly accessible.
	 * - A hidden forum may not necessarily be closed.
	 *
	 * @param WP_Query $q Query object.
	 */
	public function exclude_hidden_forums( $q ) {
		if (
			! is_admin()
			&&
			! empty( $q->query_vars['post_type'] )
			&&
			bbp_get_forum_post_type() === $q->query_vars['post_type']
			&&
			empty( $q->query_vars['forum'] )
		) {
			$q->query_vars['post__not_in'] = array_merge( $q->query_vars['post__not_in'], self::HIDDEN_FORUMS );
		}
	}

	/**
	 * Check the request for the `wporg_user_login`, and then add filters to
	 * handle either the feed request or the custom view if a user is found.
	 *
	 * @param array $query_vars The query vars
	 * @return array The query vars
	 */
	public function request( $query_vars ) {
		if ( isset( $query_vars['wporg_user_login'] ) && ! empty( $query_vars['wporg_user_login'] ) && ! $this->user ) {
			$user = get_user_by( 'slug', $query_vars['wporg_user_login'] );
			if ( $user ) {
				// Set the user if available for custom views.
				$this->user = $user;

				// If this is a feed, add filters to handle the custom view.
				if ( isset( $query_vars['feed'] ) && isset( $query_vars['bbp_view'] ) && in_array( $query_vars['bbp_view'], array( 'plugin-committer', 'plugin-contributor' ) ) ) {
					$this->query = $query_vars;
					add_filter( 'bbp_get_view_query_args', array( $this, 'get_view_query_args_for_feed' ), 10, 2 );

					// Override bbPress topic pubDate handling to show topic time and not last active time.
					add_filter( 'get_post_metadata', array( $this, 'topic_pubdate_correction_for_feed' ), 10, 4 );
				}
			}
		}
		return $query_vars;
	}

	public function topic_pubdate_correction_for_feed( $value, $object_id, $meta_key, $single ) {
		// We only care about _bbp_last_active_time in this particular context
		if( $meta_key == '_bbp_last_active_time' ) {
			$value = get_post_time( 'Y-m-d H:i:s', true, $object_id );
		}
		return $value;
	}

	public function get_view_query_args_for_feed( $retval, $view ) {
		switch( $this->query['bbp_view'] ) {
			case 'plugin-committer' :
				return array(
					'post_parent__in' => array( Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID ),
					'post_status'     => 'publish',
					'tax_query'       => array( array(
						'taxonomy'    => 'topic-plugin',
						'field'       => 'slug',
						'terms'       => self::get_plugin_slugs_by_committer( $this->user->user_login ),
					) ),
					'show_stickies'   => false,
				);
				break;
			case 'plugin-contributor' :
				return array(
					'post_parent__in' => array( Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID ),
					'post_status'     => 'publish',
					'tax_query'       => array( array(
						'taxonomy'    => 'topic-plugin',
						'field'       => 'slug',
						'terms'       => $this->get_plugin_slugs_by_contributor( $this->user ),
					) ),
					'show_stickies'   => false,
				);
				break;
		}
		return $retval;
	}

	/**
	 * Determine if a custom view needs to be loaded for this query and register
	 * the view if needed.
	 *
	 * @param array $query_vars The query vars
	 */
	public function parse_query( $query_vars ) {
		$view = get_query_var( 'bbp_view' );
		if ( ! $view || ! $this->user ) {
			return;
		}

		if ( $view == 'plugin-committer' ) {

			$slugs = self::get_plugin_slugs_by_committer( $this->user->user_login );

			// Add plugin-committer view.
			bbp_register_view(
				'plugin-committer',
				sprintf( __( 'Plugin Committer &raquo; %s', 'wporg-forums' ), esc_html( $this->user->user_login ) ),
				array(
					'post_parent__in' => array( Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID ),
					'post_status'     => 'publish',
					'tax_query'       => array( array(
						'taxonomy'    => 'topic-plugin',
						'field'       => 'slug',
						'terms'       => $slugs,
					) ),
					'show_stickies'   => false,
				)
			);

			// Add output filters and actions.
			add_filter( 'bbp_get_view_link', array( $this, 'get_view_link' ), 10, 2 );

		} elseif ( $view == 'plugin-contributor' ) {

			$slugs = self::get_plugin_slugs_by_contributor( $this->user );

			// Add plugin-contributor view.
			bbp_register_view(
				'plugin-contributor',
				sprintf( __( 'Plugin Contributor &raquo; %s', 'wporg-forums' ), esc_html( $this->user->user_login ) ),
				array(
					'post_parent__in' => array( Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID ),
					'post_status'     => 'publish',
					'tax_query'       => array( array(
						'taxonomy'    => 'topic-plugin',
						'field'       => 'slug',
						'terms'       => $slugs,
					) ),
					'show_stickies'   => false,
				)
			);

			// Add output filters and actions.
			add_filter( 'bbp_get_view_link', array( $this, 'get_view_link' ), 10, 2 );
		}
	}

	public function add_query_var( $query_vars ) {
		$query_vars[] = 'wporg_user_login';
		return $query_vars;
	}

	public function add_rewrite_rules() {
		$priority   = 'top';

		$plugin_committer_rule   = bbp_get_view_slug() . '/plugin-committer/([^/]+)/';
		$plugin_contributor_rule = bbp_get_view_slug() . '/plugin-contributor/([^/]+)/';

		$feed_id    = 'feed';
		$view_id    = bbp_get_view_rewrite_id();
		$paged_id   = bbp_get_paged_rewrite_id();

		$feed_slug  = 'feed';
		$paged_slug = bbp_get_paged_slug();

		$base_rule  = '?$';
		$feed_rule  = $feed_slug . '/?$';
		$paged_rule = $paged_slug . '/?([0-9]{1,})/?$';

		// Add plugin committer rewrite rules.
		add_rewrite_rule( $plugin_committer_rule . $base_rule,  'index.php?' . $view_id . '=plugin-committer&wporg_user_login=$matches[1]',                               $priority );
		add_rewrite_rule( $plugin_committer_rule . $paged_rule, 'index.php?' . $view_id . '=plugin-committer&wporg_user_login=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $plugin_committer_rule . $feed_rule,  'index.php?' . $view_id . '=plugin-committer&wporg_user_login=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );

		// Add plugin contributor rewrite rules.
		add_rewrite_rule( $plugin_contributor_rule . $base_rule,  'index.php?' . $view_id . '=plugin-contributor&wporg_user_login=$matches[1]',                               $priority );
		add_rewrite_rule( $plugin_contributor_rule . $paged_rule, 'index.php?' . $view_id . '=plugin-contributor&wporg_user_login=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $plugin_contributor_rule . $feed_rule,  'index.php?' . $view_id . '=plugin-contributor&wporg_user_login=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );
	}

	/**
	 * Filter view links to provide prettier links for the custom view structure.
	 */
	public function get_view_link( $url, $view ) {
		global $wp_rewrite;

		$view = bbp_get_view_id( $view );
		if ( ! in_array( $view, array( 'plugin-committer', 'plugin-contributor' ) ) ) {
			return $url;
		}

		// Pretty permalinks.
		if ( $wp_rewrite->using_permalinks() ) {
			$url = $wp_rewrite->root . "view/{$view}/" . $this->user->user_nicename;
			$url = home_url( user_trailingslashit( $url ) );

		// Unpretty permalinks.
		} else {
			$url = add_query_arg( array(
				bbp_get_view_rewrite_id() => $view,
				'wporg_user_login'        => $this->user->user_nicename,
			) );
		}
		return $url;
	}

	/**
	 * Remove compat forums from forum dropdown on front-end display.
	 *
	 * @param array $r The function args
	 * @return array The filtered args
	 */
	public function get_dropdown( $r ) {
		if ( is_admin() || ! isset( $r['post_type'] ) || ! $r['post_type'] == bbp_get_forum_post_type() ) {
			return $r;
		}

		// Set up compat forum exclusion.
		if ( bbp_is_topic_edit() || bbp_is_single_view() ) {
			if ( is_array( $r['exclude'] ) ) {
				$r['exclude'] = array_unique( array_merge( $r['exclude'], self::get_compat_forums() ) );
			} elseif( empty( $r['exclude'] ) ) {
				$r['exclude'] = self::get_compat_forums();
			}

			if ( self::is_compat_forum( $r['selected'] ) ) {
				// Prevent forum changes for topics in compat forums.
				add_filter( 'bbp_get_dropdown', array( $this, 'dropdown' ), 10, 2 );
			}
		}
		return $r;
	}

	/**
	 * Disable forum changes on topics in the compat forums.
	 *
	 * @param string $retval The dropdown
	 * @param array $r The function arguments
	 * @return string The dropdown, or substituted hidden input
	 */
	public function dropdown( $retval, $r ) {
		if ( self::is_compat_forum( $r['selected'] ) ) {
			$retval = esc_html( bbp_get_forum_title( $r['selected'] ) );
			$retval .= sprintf( '<input type="hidden" name="bbp_forum_id" id="bbp_forum_id" value="%d" />', (int) $r['selected'] );
		}
		return $retval;
	}

	/**
	 * Disable topic resolutions on the reviews forum.
	 *
	 * @param bool $retval Is topic resolution enabled for this forum?
	 * @param int $forum_id Optional. The forum id
	 * @return bool True if enabled, otherwise false
	 */
	public function is_enabled_on_forum( $retval, $forum_id = 0 ) {
		// Check the passed forum id.
		if ( ! empty( $forum_id ) ) {
			$retval = ( $forum_id != Plugin::REVIEWS_FORUM_ID );
		}

		// Check the current forum.
		if ( bbp_is_single_forum() ) {
			$retval = ( bbp_get_forum_id() != Plugin::REVIEWS_FORUM_ID );
		}

		// Check the current topic forum.
		if ( bbp_is_single_topic() || bbp_is_topic_edit() ) {
		   	$retval = ( bbp_get_topic_forum_id() != Plugin::REVIEWS_FORUM_ID );
		}

		// Check the current view.
		if ( bbp_is_single_view() ) {
			$retval = ( bbp_get_view_id() != 'reviews' );
		}

		return $retval;
	}

	/**
	 * Enable theme and plugin subscriptions.
	 */
	public function load_compat_subscriptions() {
		if ( class_exists( 'WordPressdotorg\Forums\Term_Subscription\Plugin' ) ) {
			Plugin::get_instance()->plugin_subscriptions = new Term_Subscription\Plugin( array(
				'taxonomy' => 'topic-plugin',
				'labels'   => array(
					'subscribed_header'      => __( 'Subscribed Plugins', 'wporg-forums' ),
					'subscribed_user_notice' => __( 'You are not currently subscribed to any plugins.', 'wporg-forums' ),
					'subscribed_anon_notice' => __( 'This user is not currently subscribed to any plugins.', 'wporg-forums' ),
					'receipt'                => __( 'You are receiving this email because you are subscribed to a plugin.', 'wporg-forums' ),
				),
			) );
			Plugin::get_instance()->theme_subscriptions = new Term_Subscription\Plugin( array(
				'taxonomy' => 'topic-theme',
				'labels'   => array(
					'subscribed_header'      => __( 'Subscribed Themes', 'wporg-forums' ),
					'subscribed_user_notice' => __( 'You are not currently subscribed to any themes.', 'wporg-forums' ),
					'subscribed_anon_notice' => __( 'This user is not currently subscribed to any themes.', 'wporg-forums' ),
					'receipt'                => __( 'You are receiving this email because you are subscribed to a theme.', 'wporg-forums' ),
				),
			) );
		}
	}

	public static function get_compat_forums() {
		return array( Plugin::PLUGINS_FORUM_ID, Plugin::THEMES_FORUM_ID, Plugin::REVIEWS_FORUM_ID );
	}

	public static function is_compat_forum( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return false;
		}
		return in_array( $post_id, self::get_compat_forums() );
	}

	public static function get_plugin_slugs_by_committer( $user_login ) {
		global $wpdb;
		$slugs = (array) $wpdb->get_col( $wpdb->prepare( "SELECT `path` FROM `" . PLUGINS_TABLE_PREFIX . "svn_access` WHERE `user` = %s AND `access` = 'rw'", $user_login ) );
		return self::clean_slugs( $slugs );
	}

	public static function get_plugin_slugs_by_contributor( $user ) {
		global $wpdb;
		$slugs = (array) $wpdb->get_col( $wpdb->prepare( "SELECT `topic_slug` FROM `" . PLUGINS_TABLE_PREFIX . "topics` WHERE `topic_poster` = %d AND topic_open = 1 AND topic_status = 0", $user->ID ) );
		$slugs = self::clean_slugs( $slugs );
		if ( $contributor = $wpdb->get_col( $wpdb->prepare( "SELECT `object_id` FROM `" . PLUGINS_TABLE_PREFIX . "meta` WHERE `object_type` = 'bb_topic' AND `meta_key` = 'contributors' AND `meta_value` LIKE %s", '%"' . str_replace( array( '%', '_' ), array( '\\%', '\\_' ), $user->user_login ) . '"%' ) ) ) {
			if ( $contributor_slugs = $wpdb->get_col( "SELECT `topic_slug` FROM `" . PLUGINS_TABLE_PREFIX . "topics` WHERE `topic_id` IN (" . join( ',', $contributor ) . ") AND topic_open = 1 AND topic_status = 0" ) ) {
				$slugs = array_unique( array_merge( $slugs, $contributor_slugs ) );
			}
		}
		return $slugs;
	}

	public static function clean_slugs( $slugs ) {
		$cleanslugs = array();
		foreach ( $slugs as $slug ) {
			$slug = trim( $slug, '/' );
			if ( ! empty( $slug ) ) {
				$cleanslugs[] = $slug;
			}
		}
		return $cleanslugs;
	}

	/**
	 * Modifies breadcrumbs for plugin or theme related support or review topic
	 * views to insert a link to the specific plugin/theme's support or review
	 * view.
	 *
	 * @param array $r Breadcrumb items.
	 * @return array
	 */
	public function breadcrumbs( $r ) {
		if ( ! bbp_is_single_topic() ) {
			return $r;
		}

		$forum_id = bbp_get_topic_forum_id();
		$topic_id = bbp_get_topic_id();
		$slugs = $types = array();

		// Check if the topic is associated with a plugin.
		if ( $forum_id === Plugin::PLUGINS_FORUM_ID ) {
			$types = array( 'plugin' );
		}
		// Else check if the topic is associated with a theme.
		elseif ( $forum_id === Plugin::THEMES_FORUM_ID ) {
			$types = array( 'theme' );
		}
		// Else check if the topic is a review.
		elseif ( $forum_id === Plugin::REVIEWS_FORUM_ID ) {
			// Need to check for plugin AND theme association to know which the review is for.
			$types = array( 'plugin', 'theme' );
		}
		// Else not a type of concern.
		else {
			return $r;
		}

		foreach ( $types as $type ) {
			$slugs = wp_get_post_terms( $topic_id, 'topic-' . $type, array( 'fields' => 'slugs' ) );
			if ( $slugs ) {
				break;
			}
		}

		if ( ! $slugs ) {
			return $r;
		}

		$obj = Directory_Compat::get_object_by_slug_and_type( $slugs[0], $type );

		if ( ! $obj ) {
			return $r;
		}

		$url = home_url( '/' . $type . '/' . $obj->post_name . '/' );
		if ( $forum_id === Plugin::REVIEWS_FORUM_ID ) {
			$url .= 'reviews';
		}

		// Prefix link to plugin/theme support or review forum with context.
		if ( 'plugin' === $type ) {
			/* translators: %s: link to plugin support or review forum */
			$parent_breadcrumb = __( 'Plugin: %s', 'wporg-forums' );
		} else {
			/* translators: %s: link to theme support or review forum */
			$parent_breadcrumb = __( 'Theme: %s', 'wporg-forums' );
		}
		$link = sprintf( $parent_breadcrumb, sprintf(
			'<a href="%s" class="bbp-breadcrumb-forum">%s</a>',
			esc_url( $url ),
			esc_html( $obj->post_title )
		) );

		// Insert link before topic title.
		array_splice( $r, 1, 1, $link );

		return $r;
	}

	/**
	 * In bbPress 1, topics could be referenced using their topic id, and many
	 * are indexed/linked via this rather than their pretty permalink. The
	 * custom table topic2post makes it possible to quickly dereference these
	 * and redirect them appropriately.
	 */
	public function redirect_old_topic_id() {
		global $wpdb;
		if ( is_404() && 'topic' == get_query_var( 'post_type' ) && is_numeric( get_query_var( 'topic' ) ) ) {
			$topic_id = absint( get_query_var( 'topic' ) );
			if ( ! $topic_id ) {
				return;
			}

			$cache_key = $topic_id;
			$cache_group = 'topic2post';
			$post_id = wp_cache_get( $cache_key, $cache_group );
			if ( false === $post_id ) {
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}topic2post WHERE topic_id = %d LIMIT 1", $topic_id ) );
				if ( $post_id ) {
					$post_id = absint( $post_id );
					wp_cache_set( $cache_key, $post_id, $cache_group );
				}
			}

			if ( $post_id ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					wp_safe_redirect( $permalink, 301 );
					exit;
				}
			}
		}
	}
}
