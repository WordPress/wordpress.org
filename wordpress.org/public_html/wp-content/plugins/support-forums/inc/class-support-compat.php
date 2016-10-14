<?php
/**
 * Hooks for the support forums at https://wordpress.org/support only.
 */

namespace WordPressdotorg\Forums;

class Support_Compat {

	var $loaded     = false;
	var $query      = null;
	var $user_login = null;

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

			$this->loaded = true;
		}
	}

	public function request( $query_vars ) {
		if ( isset( $query_vars['feed'] ) && isset( $query_vars['wporg_user_login'] ) ) {
			if ( isset( $query_vars['bbp_view'] ) && in_array( $query_vars['bbp_view'], array( 'plugin-committer' ) ) ) {
				$this->query = $query_vars;
				add_filter( 'bbp_get_view_query_args', array( $this, 'get_view_query_args_for_feed' ), 10, 2 );

				// Override bbPress topic pubDate handling to show topic time and not last active time
				add_filter( 'get_post_metadata', array( $this, 'topic_pubdate_correction_for_feed' ), 10, 4 );
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
						'terms'       => $this->get_plugin_slugs_by_committer( $this->query['wporg_user_login'] ),
					) ),
					'show_stickies'   => false,
				);
				break;
		}
		return $retval;
	}

	public function parse_query() {
		$user_login = get_query_var( 'wporg_user_login' );
		$view = get_query_var( 'bbp_view' );
		if ( ! $user_login || ! $view ) {
			return;
		}

		// Basic setup.
		$this->user_login = $user_login;

		if ( $view == 'plugin-committer' ) {

			$slugs = $this->get_plugin_slugs_by_committer( $user_login );

			// Add plugin-committer view.
			bbp_register_view(
				'plugin-committer',
				sprintf( __( 'Plugin Committer &raquo; %s', 'wporg-forums' ), esc_html( $user_login ) ),
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

		$plugin_committer_rule = bbp_get_view_slug() . '/plugin-committer/([^/]+)/';

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
	}

	/**
	 * Filter view links to provide prettier links for the custom view structure.
	 */
	public function get_view_link( $url, $view ) {
		global $wp_rewrite;

		$view = bbp_get_view_id( $view );
		if ( ! in_array( $view, array( 'plugin-committer' ) ) ) {
			return $url;
		}

		// Pretty permalinks.
		if ( $wp_rewrite->using_permalinks() ) {
			$url = $wp_rewrite->root . 'view/plugin-committer/' . $this->user_login;
			$url = home_url( user_trailingslashit( $url ) );

		// Unpretty permalinks.
		} else {
			$url = add_query_arg( array(
				bbp_get_view_rewrite_id() => $view,
				'wporg_user_login'        => $this->user_login,
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
					'receipt' => __( 'You are receiving this email because you are subscribed to a plugin.', 'wporg-forums' ),
				),
			) );
			Plugin::get_instance()->theme_subscriptions = new Term_Subscription\Plugin( array(
				'taxonomy' => 'topic-theme',
				'labels'   => array(
					'receipt' => __( 'You are receiving this email because you are subscribed to a theme.', 'wporg-forums' ),
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
		return $slugs;
	}
}
