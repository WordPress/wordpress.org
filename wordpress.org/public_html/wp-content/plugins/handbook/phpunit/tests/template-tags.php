<?php

defined( 'ABSPATH' ) or die();

class WPorg_Handbook_Template_Tags_Test extends WP_UnitTestCase {

	public function setUp() {
		parent::setup();

		WPorg_Handbook_Init::init();
	}

	public function tearDown() {
		parent::tearDown();

		WPorg_Handbook_Init::reset( true );
	}


	//
	//
	// TESTS
	//
	//


	/*
	 * wporg_get_handbook_post_types()
	 */

	public function test_wporg_get_handbook_post_types_default() {
		$this->assertEquals( [ 'handbook' ], wporg_get_handbook_post_types() );
	}

	public function test_wporg_get_handbook_post_types_custom_post_types() {
		reinit_handbooks( [ 'plugin', 'theme' ], 'post_types' );

		$this->assertEquals( [ 'plugin-handbook', 'theme-handbook' ], wporg_get_handbook_post_types() );
	}

	public function test_wporg_get_handbook_post_types_custom_post_types_with_dash_handbook() {
		reinit_handbooks( [ 'plugin-handbook', 'theme-handbook' ], 'post_types' );

		$this->assertEquals( [ 'plugin-handbook', 'theme-handbook' ], wporg_get_handbook_post_types() );
	}

	/*
	 * wporg_is_handbook()
	 */

	public function test_wporg_is_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertTrue( wporg_is_handbook() );
	}

	public function test_wporg_is_handbook_for_non_handbook() {
		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$post_id2 = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id2 ) );

		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertFalse( wporg_is_handbook() );
	}

	/*
	 * wporg_is_handbook_landing_page()
	 */

	public function test_wporg_is_handbook_landing_page_via_post() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertTrue( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_via_post_type() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );
		$this->go_to( get_post_type_archive_link( 'handbook' ) );

		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertTrue( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_for_slug_welcome_via_post() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'welcome' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_for_slug_welcome_via_post_type() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'welcome' ] );
		$this->go_to( get_post_type_archive_link( 'handbook' ) );

		$this->assertTrue( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_on_handbook_page_that_is_not_landing_page() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'example' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_on_non_handbook_page() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_when_no_landing_page_defined() {
		$this->go_to( get_post_type_archive_link( 'handbook' ) );

		$this->assertFalse( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_for_non_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'post', 'post_name' => 'handbook' ] );
		$this->go_to( get_post_type_archive_link( 'post' ) );

		$this->assertFalse( wporg_is_handbook_landing_page() );
	}

	public function test_wporg_is_handbook_landing_page_for_custom_non_handbook_via_post_type() {
		register_post_type( 'example' );
		$post_id = $this->factory()->post->create( [ 'post_type' => 'example', 'post_name' => 'handbook' ] );
		$this->go_to( get_post_type_archive_link( 'example' ) );

		$this->assertFalse( wporg_is_handbook_landing_page() );
	}

	/*
	 * wporg_is_handbook_post_type()
	 */

	public function test_wporg_is_handbook_post_type_default() {
		$this->assertTrue( wporg_is_handbook_post_type( 'handbook' ) );
	}

	public function test_wporg_is_handbook_post_type_with_custom_post_types() {
		reinit_handbooks( [ 'plugin', 'theme' ], 'post_types' );

		$this->assertFalse( wporg_is_handbook_post_type( 'handbook' ) );
		$this->assertTrue( wporg_is_handbook_post_type( 'plugin-handbook' ) );
		$this->assertTrue( wporg_is_handbook_post_type( 'theme-handbook' ) );
	}

	/*
	 * wporg_get_current_handbook()
	 */

	public function test_wporg_get_current_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( 'handbook', wporg_get_current_handbook() );
	}

	public function test_wporg_get_current_handbook_for_non_handbook() {
		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$post_id2 = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id2 ) );

		$this->assertFalse( wporg_get_current_handbook() );
	}

	public function test_wporg_get_current_handbook_for_multi_handbook() {
		reinit_handbooks( [ 'plugin', 'theme' ], 'post_types' );

		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'plugin-handbook' ] );
		$post_id2 = $this->factory()->post->create( [ 'post_type' => 'theme-handbook' ] );

		$this->go_to( get_permalink( $post_id1 ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertEquals( 'plugin-handbook', wporg_get_current_handbook() );


		$this->go_to( get_permalink( $post_id2 ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->assertEquals( 'theme-handbook', wporg_get_current_handbook() );
	}

	/*
	 * wporg_get_current_handbook_home_url ()
	 */

	public function test_wporg_get_current_handbook_home_url() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( 'http://example.org/?post_type=handbook', wporg_get_current_handbook_home_url() );

	}

	public function test_wporg_get_current_handbook_home_url_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEmpty( wporg_get_current_handbook_home_url() );

	}

	public function test_wporg_get_current_handbook_home_url_for_multi_handbook() {
		reinit_handbooks( [ 'plugin', 'theme' ], 'post_types' );

		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'plugin-handbook', 'post_name' => 'something' ] );
		$post_id2 = $this->factory()->post->create( [ 'post_type' => 'theme-handbook', 'post_name' => 'example' ] );

		$this->go_to( get_permalink( $post_id1 ) );
		$this->assertEquals( 'http://example.org/?post_type=plugin-handbook', wporg_get_current_handbook_home_url() );

		$this->go_to( get_permalink( $post_id2 ) );
		$this->assertEquals( 'http://example.org/?post_type=theme-handbook', wporg_get_current_handbook_home_url() );
	}

	public function test_wporg_get_current_handbook_home_url_with_landing_page() {
		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );
		$post_id2 = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'something' ] );
		$this->go_to( get_permalink( $post_id2 ) );

		$this->assertEquals( get_permalink( $post_id1 ), wporg_get_current_handbook_home_url() );

	}

	/*
	 * wporg_get_current_handbook_name()
	 */

	public function test_wporg_get_current_handbook_name() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( 'Handbook', wporg_get_current_handbook_name() );
	}

	public function test_wporg_get_current_handbook_name_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEmpty( wporg_get_current_handbook_name() );
	}

	public function test_wporg_get_current_handbook_name_with_name_set() {
		$name = 'Custom Name';
		update_option( 'handbook_name', $name );
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( $name, wporg_get_current_handbook_name() );
	}

	public function test_wporg_get_current_handbook_name_multi_handbook_with_names_set() {
		reinit_handbooks( [ 'custom', 'example' ], 'post_types' );

		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'custom-handbook' ] );
		update_option( 'custom-handbook_name', 'My Custom Handbook' );

		$post_id2 = $this->factory()->post->create( [ 'post_type' => 'example-handbook' ] );
		update_option( 'example-handbook_name', 'An Example Handbook' );

		$this->go_to( get_permalink( $post_id1 ) );
		$this->assertEquals( 'My Custom Handbook', wporg_get_current_handbook_name() );

		$this->go_to( get_permalink( $post_id2 ) );
		$this->assertEquals( 'An Example Handbook', wporg_get_current_handbook_name() );
	}

	public function test_wporg_get_current_handbook_name_multi_handbook_with_no_names_set() {
		reinit_handbooks( [ 'custom', 'example' ], 'post_types' );

		$post_id1 = $this->factory()->post->create( [ 'post_type' => 'custom-handbook' ] );
		$post_id2 = $this->factory()->post->create( [ 'post_type' => 'example-handbook' ] );

		$this->go_to( get_permalink( $post_id1 ) );
		$this->assertEquals( 'Custom Handbook', wporg_get_current_handbook_name() );

		$this->go_to( get_permalink( $post_id2 ) );
		$this->assertEquals( 'Example Handbook', wporg_get_current_handbook_name() );
	}

}