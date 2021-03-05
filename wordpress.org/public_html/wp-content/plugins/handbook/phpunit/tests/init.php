<?php

defined( 'ABSPATH' ) or die();

class WPorg_Handbook_Init_Test extends WP_UnitTestCase {

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
	// DATA PROVIDERS
	//
	//


	public static function get_default_config() {
		return \dataprovider_get_default_config();
	}


	//
	//
	// TESTS
	//
	//


	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WPorg_Handbook_Init' ) );
	}

	public function test_hooks_after_setup_theme_to_initialize() {
		$this->assertEquals( 10, has_action( 'after_setup_theme', [ 'WPorg_Handbook_Init', 'init' ] ) );
	}

	public function test_registers_default_hooks() {
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', [ 'WPorg_Handbook_Init' , 'enqueue_styles' ] ) );
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', [ 'WPorg_Handbook_Init' , 'enqueue_scripts' ] ) );
	}

	/*
	 * get_handbook_objects()
	 */

	public function test_get_handbook_objects_default() {
		$handbooks = WPorg_Handbook_Init::get_handbook_objects();
		$first_handbook = reset( $handbooks );

		$this->assertIsArray( $handbooks );
		$this->assertCount( 1, $handbooks );
		$this->assertInstanceOf( 'WPorg_Handbook', $first_handbook );
		$this->assertEquals( 'handbook', $first_handbook->post_type );
	}

	public function test_get_handbook_objects_filtered() {
		reinit_handbooks( [ 'plugins', 'themes' ], 'post_types' );

		$handbooks = WPorg_Handbook_Init::get_handbook_objects();

		$this->assertIsArray( $handbooks );
		$this->assertCount( 2, $handbooks );

		$first_handbook = reset( $handbooks );
		$this->assertInstanceOf( 'WPorg_Handbook', $first_handbook );
		$this->assertEquals( 'plugins-handbook', $first_handbook->post_type );

		$second_handbook = next( $handbooks );
		$this->assertInstanceOf( 'WPorg_Handbook', $second_handbook );
		$this->assertEquals( 'themes-handbook', $second_handbook->post_type );
	}

	/*
	 * get_handbook()
	 */

	public function test_get_handbook_for_invalid_handbook() {
		$this->assertFalse( WPorg_Handbook_Init::get_handbook( 'nonexistent-handbook' ) );
	}

	public function test_get_handbook() {
		$handbook = WPorg_Handbook_Init::get_handbook( 'handbook' );

		$this->assertTrue( is_a( $handbook, 'WPorg_Handbook' ) );
		$this->assertEquals( 'handbook', $handbook->post_type );
	}

	public function test_get_handbook_when_multiple_handbooks_present() {
		reinit_handbooks( [ 'plugins-handbook', 'themes-handbook' ], 'post_types' );

		$handbook = WPorg_Handbook_Init::get_handbook( 'plugins-handbook' );

		$this->assertTrue( is_a( $handbook, 'WPorg_Handbook' ) );
		$this->assertEquals( 'plugins-handbook', $handbook->post_type );
	}

	/*
	 * get_post_types()
	 */

	public function test_get_post_types_default() {
		$this->assertEquals( ['handbook'], WPorg_Handbook_Init::get_post_types() );
	}

	public function test_get_post_types_custom() {
		reinit_handbooks( [ 'plugins-handbook', 'themes' ], 'post_types' );

		// Note: The automatic appending of '-handbook' is for back-compat.
		$this->assertEquals( ['plugins-handbook', 'themes-handbook'], WPorg_Handbook_Init::get_post_types() );
	}

	public function test_get_post_types_filtered() {
		add_filter( 'handbook_post_types', function ( $post_types ) {
			$post_types[] = 'example';
			return $post_types;
		}, 11 );
		reinit_handbooks( [ 'plugins', 'themes' ], 'post_types' );

		// Note: The automatic appending of '-handbook' is for back-compat.
		$this->assertEquals( [ 'plugins-handbook', 'themes-handbook', 'example-handbook' ], WPorg_Handbook_Init::get_post_types() );
	}

	/*
	 * enqueue_styles()
	 */

	public function test_enqueue_styles() {
		$this->assertFalse( wp_style_is( 'wporg-handbook-css', 'enqueued' ) );

		WPorg_Handbook_Init::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wporg-handbook-css', 'enqueued' ) );
	}

	/*
	 * enqueue_scripts()
	 */

	public function test_enqueue_scripts() {
		$this->assertFalse( wp_script_is( 'wporg-handbook', 'enqueued' ) );

		WPorg_Handbook_Init::enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wporg-handbook', 'enqueued' ) );
	}

	/*
	 * reset()
	 */

	public function test_reset() {
		$this->test_get_post_types_custom();

		$this->assertEquals( 2, count( WPorg_Handbook_Init::get_handbook_objects() ) );
		$this->assertEquals( 2, count( WPorg_Handbook_Init::get_post_types() ) );

		WPorg_Handbook_Init::reset();

		$this->assertEmpty( count( WPorg_Handbook_Init::get_handbook_objects() ) );
		$this->assertEmpty( count( WPorg_Handbook_Init::get_post_types() ) );
	}

	/*
	 * get_handbooks_config()
	 */

	/**
	 * @dataProvider get_default_config
	 */
	public function test_get_handbooks_config_default( $key, $default ) {
		$configs = WPorg_Handbook_Init::get_handbooks_config();
		$this->assertArrayHasKey( 'handbook', $configs );
		$this->assertEquals( 1, count( $configs ) );

		$config = $configs['handbook'];

		$this->assertArrayHasKey( $key, $config );
		$value = $config[ $key ];

		if ( 'slug' === $key ) {
			$this->assertEquals( 'handbook', $value );
		} elseif ( '' === $default ) {
			$this->assertEmpty( $value );
		} else {
			$this->assertEquals( $default, $value );
		}
	}

	public function test_get_handbooks_config_non_handbook() {
		$this->assertEmpty( WPorg_Handbook_Init::get_handbooks_config( 'nonexistent-handbook' ) );
	}

	/**
	 * @dataProvider get_default_config
	 */
	public function test_get_handbooks_config_specific_handbook_default( $key, $default ) {
		$config = WPorg_Handbook_Init::get_handbooks_config( 'handbook' );

		$this->assertArrayHasKey( $key, $config );
		$value = $config[ $key ];

		if ( 'slug' === $key ) {
			$this->assertEquals( 'handbook', $value );
		} elseif ( '' === $default ) {
			$this->assertEmpty( $value );
		} else {
			$this->assertEquals( $default, $value );
		}
	}

	/**
	 * @dataProvider get_default_config
	 */
	public function test_get_handbooks_config_specific_custom_handbook( $key, $default ) {
		reinit_handbooks( [ 'plugins' => [] ] );

		$config = WPorg_Handbook_Init::get_handbooks_config( 'plugins-handbook' );

		$this->assertArrayHasKey( $key, $config );
		$value = $config[ $key ];

		if ( 'slug' === $key ) {
			$this->assertEquals( 'plugins', $value );
		} elseif ( '' === $default ) {
			$this->assertEmpty( $value );
		} else {
			$this->assertEquals( $default, $value );
		}
	}

}