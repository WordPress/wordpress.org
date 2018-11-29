<?php
/**
 * Class providing breadcrumb links.
 *
 * @package handbook
 */

class WPorg_Handbook_Breadcrumbs {

	/**
	 * Is the handbook table of contents produced by the Handbook Pages widget?
	 *
	 * @access private
	 * @var bool
	 */
	private static $using_pages_widget = false;

	/**
	 * Initializes handbook navigation.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ), 100 );
	}

	/**
	 * Fires on 'init' action.
	 *
	 * @access public
	 */
	public static function do_init() {
		// Note if the WPorg_Handbook_Pages_Widget widget is in use.
		if ( is_active_widget( false, false, WPorg_Handbook_Pages_Widget::get_widget_id_base(), true ) ) {
			self::$using_pages_widget = true;
		}

		// Output breadcrumb.
		add_action( 'handbook_breadcrumbs', [ __CLASS__, 'output_breadcrumbs' ] );
	}

	/**
	 * Outputs breadcrumb markup.
	 *
	 * @access public
	 */
	public static function output_breadcrumbs() {
		if ( ! wporg_is_handbook() || ! self::$using_pages_widget ) {
			return;
		}

		$links = [];

		// First link is always link to main site.
		$links[] = sprintf( '<a href="%s/">%s</a>', esc_url( site_url() ), __( 'Home', 'wporg' ) );

		// Second link is always link to handbook home page.
		if ( wporg_is_handbook_landing_page() ) {
			$links[] = __( 'Handbook', 'wporg' );
		} else {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( wporg_get_current_handbook_home_url() ), __( 'Handbook', 'wporg' ) );
		}

		// Add in links to current handbook page and all of its ancestor pages.
		$page = $current_page = get_post();
		$pages = [];
		
		do {
			$parent_id = wp_get_post_parent_id( $page );
			if ( $parent_id ) {
				$pages[] = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $parent_id ) ), get_the_title( $parent_id ) );
				$page = $parent_id;
			}
		} while ( $parent_id );

		$pages = array_reverse( $pages );
		foreach ( $pages as $page ) {
			$links[] = $page;
		}	

		// Last link is the current handbook page, unless it's the landing page.
		if ( ! wporg_is_handbook_landing_page() ) {
			$links[] = get_the_title( $current_page );
		}

		echo '<div class="handbook-breadcrumbs">';
		echo implode( ' / ', $links );
		echo "</div>\n";
	}

}

WPorg_Handbook_Breadcrumbs::init();

