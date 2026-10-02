<?php
/**
 * Tests for the props admin pages.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers which pages are registered and for whom.
 */
class WPorg_Trac_Watcher_Admin_Menu_Test extends WPorg_Trac_Watcher_TestCase {

	/**
	 * Registers the admin menu as an editor and returns the props editing pages.
	 *
	 * @return array Menu entries whose slug starts with `props-edit-`.
	 */
	protected function register_menu(): array {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Starting from an empty menu is the point.
		$GLOBALS['menu']              = array();
		$GLOBALS['submenu']           = array();
		$GLOBALS['admin_page_hooks']  = array();
		$GLOBALS['_registered_pages'] = array();
		$GLOBALS['_parent_pages']     = array();
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		do_action( 'admin_menu' );

		return array_values(
			array_filter(
				$GLOBALS['menu'],
				function ( $entry ) {
					return str_starts_with( $entry[2], 'props-edit-' );
				}
			)
		);
	}

	/**
	 * The editing page offers the actions the save handlers accept, so it requires what the handlers require.
	 */
	public function test_props_editing_pages_require_the_capability_the_handlers_check(): void {
		$entries = $this->register_menu();

		$this->assertNotEmpty( $entries, 'No props pages were registered.' );

		foreach ( $entries as $entry ) {
			$this->assertSame( 'edit_others_posts', $entry[1], $entry[2] . ' is registered for a different capability.' );
		}
	}
}
