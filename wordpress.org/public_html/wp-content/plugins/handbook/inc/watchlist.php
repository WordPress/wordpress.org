<?php

class WPorg_Handbook_Watchlist {

	private static $post_types = array( 'handbook' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'on_init' ) );
	}

	public static function on_init() {
		self::$post_types = (array) apply_filters( 'handbook_post_types', self::$post_types );
		self::$post_types = array_map( array( __CLASS__, 'append_suffix' ), self::$post_types );

		add_action( 'p2_action_links', array(__CLASS__, 'display_action_link'), 100 );
		add_filter( 'o2_filter_post_actions', array( __CLASS__, 'add_o2_action_link' ) );
		add_filter( 'o2_filter_post_action_html', array( __CLASS__, 'get_o2_action_link' ), 10, 2 );
	}

	/**
	 * Appends '-handbook' to the dynamic post type, if not already 'handbook'.
	 *
	 * @param  string $t Hanbook post type name.
	 * @return string
	 */
	private static function append_suffix( $t ) {
		if ( in_array( $t, array( 'handbook', 'page' ) ) ) {
			return $t;
		}

		return $t . '-handbook';
	}

	/**
	 * Adds a 'Watch' action link to O2
	 */
	public static function add_o2_action_link( $actions ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$post = get_post();

		if ( 'page' == $post->post_type || ( in_array( $post->post_type, self::$post_types ) && ! is_post_type_archive( self::$post_types ) ) ) {
			$watchlist = get_post_meta( $post->ID, '_wporg_watchlist', true );

			if ( $watchlist && in_array( get_current_user_id(), $watchlist ) ) {
				$actions[35] = [
					'action'  => 'watch',
					'text'    => __( 'Unwatch', 'wporg' ),
					'href'    => wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&post_id=' . $post->ID ), 'unwatch-' . $post->ID ),
					'title'   => __( 'Stop getting notified about changes to this page', 'wporg' ),
					'classes' => [ 'genericon', 'genericon-unsubscribe' ],	
				];
			} else {
				$actions[35] = [
					'action'  => 'watch',
					'text'    => __( 'Watch', 'wporg' ),
					'href'    => wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&watch=1&post_id=' . $post->ID ), 'watch-' . $post->ID ),
					'title'   => __( 'Get notified about changes to this page', 'wporg' ),
					'classes' => [ 'genericon', 'genericon-subscribe' ],
				];
			}
		}
		return $actions;
	}

	/**
	 * Create the HTML for the watch o2 post action.
	 */
	public static function get_o2_action_link( $html, $action ) {
		if ( 'watch' === $action['action'] ) {
			$html = sprintf(
				'<a href="%s" title="%s" class="%s">%s</a>',
				$action['href'],
				$action['title'],
				implode( ' ', $action['classes'] ),
				$action['text']
			);
		}

		return $html;
	}

	/**
	 * Adds a 'Watch' action link to P2
	 */
	public static function display_action_link() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		$post = get_post();

		if ( 'page' == $post->post_type || ( in_array( $post->post_type, self::$post_types ) && ! is_post_type_archive( self::$post_types ) ) ) {

			$watchlist = get_post_meta( $post->ID, '_wporg_watchlist', true );

			echo ' | ';

			if ( $watchlist && in_array( get_current_user_id(), $watchlist ) ) {
				printf(
					__( '<a href="%s" title="%s">Unwatch</a>', 'wporg' ),
					wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&post_id=' . $post->ID ), 'unwatch-' . $post->ID ),
					esc_attr__( 'Stop getting notified about changes to this page', 'wporg' )
				);
			} else {
				printf(
					__( '<a href="%s" title="%s">Watch</a>', 'wporg' ),
					wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&watch=1&post_id=' . $post->ID ), 'watch-' . $post->ID ),
					esc_attr__( 'Get notified about changes to this page', 'wporg' )
				);
			}

		}

	}

}

WPorg_Handbook_Watchlist::init();

