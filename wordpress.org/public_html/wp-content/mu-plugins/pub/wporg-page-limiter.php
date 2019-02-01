<?php
/**
 * Plugin Name: Limit Logged out users to 49 Pages. See https://meta.trac.wordpress.org/ticket/4068
 */

class WPORG_Page_Limiter {
	// Bots are blocked for Pages 50+
	const MAX_PAGES = 49;

	function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	public function init() {
		// Don't apply this to WordPress.org API endpoints.
		if ( defined( 'WPORG_IS_API' ) && WPORG_IS_API ) {
			return;
		}

		// Logged in users don't need limited pagination.
		if ( is_user_logged_in() ) {
			return;
		}

		// Priority of 100 is needed as a few places use filter callbacks to fill in the details.

		// Pre-query, ensure we flag this request as a 404.
		add_filter( 'request', [ $this, 'request' ], 100 );

		// 404 on high pages instead of performing a DB query.
		add_action( 'parse_query', [ $this, 'parse_query' ], 100 );

		// Limit WP_Query::max_num_pages to self::MAX_PAGES
		add_filter( 'found_posts', [ $this, 'found_posts' ], 100, 2 );

		// BbPress Forum Topic pagination
		add_filter( 'bbp_topic_pagination', [ $this, 'bbp_topic_pagination' ], 100 );
	}

	// Pre-query, ensure we flag this request as a 404 - Won't stop the actual WP_Query though, just sets the 404 headers.
	public function request( $args ) {
		if ( isset( $args['paged'] ) && $args['paged'] > self::MAX_PAGES ) {
			$args['error'] = 404;
		}
		return $args;
	}

	// Trigger a 404 for any paged requests exceeding the page limits.
	public function parse_query( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$paged = $query->get( 'paged' );

		if ( $paged && $paged > self::MAX_PAGES ) {
			$query->set_404();
		/*
			// TODO: WP_Query will still query the DB even if it's been told it's a 404 either via set_404() or via WP::parse_request().
			add_filter( 'posts_request', function( $request ) {
				// remove_filter( 'posts_request', __METHOD__ );
				return ''; // empty SQL
			}, 100 );
		*/
		}

	}

	// Make WordPress think there's only 50 pages
	public function found_posts( $found_posts, $query ) {
		if ( ! $query->is_main_query() ) {
			return $found_posts;
		}

		// Set a 20 posts_per_page fallback just in case it's not set on the query.. Shouldn't actually be needed.
		$posts_per_page = $query->query_vars['posts_per_page'] ?? 20;
		$max_posts      = self::MAX_PAGES * $posts_per_page;

		return min(
			$found_posts,
			$max_posts
		);
	}

	// bbPress filter the max forum pagination
	public function bbp_topic_pagination( $args ) {
		if ( $args['total'] > self::MAX_PAGES ) {
			$args['total'] = self::MAX_PAGES;
		}

		return $args;
	}
}
new WPORG_Page_Limiter();