<?php

defined( 'ABSPATH' ) or die();

class WPorg_Handbook_Admin_Notices_Test extends WP_UnitTestCase {

	//
	//
	// TESTS
	//
	//


	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WPorg_Handbook_Admin_Notices' ) );
	}

	public function test_hooks_after_setup_theme_to_initialize() {
		$this->assertEquals( 10, has_action( 'plugins_loaded', [ 'WPorg_Handbook_Admin_Notices', 'init' ] ) );
	}

	public function test_registers_default_hooks() {
		$this->assertEquals( 10, has_action( 'admin_notices', [ 'WPorg_Handbook_Admin_Notices' , 'show_new_handbook_message' ] ) );
	}

	/*
	 * show_new_handbook_message()
	 */

	public function test_show_new_handbook_message_shows_nothing_by_default() {
		$this->expectOutputRegex( '~^$~', WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_shows_nothing_on_random_admin_page() {
		set_current_screen( 'tools' );
		$screen = get_current_screen();
		$screen->post_type = 'handbook';

		$this->expectOutputRegex( '~^$~', WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_shows_nothing_on_handbook_listing_for_unhandled_post_status() {
		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'handbook';
		$GLOBALS['wp_query']->query_vars['post_status'] = 'draft';

		$this->expectOutputRegex( '~^$~', WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_shows_nothing_on_handbook_listing_with_posts() {
		$this->factory->post->create( [ 'post_type' => 'handbook', 'post_status' => 'publish' ] );
		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'handbook';
		$GLOBALS['wp_query']->query( [ 'post_type' => 'handbook' ] );

		$this->expectOutputRegex( '~^$~', WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_shows_nothing_on_non_handbook_listing() {
		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'post';

		$this->expectOutputRegex( '~^$~', WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_shows_for_handbook_listing_viewing_all() {
		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'handbook';

		$expected = '<div class="notice notice-success"><p><strong>Welcome to your new handbook!</strong>.+</p></div>' . "\n";

		$this->expectOutputRegex( "~^{$expected}$~", WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_shows_for_handbook_listing_viewing_publish() {
		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'handbook';
		$GLOBALS['wp_query']->query( [ 'post_type' => 'handbook', 'post_status' => 'publish' ] );

		$expected = '<div class="notice notice-success"><p><strong>Welcome to your new handbook!</strong>.+</p></div>' . "\n";

		$this->expectOutputRegex( "~^{$expected}$~", WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_takes_into_account_handbook_post_type_in_message() {
		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'handbook';

		$expected = 'one of the following slugs: <code>handbook</code>, <code>welcome</code>\.';

		$this->expectOutputRegex( "~{$expected}~", WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

	public function test_show_new_handbook_message_takes_into_account_custom_handbook_post_type_in_message() {
		reinit_handbooks( ['plugin-handbook', 'theme-handbook'], 'post_types' );

		set_current_screen( 'edit' );
		$screen = get_current_screen();
		$screen->post_type = 'plugin-handbook';

		$expected = 'one of the following slugs: <code>plugin</code>, <code>welcome</code>, <code>plugin-handbook</code>, <code>handbook</code>\.';

		$this->expectOutputRegex( "~{$expected}~", WPorg_Handbook_Admin_Notices::show_new_handbook_message() );
	}

}
