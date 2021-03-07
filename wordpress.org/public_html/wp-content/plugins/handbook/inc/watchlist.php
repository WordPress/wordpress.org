<?php
/**
 * Class providing P2/O2 watchlist functionality.
 *
 * @package handbook
 */

class WPorg_Handbook_Watchlist {

	/**
	 * Memoized array of handbook post types.
	 *
	 * @var array
	 */
	private static $post_types;

	/**
	 * Initializes actions.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'on_init' ] );
	}

	/**
	 * Performs actions intended to occur during 'init' action.
	 */
	public static function on_init() {
		self::$post_types = WPorg_Handbook_Init::get_post_types();

		self::o2_register_default_post_action_states();

		add_action( 'p2_action_links',            [ __CLASS__, 'display_action_link' ], 100 );
		add_filter( 'o2_filter_post_actions',     [ __CLASS__, 'add_o2_action_link' ] );
		add_filter( 'o2_filter_post_action_html', [ __CLASS__, 'get_o2_action_link' ], 10, 2 );
	}

	/**
	 * Returns default post action info.
	 *
	 * @param string $type The post action type. Either 'watch' or 'unwatch'.
	 * @return array
	 */
	protected static function get_default_post_action_info( $type ) {
		$info = [];

		if ( ! in_array( $type, [ 'unwatch', 'watch' ] ) ) {
			return $info;
		}

		if ( 'watch' === $type ) {
			$info = [
				'shortText' => __( 'Watch', 'wporg' ),
				'title'     => __( 'Get notified about changes to this page', 'wporg' ),
				'genericon' => 'genericon-subscribe',
				'classes'   => [ 'genericon', 'genericon-subscribe' ],
				'rel'       => false,
			];
		} else {
			$info = [
				'shortText' => __( 'Unwatch', 'wporg' ),
				'title'     => __( 'Stop getting notified about changes to this page', 'wporg' ),
				'genericon' => 'genericon-unsubscribe',
				'classes'   => [ 'genericon', 'genericon-unsubscribe' ],
				'rel'       => false,
			];
		}

		return $info;
	}

	/**
	 * Registers default post action states.
	 */
	public static function o2_register_default_post_action_states() {
		if( ! function_exists( 'o2_register_post_action_states' ) ) {
			return;
		}

		o2_register_post_action_states( 'watch', [
			'unwatch' => self::get_default_post_action_info( 'unwatch' ),
			'watch'   => self::get_default_post_action_info( 'watch' ),
		] );
	}

	/**
	 * Adds a 'Watch' action link to O2.
	 *
	 * @param array $actions Array of O2 actions.
	 * @return array
	 */
	public static function add_o2_action_link( $actions ) {
		if ( ! is_user_logged_in() ) {
			return $actions;
		}

		$post = get_post();
		if ( ! $post ) {
			return $actions;
		}

		if ( in_array( $post->post_type, self::$post_types ) && ! is_post_type_archive( self::$post_types ) ) {
			$watchlist = get_post_meta( $post->ID, '_wporg_watchlist', true );

			if ( $watchlist && in_array( get_current_user_id(), $watchlist ) ) {
				$actions[35] = wp_parse_args( [
					'action'  => 'watch',
					'href'    => wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&post_id=' . $post->ID ), 'unwatch-' . $post->ID ),
					'initialState' => 'unwatch',
				], self::get_default_post_action_info( 'unwatch' ) );
			} else {
				$actions[35] = wp_parse_args( [
					'action'  => 'watch',
					'href'    => wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&watch=1&post_id=' . $post->ID ), 'watch-' . $post->ID ),
					'initialState' => 'watch',
				], self::get_default_post_action_info( 'watch' ) );
			}
		}

		return $actions;
	}

	/**
	 * Returns the HTML for the watch o2 post action.
	 *
	 * @param string $html   The HTML for the given action.
	 * @param array  $action Data about the action.
	 * @return string
	 */
	public static function get_o2_action_link( $html, $action ) {
		if ( 'watch' === $action['action'] ) {
			$html = sprintf(
				'<a href="%s" title="%s" class="%s">%s</a>',
				$action['href'],
				$action['title'],
				implode( ' ', $action['classes'] ),
				$action['shortText']
			);
		}

		return $html;
	}

	/**
	 * Outputs a 'Watch' action link to P2.
	 */
	public static function display_action_link() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_type, self::$post_types ) && ! is_post_type_archive( self::$post_types ) ) {
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
