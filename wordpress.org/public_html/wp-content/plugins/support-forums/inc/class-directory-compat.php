<?php

namespace WordPressdotorg\Forums;

abstract class Directory_Compat {

	abstract protected function compat();
	abstract protected function slug();
	abstract protected function title();
	abstract protected function forum_id();
	abstract protected function query_var();
	abstract protected function taxonomy();

	public function __construct() {}

	public function init() {
		add_action( 'bbp_init',              array( $this, 'register_taxonomy' ) );
		add_filter( 'query_vars',            array( $this, 'add_query_var' ) );
		add_action( 'bbp_add_rewrite_rules', array( $this, 'add_rewrite_rules' ) );

		add_filter( 'bbp_get_view_link',     array( $this, 'get_view_link' ), 10, 2 );
		add_filter( 'bbp_breadcrumbs',       array( $this, 'breadcrumbs' ) );
	}

	public function add_rewrite_rules() {
		$priority   = 'top';

		$root_id    = $this->compat();
		$root_var   = $this->query_var();
		$review_id  = 'reviews';

		$support_rule = $this->compat() . '/([^/]+)/';
		$reviews_rule = $this->compat() . '/([^/]+)/' . $review_id . '/';

		$feed_id    = 'feed';
		$view_id    = bbp_get_view_rewrite_id();
		$paged_id   = bbp_get_paged_rewrite_id();

		$feed_slug  = 'feed';
		$paged_slug = bbp_get_paged_slug();

		$base_rule  = '?$';
		$feed_rule  = $feed_slug  . '/?$';
		$paged_rule = $paged_slug . '/?([0-9]{1,})/?$';

		// Add reviews view rewrite rules.
		add_rewrite_rule( $reviews_rule . $base_rule,  'index.php?' . $view_id . '=' . $review_id . '&' . $root_var . '=$matches[1]',                               $priority );
		add_rewrite_rule( $reviews_rule . $paged_rule, 'index.php?' . $view_id . '=' . $review_id . '&' . $root_var . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $reviews_rule . $feed_rule,  'index.php?' . $view_id . '=' . $review_id . '&' . $root_var . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );

		// Add support view rewrite rules.
		add_rewrite_rule( $support_rule . $base_rule,  'index.php?' . $view_id . '=' . $root_id . '&' . $root_var . '=$matches[1]',                               $priority );
		add_rewrite_rule( $support_rule . $paged_rule, 'index.php?' . $view_id . '=' . $root_id . '&' . $root_var . '=$matches[1]&' . $paged_id . '=$matches[2]', $priority );
		add_rewrite_rule( $support_rule . $feed_rule,  'index.php?' . $view_id . '=' . $root_id . '&' . $root_var . '=$matches[1]&' . $feed_id  . '=$matches[2]', $priority );
	}

	public function add_query_var( $query_vars ) {
		$query_vars[] = $this->query_var();
		return $query_vars;
	}

	public function register_taxonomy() {
		if ( post_type_exists( 'topic' ) ) {
			register_taxonomy( $this->taxonomy(), 'topic', array( 'public' => false ) );
		}
	}

	/**
	 * Filter view links to provide prettier links for these subforum views.
	 */
	public function get_view_link( $url, $view ) {
		global $wp_rewrite;

		$view = bbp_get_view_id( $view );
		if ( $view != $this->compat() ) {
			return $url;
		}

		// Pretty permalinks.
		if ( $wp_rewrite->using_permalinks() ) {
			$url = $wp_rewrite->root . $this->compat() . '/' . $this->slug();
			$url = home_url( user_trailingslashit( $url ) );

		// Unpretty permalinks.
		} else {
			$url = add_query_arg( array(
				bbp_get_view_rewrite_id() => $view,
				$this->query_var()        => $this->slug(),
			) );
		}

		return $url;
	}

	/**
	 * Filter the breadcrumbs for directory views so we can specify the plugin
	 * or theme in the breadcrumbs.
	 */
	public function breadcrumbs( $r ) {
		if ( ! bbp_is_single_view() ) {
			return $r;
		}

		$view = bbp_get_view_id();
		if ( ! in_array( $view, array( $this->compat(), 'reviews' ) ) ) {
			return $r;
		}

		$r[1] = '<a href="' . esc_url( bbp_get_forum_permalink( $this->forum_id() ) ) . '" class="bbp-breadcrumb-forum">' . esc_html( bbp_get_forum_title( $this->forum_id() ) ) . '</a>';
		$r[2] = esc_html( $this->title() );
		if ( 'reviews' == $view ) {
			$r[2] = '<a href="' . esc_url( bbp_get_view_url( $this->compat() ) ) . '" class="bbp-breadcrumb-forum">' . esc_html( $this->title() ) . '</a>';
			$r[3] = __( 'Reviews', 'wporg-forums' );
		}
		return $r;
	}
}
