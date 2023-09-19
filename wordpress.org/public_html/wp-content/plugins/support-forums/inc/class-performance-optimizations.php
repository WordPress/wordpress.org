<?php

namespace WordPressdotorg\Forums;

use WP_Error;

class Performance_Optimizations {

	var $term = null;
	var $query = null;
	var $bound_id = array();

	function __construct() {
		// Remove query to get adjacent posts.
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

		// Filters on pre_get_posts.
		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		// Don't use post_modified/post_modified_gmt to find the most recent content change.
		add_filter( 'pre_get_lastpostmodified', array( $this, 'pre_get_lastpostmodified' ), 10, 3 );

		// Query simplification.
		add_filter( 'bbp_after_has_topics_parse_args', array( $this, 'has_topics' ) );
		add_filter( 'bbp_after_has_replies_parse_args', array( $this, 'has_replies' ) );
		add_filter( 'bbp_register_view_no_replies', array( $this, 'exclude_compat_forums' ) );
		add_filter( 'bbp_register_view_all_topics', array( $this, 'exclude_compat_forums' ) );

		// Editor.
		add_action( 'wp_ajax_wp-link-ajax', array( $this, 'disable_wp_link_ajax' ), -1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Redirect search results.
		add_action( 'bbp_template_redirect', array( $this, 'redirect_search_results_to_google_search' ) );

		// Redirect (.+)/page/[01]/ and (.+)?paged=[01] to $1
		add_action( 'bbp_template_redirect', array( $this, 'redirect_page_zero_one' ) );

		// Redirect Attachments to their file
		add_action( 'bbp_template_redirect', array( $this, 'redirect_attachments' ) );

		// Disable post meta key lookup, see https://core.trac.wordpress.org/ticket/33885.
		add_filter( 'postmeta_form_keys', '__return_empty_array' );

		// Disable canonical redirects for short post_names
		add_filter( 'template_redirect', array( $this, 'maybe_disable_404_canonical' ), 9 );

		// Disable the 'popular' view.
		add_filter( 'bbp_register_views', array( $this, 'disable_popular_view' ) );

		// Disable entire-forum subscriptions.
		add_filter( 'bbp_get_forum_subscribers', '__return_empty_array' ); // bbPress 2.5; 2.6 deprecated
		add_filter( 'bbp_get_subscribers', array( $this, 'bbp_get_subscribers' ), 10, 3 ); // bbPress 2.6
		add_filter( 'bbp_get_user_subscribe_link', array( $this, 'bbp_get_user_subscribe_link' ), 10, 4 ); // Remove link.

		// Disable new tag creation for non-moderators.
		add_filter( 'bbp_new_topic_pre_insert',        array( $this, 'limit_topic_tag_creation' ) );
		add_filter( 'bbp_edit_topic_pre_insert',       array( $this, 'limit_topic_tag_creation' ) );
		add_filter( 'bbp_new_reply_pre_set_terms',     array( $this, 'limit_topic_reply_tag_creation' ) );
		add_filter( 'bbp_edit_reply_pre_set_terms',    array( $this, 'limit_topic_reply_tag_creation' ) );

		// Add some caching on count_users().
		add_filter( 'pre_count_users', array( $this, 'cache_count_users' ), 10, 3 );
		// ..and don't do expensive orderbys & counting for user queries that don't need it.
		add_action( 'pre_get_users', array( $this, 'pre_get_users' ) );

		// Disable feeds for non-existent views. See https://bbpress.trac.wordpress.org/ticket/3544
		add_action( 'bbp_request', array( $this, 'bbp_request_disable_missing_view_feeds' ), 9 ); // Before bbp_request_feed_trap().
	}

	/**
	 * Disables Canonical redirects on 404 pages when a short (<5char) name is provided.
	 *
	 * This is used to avoid really bad queries in redirect_guess_404_permalink() on urls such as:
	 * https://wordpress.org/support/topic/test/*1*
	 */
	public function maybe_disable_404_canonical() {
		if ( is_404() && get_query_var( 'name' ) && strlen( get_query_var( 'name' ) ) < 5 ) {
			remove_filter( 'template_redirect', 'redirect_canonical' );
		}
	}

	/**
	 * Redirects search results to Google Custom Search.
	 */
	public function redirect_search_results_to_google_search() {
		$is_wp_search  = is_search();
		$is_bbp_search = bbp_is_search_results();

		if ( ! $is_wp_search && ! $is_bbp_search ) {
			return;
		}

		if ( false === strpos( home_url(), 'https://wordpress.org/' ) ) {
			return;
		}

		$search_terms = $search_url = '';

		if ( $is_bbp_search ) {
			$search_terms = bbp_get_search_terms();
		} elseif ( $is_wp_search ) {
			$search_terms = get_search_query( false );
		}

		if ( isset( $_GET['intext'] ) ) {
			$search_terms .= ' intext:"' . esc_attr( $_GET['intext'] ) . '"';
		}

		if ( $search_terms ) {
			$tab = ! empty( $_GET['tab'] ) && 'docs' === $_GET['tab'] ? 'docs' : 'forums';
			$search_url = sprintf( "https://wordpress.org/search/%s/?in=support_{$tab}", urlencode( $search_terms ) );
			$search_url = esc_url_raw( $search_url );
		}

		if ( ! $search_url ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		wp_safe_redirect( $search_url );
		exit;
	}

	/**
	 * Redirects /page/[01]/, and /?paged=[01] requests to the archive root.
	 */
	public function redirect_page_zero_one() {
		global $wp_query;

		if (
			isset( $wp_query->query['paged'] ) &&
			in_array( $wp_query->query['paged'], [ 0, 1 ] ) &&
			'POST' !== $_SERVER['REQUEST_METHOD']
		) {
			// Generate the current URL.
			$current_url = $_SERVER['REQUEST_URI'];
			// Remove the path components.
			$current_url = preg_replace( '!^' . preg_quote( parse_url( home_url('/'), PHP_URL_PATH ), '!' ) . '!i', '', $current_url );
			$current_url = home_url( $current_url );

			// Remove any paged items
			$pageless_url = $current_url;
			$pageless_url = remove_query_arg( 'paged', $pageless_url );
			$pageless_url = preg_replace( '!/page/[01]/?!i', '/', $pageless_url );

			if ( $pageless_url !== $current_url ) {
				wp_safe_redirect( $pageless_url, 301 );
				exit;
			}
		}
	}

	public function redirect_attachments() {
		if ( is_attachment() ) {
			$url = wp_get_attachment_url( get_queried_object_id() );
			if ( ! $url ) {
				return;
			}

			if (
				function_exists( 'jetpack_photon_url' ) &&
				class_exists( '\Jetpack' ) &&
				method_exists( '\Jetpack', 'get_active_modules' ) &&
				in_array( 'photon', \Jetpack::get_active_modules() )
			) {
				$url = jetpack_photon_url( $url );
			}

			wp_redirect( $url, 301 );
			exit;
		}
	}

	public function pre_get_posts( $query ) {
		/**
		 * Feeds do not need to know the total count for a given query.
		 */
		if ( $query->is_feed() ) {
			$query->set( 'no_found_rows', true );
			$query->set( 'orderby', 'ID' );
		}
	}

	/**
	 * Forum traffic is high enough that we can avoid a query on post_modified_date
	 * and just look at the date on the post with the highest id. This filters
	 * on pre_get_lastpostmodified and caches the result of the simplified query.
	 *
	 * By using a different cache key, we can avoid constantly modifying this and
	 * allow it to time out after five minutes. Otherwise, certain feeds will be
	 * always have a changed status.
	 */
	public function pre_get_lastpostmodified( $retval, $timezone, $post_type ) {
		global $wpdb;

		// This is largely derived from _get_last_post_time().
		$timezone = strtolower( $timezone );

		$cache_key = "wporg:lastpostmodified:$timezone";
		if ( 'any' !== $post_type ) {
			$cache_key .= ':' . sanitize_key( $post_type );
		}
		$cache_group = 'wporg-forums-timeinfo';

		$date = wp_cache_get( $cache_key, $cache_group );

		if ( ! $date ) {
			if ( 'any' === $post_type ) {
				$post_types = get_post_types( array( 'public' => true ) );
				array_walk( $post_types, array( $wpdb, 'escape_by_ref' ) );
				$post_types = "'" . implode( "', '", $post_types ) . "'";
			} else {
				$post_types = "'" . sanitize_key( $post_type ) . "'";
			}

			switch ( $timezone ) {
				case 'gmt' :
					$date = $wpdb->get_var( "SELECT post_date_gmt FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY `ID` DESC LIMIT 1" );
					break;
				case 'blog' :
					$date = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY `ID` DESC LIMIT 1" );
					break;
				case 'server' :
					$add_seconds_server = date( 'Z' );
					$date = $wpdb->get_var( "SELECT DATE_ADD( post_date_gmt, INTERVAL '$add_seconds_server' SECOND ) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY `ID` DESC LIMIT 1" );
					break;
			}

			if ( $date ) {
				wp_cache_set( $cache_key, $date, $cache_group, 5 * MINUTE_IN_SECONDS );
			}
		}
		if ( empty( $date ) ) {
			return $retval;
		}

		return $date;
	}

	/**
	 * Optimize queries for has_topics as much as possible.
	 */
	public function has_topics( $r ) {
		/**
		 * Feeds
		 */
		if ( is_feed() ) {
			$r['no_found_rows'] = true;
			if ( ! is_singular( 'topic' ) ) {
				add_filter( 'posts_where', array( $this, 'posts_in_last_month' ) );
			}
		}

		/**
		 * Filter queries so they are not sorted by the post meta value of
		 * `_bbp_last_active_time`. This query needs additional optimization
		 * to run over large sets of posts.
		 * See also:
		 * - https://bbpress.trac.wordpress.org/ticket/1925
		 */
		if ( isset( $r['post_type'] ) && 'topic' == $r['post_type'] ) {

			// Theme and plugin views rely on taxonomy queries.
			if ( isset( $r['tax_query'] ) ) {

				// Only look at the last year of topics for the active view.
				if ( bbp_is_single_view() && bbp_get_view_id() == 'active' ) {
					add_filter( 'posts_where', array( $this, 'posts_in_last_year' ) );
				}
				return $r;
			}

			if ( isset( $r['meta_key'] ) && ! bbp_is_single_user_topics() ) {
				// has_topics() uses this by default.
				if ( '_bbp_last_active_time' == $r['meta_key'] ) {
					unset( $r['meta_key'] );
					unset( $r['meta_type'] );
					$r['orderby'] = 'ID';
				// Some views use meta key lookups and should only look at known
				// open topics.
				} elseif ( ! empty( $r['meta_key'] ) ) {
					$r['orderby'] = 'ID';
					add_filter( 'posts_where', array( $this, 'posts_in_last_six_months' ) );
				}
			}

			// If this is a forum, limit the number of pages we're dealing with.
			if ( bbp_is_single_forum() && isset( $r['post_parent'] ) && get_post_type( $r['post_parent'] ) === bbp_get_forum_post_type() ) {
				$r['no_found_rows'] = true;
				add_filter( 'bbp_topic_pagination', array( $this, 'forum_pagination' ) );
				$this->query = $r;
			}

			if ( bbp_is_single_view() && ! in_array( bbp_get_view_id(), array( 'plugin', 'theme', 'reviews', 'active', 'unresolved', 'taggedmodlook' ) ) ) {
				$r['post_parent__not_in'] = array( Plugin::THEMES_FORUM_ID, Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID );
			}
		}

		// Limit all-replies & all-topics views to posts in the last month.
		if ( bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'all-replies', 'all-topics' ) ) ) {
			add_filter( 'posts_where', array( $this, 'posts_in_last_month' ) );
		}

		return $r;
	}

	public function has_replies( $r ) {
		if ( is_feed() ) {
			$r['no_found_rows'] = true;
			if ( ! is_singular( 'topic' ) ) {
				add_filter( 'posts_where', array( $this, 'posts_in_last_month' ) );
			}
		}
		return $r;
	}

	public function exclude_compat_forums( $r ) {
		$r['post_parent__not_in'] = array( Plugin::THEMES_FORUM_ID, Plugin::PLUGINS_FORUM_ID, Plugin::REVIEWS_FORUM_ID );
		return $r;
	}

	/**
	 * Replace link AJAX with a short-circuited version.
	 * @todo Replace link with a custom modal to avoid AJAX call entirely.
	 */
	public function disable_wp_link_ajax() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			remove_action( 'wp_ajax_wp-link-ajax', 'wp_ajax_wp_link_ajax', 1 );
			add_action( 'wp_ajax_wp-link-ajax', '__return_zero', 1 );
		}
	}

	public function enqueue_styles() {
		if ( current_user_can( 'participate' ) ) {
			wp_enqueue_style( 'support-forums-participants', plugins_url( 'css/styles-participants.css', __DIR__ ), array(), '20230919' );
		}
	}

	public function posts_in_last_month( $w ) {
		global $wpdb;

		$bound_id = $this->get_bound_id( '1 MONTH' );
		$w .= $wpdb->prepare( " AND ( $wpdb->posts.ID >= %d )", $bound_id );
		return $w;
	}

	public function posts_in_last_six_months( $w ) {
		global $wpdb;

		$bound_id = $this->get_bound_id( '6 MONTH' );
		$w .= $wpdb->prepare( " AND ( $wpdb->posts.ID >= %d )", $bound_id );
		return $w;
	}

	public function posts_in_last_year( $w ) {
		global $wpdb;

		$bound_id = $this->get_bound_id( '1 YEAR' );
		$w .= $wpdb->prepare( " AND ( $wpdb->posts.ID >= %d )", $bound_id );
		return $w;
	}

	public function forum_pagination( $r ) {
		global $wpdb;

		// Try the stored topic count.
		$count = get_post_meta( $this->query['post_parent'], '_bbp_topic_count', true );
		if ( ! empty( $count ) ) {
			$r['total'] = $count / bbp_get_topics_per_page();
			return $r;
		}

		// Try SQL.
		if ( ! is_null( $this->query ) ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'topic' AND post_status IN ( 'publish', 'closed' ) LIMIT 1", $this->query['post_parent'] ) );
			if ( $count ) {
				$r['total'] = $count / bbp_get_topics_per_page();
				update_post_meta( $this->query['post_parent'], '_bbp_topic_count', $count );
				update_post_meta( $this->query['post_parent'], '_bbp_total_topic_count', $count );
				return $r;
			}
		}

		// Give a reasonable default to fall back on.
		$r['total'] = 10;
		return $r;
	}

	/**
	 * Get the term for a plugin or theme view from query_var.
	 */
	public function get_term() {
		if ( null !== $this->term ) {
			return $this->term;
		}

		$slug = false;
		if ( ! empty( get_query_var( Plugin::get_instance()->plugins->query_var() ) ) ) {
			$slug = Plugin::get_instance()->plugins->slug();
			$tax  = Plugin::get_instance()->plugins->taxonomy();
		} elseif ( ! empty( get_query_var( Plugin::get_instance()->themes->query_var() ) ) ) {
			$slug = Plugin::get_instance()->themes->slug();
			$tax  = Plugin::get_instance()->themes->taxonomy();
		}
		if ( $slug ) {
			$term = get_term_by( 'slug', $slug, $tax );
		} else {
			return false;
		}
		return $term;
	}

	/**
	 * Get the ID from a topic one year ago so that we can only look at topics
	 * after that ID.
	 */
	public function get_bound_id( $interval ) {
		global $wpdb;

		if ( ! in_array( $interval, array( '1 WEEK', '1 MONTH', '6 MONTH', '1 YEAR' ) ) ) {
			$interval = '1 WEEK';
		}

		if ( array_key_exists( $interval, $this->bound_id ) ) {
			return $this->bound_id[ $interval ];
		}

		// Check cache.
		$cache_key = str_replace( ' ', '-', $interval );
		$cache_group = 'topic-bound-ids';
		$bound_id = wp_cache_get( $cache_key, $cache_group );
		if ( false === $bound_id ) {

			// Use the type_status_date index, excluding reviews because they were imported last.
			$bound_id = $wpdb->get_var( "
				SELECT `ID`
				FROM $wpdb->posts
				WHERE post_type = 'topic'
					AND post_status IN ( 'publish', 'closed' )
					AND post_parent != " . Plugin::REVIEWS_FORUM_ID . "
					AND post_date < DATE_SUB( NOW(), INTERVAL $interval )
				ORDER BY `ID` DESC
				LIMIT 1 " );
			// Set the bound id to 1 if there is not a suitable range.
			if ( ! $bound_id ) {
				$bound_id = 1;
			}
			$this->bound_id[ $interval ] = $bound_id;

			wp_cache_set( $cache_key, $bound_id, $cache_group, DAY_IN_SECONDS );
		}
		return $bound_id;
	}

	/**
	 * Deregister the 'popular' view as it doesn't make sense on the Global Forums from a performance perspective.
	 */
	public function disable_popular_view() {
		bbp_deregister_view( 'popular' );
	}

	/**
	 * Block forum subscriptions.
	 *
	 * No user ever actually intends to subscribe to an entire forum, so lets just never do that.
	 */
	function bbp_get_subscribers( $user_ids, $object_id, $type ) {
		// We don't want anyone subscribing to a forum, null it out if that happens.
		if ( 'post' === $type && bbp_is_forum( $object_id ) ) {
			$user_ids = array();
		}

		return $user_ids;
	}

	/**
	 * Disable the 'Subscribe' link for forums.
	 */
	function bbp_get_user_subscribe_link( $html, $r, $user_id, $object_id ) {
		// We don't want end-users accidentally subscribing to a forum, lets remove those links.
		if ( bbp_is_forum( $object_id ) ) {
			$html = '';
		}

		return $html;
	}

	/**
	 * Limit the creation of new tags to moderators or above.
	 *
	 * This curates the list of tags, keeping the tag list short and relevant.
	 *
	 * @param array $topic Array of post details.
	 *
	 * @return array Filtered post details array.
	 */
	public function limit_topic_tag_creation( $topic ) {
		// Only affect the topic post type.
		if ( bbp_get_topic_post_type() !== $topic['post_type'] ) {
			return $topic;
		}

		// Do not modify anything if the user has moderator capabilities.
		if ( current_user_can( 'moderate' ) ) {
			return $topic;
		}

		$existing_tags = array();

		$taxonomy = bbp_get_topic_tag_tax_slug();

		if ( ! empty( $topic['tax_input'][ $taxonomy ] ) ) {
			if ( ! is_array( $topic['tax_input'][ $taxonomy ] ) ) {
				$topic['tax_input'][ $taxonomy ] = preg_split( '/,\s*/', trim( $topic['tax_input'][ $taxonomy ], " \n\t\r\0\x0B," ) );
			}

			// Loop through the proposed terms
			foreach ( $topic['tax_input'][ $taxonomy ] as $i => $term ) {
				if ( ! term_exists( $term, $taxonomy ) ) {
					// ..and remove anything that doesn't exist.
					unset( $topic['tax_input'][ $taxonomy ][ $i ] );
				}
			}
		}

		return $topic;
	}

	/**
	 * Limit the creation of new tags to moderators or above.
	 *
	 * This curates the list of tags, keeping the tag list short and relevant.
	 *
	 * @param array|string $terms Array of terms to apply to a topic.
	 *
	 * @return array|string Filtered array of terms to apply to a topic.
	 */
	public function limit_topic_reply_tag_creation( $terms ) {
		// Do not modify anything if the user has moderator capabilities.
		if ( current_user_can( 'moderate' ) ) {
			return $terms;
		}

		if ( ! is_array( $terms ) ) {
			$terms = preg_split( '/,\s*/', trim( $terms, " \n\t\r\0\x0B," ) );
		}

		$existing_terms = array();

		// Loop through the proposed tags and keep only the existing ones.
		foreach ( $terms as $term ) {
			if ( term_exists( $term, bbp_get_topic_tag_tax_id() ) ) {
				$existing_terms[] = $term;
			}
		}

		// Return a string if the input value was one.
		if ( ! is_array( $terms ) ) {
			return implode( ', ', $terms );
		}

		return $existing_terms;
	}

	/**
	 * Cache the result of `count_users()` as the Support Forums site has a lot of users.
	 *
	 * This slows wp-admin/users.php down so much that it's hard to use when required.
	 * As these numbers don't change often, it's cached for 24hrs hours, which avoids a 20-60s query on each users.php pageload.
	 */
	public function cache_count_users( $result, $strategy, $site_id ) {
		global $wpdb;
		static $running = false;

		if ( $result || ! is_multisite() || $running ) {
			return $result;
		}

		switch_to_blog( $site_id );

		$result = get_transient( 'count_users' );
		if ( ! $result ) {
			if ( is_callable( [ $wpdb, 'send_reads_to_masters' ] ) ) {
				$wpdb->send_reads_to_masters(); // unfortunate.
			}

			// always time strategy, memory loads every single meta_value, that ain't gonna work.
			$strategy = 'time';

			$running = true;
			$result  = count_users( $strategy, $site_id );
			$running = false;

			set_transient( 'count_users', $result, DAY_IN_SECONDS );
		}

		restore_current_blog();

		return $result;
	}

	/**
	 * Filter use queries to be more performant, as the default WordPress user queries
	 * just don't scale to several million users here.
	 *
	 * @param \WP_User_Query $query
	 */
	public function pre_get_users( $query ) {
		$is_role_related = false;
		foreach ( [ 'role', 'role__in', 'role__not_in', 'capability', 'capability__in', 'capability__not_in' ] as $var ) {
			if ( ! empty( $query->query_vars[ $var ] ) ) {
				$is_role_related = true;
				break;
			}
		}

		// Don't count users unless searching.
		if ( empty( $query->query_vars['search'] ) && ! $is_role_related ) {
			$query->query_vars['count_total'] = false;
		}

		// Sort by ID instead of login by default, speeds up initial load.
		if ( 'login' === $query->query_vars['orderby'] && empty( $_GET['orderby'] ) ) {
			$query->query_vars['orderby'] = 'ID';
			$query->query_vars['order'] = 'DESC';
		}

		// Searches for email domains should be wildcard before only.
		if ( str_starts_with( $query->query_vars['search'], '*@' ) && str_ends_with( $query->query_vars['search'], '*' ) ) {
			$is_role_related = false;

			$query->query_vars['search'] = '*' . trim( $query->query_vars['search'], '*' );
		}

		// If a search is being performed, and it's not capability/role dependant, ignore the blog_id.
		if ( $query->query_vars['blog_id'] && ! $is_role_related ) {
			$query->query_vars['blog_id'] = false;
		}
	}

	/**
	 * Disable feeds for missing bbPress views.
	 *
	 * @see https://bbpress.trac.wordpress.org/ticket/3544
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function bbp_request_disable_missing_view_feeds( $query_vars ) {
		$view_id = bbp_get_view_rewrite_id();

		if (
			isset( $query_vars['feed'] ) &&
			isset( $query_vars[ $view_id ] ) &&
			! bbp_get_view_query_args( $query_vars[ $view_id ] )
		) {
			unset( $query_vars[ $view_id ] );

			// Set a 404 status, without this bbPress is unsure of what to do.
			$query_vars['error'] = 404;
		}

		return $query_vars;
	}
}
