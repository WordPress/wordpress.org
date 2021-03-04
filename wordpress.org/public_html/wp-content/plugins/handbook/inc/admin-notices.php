<?php
/**
 * Class to output helpful admin notices.
 *
 * @package handbook
 */

class WPorg_Handbook_Admin_Notices {

	/**
	 * Initializes functionality.
	 *
	 * @access public
	 */
	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'show_new_handbook_message' ] );
	}

	/**
	 * Outputs admin notice showing tips for newly created handbook.
	 *
	 * @todo Maybe instead of hiding the message once posts are present it should persist as long as no landing page has been created?
	 *
	 * @access public
	 */
	public static function show_new_handbook_message() {
		global $wp_query;

		$current_screen = get_current_screen();

		// Only show message in listing of handbook posts when no posts are present yet.
		if (
			$current_screen
		&&
			'edit' === $current_screen->base
		&&
			in_array( $current_screen->post_type, wporg_get_handbook_post_types() )
		&&
			0 === $wp_query->post_count
		&&
			( empty( $wp_query->query_vars['post_status'] ) || 'publish' === $wp_query->query_vars['post_status'] )
		) {
			echo '<div class="notice notice-success"><p>';

			$suggested_slugs = array_unique( [
				str_replace( '-handbook', '', $current_screen->post_type ),
				'welcome',
				$current_screen->post_type,
				'handbook',
			] );
			$suggested_slugs = array_map( function( $x ) { return "<code>{$x}</code>"; }, $suggested_slugs );

			printf(
				/* translators: 1: example landing page title that includes post type name, 2: comma-separated list of acceptable post slugs */
				__( '<strong>Welcome to your new handbook!</strong> It is recommended that the first post you create is the landing page for the handbook. You can title it anything you like (suggestions: <code>%1$s</code> or <code>Welcome</code>). However, you must ensure that it has one of the following slugs: %2$s.', 'wporg' ),
				WPorg_Handbook::get_name( $current_screen->post_type ),
				implode( ', ', $suggested_slugs )
			);
			echo "</p></div>\n";
		}
	}

}

add_action( 'plugins_loaded', [ 'WPorg_Handbook_Admin_Notices', 'init' ] );
