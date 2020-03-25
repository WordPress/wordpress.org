<?php
/**
 * Class to output helpful admin notices.
 *
 * @package handbook
 */

class WPorg_Handbook_Admin_Notices {

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_new_handbook_message' ) );
	}

	/**
	 * Outputs admin notice showing tips for newly created handbook.
	 *
	 * @todo Maybe instead of hiding the message once posts are present it should persist as long as no landing page has been created?
	 *
	 * @access public
	 */
	public function show_new_handbook_message() {
		global $wp_query;

		$current_screen = get_current_screen();

		// Only show message in listing of handbook posts when no posts are present yet.
		if (
			'edit' === $current_screen->base
		&&
			in_array( $current_screen->post_type, wporg_get_handbook_post_types() )
		&&
			0 === $wp_query->post_count
		) {
			echo '<div class="notice notice-success"><p>';
			printf(
				/* translators: 1: example landing page title that includes post type name, 2: comma-separated list of acceptable post slugs */
				__( '<strong>Welcome to your new handbook!</strong> It is recommended that the first post you create is the landing page for the handbook. You can title it anything you like (suggestions: <code>%1$s</code> or <code>Welcome</code>). However, you must ensure that it has one of the following slugs: %2$s.', 'wporg' ),
				WPorg_Handbook::get_name( $current_screen->post_type ),
				'<code>' . str_replace( '-handbook', '', $current_screen->post_type ) . '</code>, <code>welcome</code>, <code>' . $current_screen->post_type . '</code>, <code>handbook</code>'
			);
			echo "</p></div>\n";
		}
	}

}

$admin_notices = new WPorg_Handbook_Admin_Notices();
