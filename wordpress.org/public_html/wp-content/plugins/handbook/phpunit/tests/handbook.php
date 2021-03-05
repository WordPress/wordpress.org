<?php

defined( 'ABSPATH' ) or die();

// Mock P2_Resolved_Posts class for later.
class P2_Resolved_Posts {
	public static function instance() {
		return __CLASS__;
	}
	public static function register_filter() {
		$GLOBALS['p2_resolved_posts'] = new self;
		add_filter( 'p2_action_links', [ P2_Resolved_Posts::instance(), 'p2_action_links' ], 100 );
	}
}

class WPorg_Handbook_Handbook_Test extends WP_UnitTestCase {

	protected $handbook;

	public function setUp() {
		parent::setup();
		WPorg_Handbook_Init::init();

		$handbooks = WPorg_Handbook_Init::get_handbook_objects();
		$this->handbook = reset( $handbooks );
	}

	public function tearDown() {
		parent::tearDown();

		foreach ( WPorg_Handbook_Init::get_handbook_objects() as $obj ) {
			unset( $obj );
		}
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
		$this->assertTrue( class_exists( 'WPorg_Handbook' ) );
	}

	/*
	 * ::caps()
	 */

	public function test_caps() {
		$this->assertEquals(
			[ 'edit_handbook_pages', 'edit_others_handbook_pages',  'edit_published_handbook_pages' ],
			WPorg_Handbook::caps()
		);
	}

	/*
	 * ::editor_caps()
	 */

	public function test_editor_caps() {
		$this->assertEquals(
			[ 'publish_handbook_pages', 'delete_handbook_pages', 'delete_others_handbook_pages', 'delete_published_handbook_pages',
			'delete_private_handbook_pages', 'edit_private_handbook_pages', 'read_private_handbook_pages' ],
			WPorg_Handbook::editor_caps()
		);
	}

	/*
	 * ::get_default_handbook_config()
	 */

	public function test_get_default_handbook_config_returns_same_number_of_items_defined_in_associated_dataprovider() {
		$this->assertEquals( count( dataprovider_get_default_config() ), count( WPorg_Handbook::get_default_handbook_config() ) );
	}

	/**
	 * @dataProvider get_default_config
	 */
	public function test_get_default_handbook_config( $key, $default ) {
		$config = WPorg_Handbook::get_default_handbook_config();

		$this->assertArrayHasKey( $key, $config );
		$this->assertEquals( $default, $config[ $key ] );
	}

	/*
	 * ::get_name()
	 */

	public function test_get_name_default() {
		$this->assertEquals( 'Handbook', WPorg_Handbook::get_name() );
	}

	public function test_get_name_default_raw() {
		$this->assertEmpty( WPorg_Handbook::get_name( 'handbook', true ) );
	}

	public function test_get_name_when_explicitly_set() {
		$name = 'Example Handbook';
		update_option( 'handbook_name', $name );

		$this->assertEquals( $name, WPorg_Handbook::get_name() );
	}

	public function test_get_name_default_for_custom_post_type() {
		$handbook = 'plugin-handbook';

		$this->assertEquals( 'Plugin Handbook', WPorg_Handbook::get_name( $handbook ) );
	}

	public function test_get_name_for_custom_post_type_when_explicitly_set() {
		$name = 'WordPress Plugin Handbook';
		$handbook = 'plugin-handbook';
		update_option( $handbook . '_name', $name );

		$this->assertEquals( $name, WPorg_Handbook::get_name( $handbook ) );
	}

	/*
	 * __construct()
	 */

	public function test_constructor_sets_post_type_of_handbook() {
		$handbook = new WPorg_Handbook( 'handbook' );
		$this->assertEquals( 'handbook', $handbook->post_type );
	}

	public function test_constructor_sets_post_type_of_plugin_handbook() {
		add_filter( 'handbooks_config', function ()  {
			return [ 'plugin' => [] ];
		} );

		$handbook = new WPorg_Handbook( 'plugin-handbook' );
		$this->assertEquals( 'plugin-handbook', $handbook->post_type );
	}

	public function test_constructor_sets_setting_name() {
		$handbook = new WPorg_Handbook( 'handbook' );

		$this->assertEquals( 'handbook_name', $handbook->setting_name );
	}

	public function test_constructor_sets_setting_name_for_non_standard_handbook() {
		$handbook = new WPorg_Handbook( 'plugin-handbook' );

		$this->assertEquals( 'plugin-handbook_name', $handbook->setting_name );
	}

	/*
	 * display_post_states()
	 */

	public function test_display_post_states_if_not_handbook() {
		$states = [ 'Draft', 'Example' ];
		$handbook = new WPorg_Handbook( 'handbook' );
		$post = $this->factory->post->create_and_get();

		$this->assertEquals( $states, $handbook->display_post_states( $states, $post ) );
	}

	public function test_display_post_states_if_handbook_but_not_front_page() {
		$states = [ 'Draft', 'Example' ];
		$handbook = new WPorg_Handbook( 'handbook' );
		$post = $this->factory->post->create_and_get( [ 'post_type' => 'handbook' ] );

		$this->assertEquals( $states, $handbook->display_post_states( $states, $post ) );
	}

	public function test_display_post_states_if_handbook_front_page() {
		$states = [ 'Draft', 'Example' ];
		$handbook = new WPorg_Handbook( 'handbook' );
		$post = $this->factory->post->create_and_get( [ 'post_type' => 'handbook', 'post_name' => 'welcome' ] );

		$this->assertEquals( [ 'Draft', 'Example', 'Handbook Front Page' ], $handbook->display_post_states( $states, $post ) );
	}

	/*
	 * add_body_class()
	 */

	public function test_add_body_class() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( [ 'example', 'single-handbook', 'handbook-landing-page' ], $this->handbook->add_body_class( [ 'example' ] ) );
	}

	public function test_add_body_class_via_post_type_archive() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );
		$this->go_to( get_post_type_archive_link( 'handbook' ) );

		$this->assertEquals( [ 'example', 'single-handbook', 'handbook-landing-page' ], $this->handbook->add_body_class( [ 'example' ] ) );
	}

	public function test_add_body_class_for_welcome_via_post() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'welcome' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( [ 'example', 'single-handbook', 'handbook-landing-page' ], $this->handbook->add_body_class( [ 'example' ] ) );
	}

	public function test_add_body_class_for_welcome_via_post_type() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'welcome' ] );
		$this->go_to( get_post_type_archive_link( 'handbook' ) );

		$this->assertEquals( [ 'example', 'single-handbook', 'handbook-landing-page' ], $this->handbook->add_body_class( [ 'example' ] ) );
	}

	public function test_add_body_class_for_handbook_non_root_page() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'some-page' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( [ 'example', 'single-handbook' ], $this->handbook->add_body_class( [ 'example' ] ) );
	}

	public function test_add_body_class_for_non_handbook_post() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'post', 'post_name' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( [ 'example' ], $this->handbook->add_body_class( [ 'example' ] ) );
	}

	/*
	 * add_post_class()
	 */

	public function test_add_post_class_for_handbook_post() {
		$post_id = $this->factory->post->create( [ 'post_type' => 'handbook' ] );
		$GLOBALS['post'] = get_post( $post_id );

		$this->assertEquals( [ 'something', 'type-handbook' ], $this->handbook->add_post_class( [ 'something' ] ) );
	}

	public function test_add_post_class_for_custom_handbook_post() {
		$handbook1 = new WPorg_Handbook( 'theme-handbook' );
		$handbook2 = new WPorg_Handbook( 'plugin-handbook' );
		$post_id = $this->factory->post->create( [ 'post_type' => 'theme-handbook' ] );
		$GLOBALS['post'] = get_post( $post_id );

		$this->assertEquals( [ 'something', 'type-handbook' ], $handbook1->add_post_class( [ 'something' ] ) );
	}

	public function test_add_post_class_for_handbook_post_of_another_post_type() {
		$handbook1 = new WPorg_Handbook( 'theme-handbook' );
		$handbook2 = new WPorg_Handbook( 'plugin-handbook' );
		$post_id = $this->factory->post->create( [ 'post_type' => 'theme-handbook' ] );
		$GLOBALS['post'] = get_post( $post_id );

		$this->assertEquals( [ 'something' ], $handbook2->add_post_class( [ 'something' ] ) );
	}

	public function test_add_post_class_for_nonhandbook_post() {
		$post_id = $this->factory->post->create( [ 'post_type' => 'post' ] );
		$GLOBALS['post'] = get_post( $post_id );

		$this->assertEquals( [ 'something' ], $this->handbook->add_post_class( [ 'something' ] ) );
	}

	/*
	 * add_name_setting()
	 */

	public function test_add_name_setting() {
		$this->handbook->add_name_setting();
		$setting_name = 'handbook_name';

		$registered = get_registered_settings();
		$this->assertArrayHasKey( $setting_name, $registered );

		$args = $registered[ $setting_name ];
		$this->assertEquals( 'general', $args['group'] );
	}

	/*
	 * name_setting_html()
	 */

	public function test_name_setting_html_default() {
		$expected = '<input type="text" id="handbook_name" name="handbook_name" value="" class="regular-text ltr" />';

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', $this->handbook->name_setting_html() );
	}

	/*
	 * grant_handbook_caps()
	 */

	public function test_grant_handbook_caps_non_user() {
		$this->assertEmpty( $this->handbook->grant_handbook_caps( [] ) );
	}

	public function test_grant_handbook_caps_user() {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$expected[ 'something' ] = true;
		foreach ( WPorg_Handbook::caps() as $cap ) {
			$expected[ $cap ] = true;
		}

		$this->assertEquals( $expected, $this->handbook->grant_handbook_caps( [ 'something' => true ] ) );
	}

	public function test_grant_handbook_caps_editor() {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$expected[ 'edit_pages' ] = true;
		foreach ( array_merge( WPorg_Handbook::caps(), WPorg_Handbook::editor_caps() ) as $cap ) {
			$expected[ $cap ] = true;
		}

		$this->assertEquals( $expected, $this->handbook->grant_handbook_caps( [ 'edit_pages' => true ] ) );
	}

	/*
	 * register_post_type()
	 */

	public function test_register_post_type() {
		$pobj = get_post_type_object( 'handbook' );

		$this->assertInstanceOf( 'WP_Post_Type', $pobj );
		$this->assertEquals( 'handbook', $pobj->name );
	}

	public function test_register_post_type_for_custom_handbook_post_type() {
		$hb = new WPorg_Handbook( 'plugin-handbook' );
		$hb->register_post_type();

		$pobj = get_post_type_object( 'plugin-handbook' );

		$this->assertInstanceOf( 'WP_Post_Type', $pobj );
		$this->assertEquals( 'dashicons-book', $pobj->menu_icon );
		$this->assertEquals( 'plugin-handbook', $pobj->name );
		$this->assertEquals( 'Plugin Handbook', $pobj->label );

		unset( $hb );
	}

	public function test_filter_handbook_post_type_defaults() {
		$hb = new WPorg_Handbook( 'plugin-handbook' );
		add_filter( 'handbook_post_type_defaults', function ( $config ) { $config['menu_icon'] = 'dashicons-admin-network'; return $config; } );
		$hb->register_post_type();

		$pobj = get_post_type_object( 'plugin-handbook' );

		$this->assertInstanceOf( 'WP_Post_Type', $pobj );
		$this->assertEquals( 'dashicons-admin-network', $pobj->menu_icon );

		unset( $hb );
	}

	/*
	 * post_is_landing_page()
	 */

	public function test_post_is_landing_page_on_non_handbook_page() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'post', 'post_name' => 'handbook' ] );

		$this->assertFalse( $this->handbook->post_is_landing_page( $post_id ) );
		$this->assertFalse( $this->handbook->post_is_landing_page( get_post( $post_id ) ) );
	}

	public function test_post_is_landing_page_on_handbook_non_landing_page() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'some-page' ] );

		$this->assertFalse( $this->handbook->post_is_landing_page( $post_id ) );
		$this->assertFalse( $this->handbook->post_is_landing_page( get_post( $post_id ) ) );
	}

	public function test_post_is_landing_page_on_landing_page_with_handbook_slug() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );

		$this->assertTrue( $this->handbook->post_is_landing_page( $post_id ) );
		$this->assertTrue( $this->handbook->post_is_landing_page( get_post( $post_id ) ) );
	}

	public function test_post_is_landing_page_on_landing_page_with_welcome_slug() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook', 'post_name' => 'welcome' ] );

		$this->assertTrue( $this->handbook->post_is_landing_page( $post_id ) );
		$this->assertTrue( $this->handbook->post_is_landing_page( get_post( $post_id ) ) );
	}

	public function test_post_is_landing_page_on_landing_page_with_post_type_slug() {
		$hb = new WPorg_Handbook( 'plugin-handbook' );
		$post_id = $this->factory()->post->create( [ 'post_type' => 'plugin-handbook', 'post_name' => 'plugin-handbook' ] );

		$this->assertFalse( $this->handbook->post_is_landing_page( $post_id ) );
		$this->assertTrue( $hb->post_is_landing_page( $post_id ) );
		$this->assertTrue( $hb->post_is_landing_page( get_post( $post_id ) ) );

		unset( $hb );
	}

	public function test_post_is_landing_page_on_landing_page_with_partial_post_type_slug() {
		$hb = new WPorg_Handbook( 'plugin-handbook' );
		$post_id = $this->factory()->post->create( [ 'post_type' => 'plugin-handbook', 'post_name' => 'plugin' ] );

		$this->assertFalse( $this->handbook->post_is_landing_page( $post_id ) );
		$this->assertTrue( $hb->post_is_landing_page( $post_id ) );
		$this->assertTrue( $hb->post_is_landing_page( get_post( $post_id ) ) );

		unset( $hb );
	}

	/*
	 * post_type_link()
	 */

	public function test_post_type_link_for_non_handbook() {
		$post = $this->factory()->post->create_and_get( [ 'post_type' => 'post', 'post_name' => 'handbook' ] );
		$expected = 'something';

		$this->assertEquals( $expected, $this->handbook->post_type_link( $expected, $post ) );
	}

	public function test_post_type_link_for_handbook_non_landing() {
		$post = $this->factory()->post->create_and_get( [ 'post_type' => 'handbook', 'post_name' => 'example' ] );
		$expected = 'something';

		$this->assertEquals( $expected, $this->handbook->post_type_link( $expected, $post ) );
	}

	public function test_post_type_link_for_handbook_landing() {
		$post = $this->factory()->post->create_and_get( [ 'post_type' => 'handbook', 'post_name' => 'handbook' ] );
		$expected = 'something';

		$this->assertEquals( 'http://example.org/?post_type=handbook', $this->handbook->post_type_link( 'something', $post ) );
	}

	/*
	 * handbook_sidebar()
	 */

	public function test_handbook_sidebar() {
		global $wp_registered_sidebars;

		$this->assertTrue( isset( $wp_registered_sidebars[ 'handbook' ] ) );
		$this->assertSame(
			[ 'name', 'id', 'description', 'class', 'before_widget', 'after_widget', 'before_title', 'after_title', 'before_sidebar', 'after_sidebar' ],
			array_keys( $wp_registered_sidebars[ 'handbook' ] )
		);
		$this->assertEquals( 'handbook', $wp_registered_sidebars[ 'handbook' ]['id'] );
		$this->assertEquals( 'Handbook Sidebar', $wp_registered_sidebars[ 'handbook' ]['name'] );
	}

	public function test_filter_wporg_handbook_sidebar_args() {
		global $wp_registered_sidebars;
		add_filter( 'wporg_handbook_sidebar_args', function ( $args ) { $args['name'] = 'New Name'; return $args; } );
		$wp_registered_sidebars = [];

		$this->assertFalse( isset( $wp_registered_sidebars[ 'handbook' ] ) );

		$this->handbook->handbook_sidebar();

		$this->assertTrue( isset( $wp_registered_sidebars[ 'handbook' ] ) );
		$this->assertEquals( 'handbook', $wp_registered_sidebars[ 'handbook' ]['id'] );
		$this->assertEquals( 'New Name', $wp_registered_sidebars[ 'handbook' ]['name'] );
	}

	public function test_handbook_sidebar_registers_widget() {
		global $wp_widget_factory;

		$this->assertArrayHasKey( 'WPorg_Handbook_Pages_Widget', $wp_widget_factory->widgets );
	}

	/*
	 * wporg_email_changes_for_post_types()
	 */

	public function test_wporg_email_changes_for_post_types() {
		$this->assertEquals( [ 'custom', 'handbook' ], $this->handbook->wporg_email_changes_for_post_types( [ 'custom' ] ) );
	}

	public function test_wporg_email_changes_for_post_types_with_handbook_already_present() {
		$this->assertEquals( [ 'custom', 'handbook' ], $this->handbook->wporg_email_changes_for_post_types( [ 'custom', 'handbook' ] ) );
	}

	public function test_wporg_email_changes_for_post_types_with_multiple_handbooks() {
		$hb = new WPorg_Handbook( 'plugin-handbook' );

		$this->assertEquals( [ 'custom', 'plugin-handbook' ], $hb->wporg_email_changes_for_post_types( [ 'custom' ] ) );

		unset( $hb );
	}

	/*
	 * disable_p2_resolved_posts_action_links()
	 */

	public function test_disable_p2_resolved_posts_action_links() {
		P2_Resolved_Posts::register_filter();
		$this->assertEquals( 100, has_filter( 'p2_action_links', [ P2_Resolved_Posts::instance(), 'p2_action_links' ] ) );

		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );
		$this->handbook->disable_p2_resolved_posts_action_links();

		$this->assertFalse( has_filter( 'p2_action_links', [ P2_Resolved_Posts::instance(), 'p2_action_links' ] ) );
	}

	public function test_disable_p2_resolved_posts_action_links_on_non_handbook() {
		P2_Resolved_Posts::register_filter();
		$this->assertEquals( 100, has_filter( 'p2_action_links', [ P2_Resolved_Posts::instance(), 'p2_action_links' ] ) );

		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		$this->handbook->disable_p2_resolved_posts_action_links();

		$this->assertEquals( 100, has_filter( 'p2_action_links', [ P2_Resolved_Posts::instance(), 'p2_action_links' ] ) );
	}

	/*
	 * disable_o2_processing()
	 */

	public function test_disable_o2_processing_for_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( $this->handbook->disable_o2_processing( true ) );
		$this->assertFalse( $this->handbook->disable_o2_processing( false ) );
	}

	public function test_disable_o2_processing_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertTrue( $this->handbook->disable_o2_processing( true ) );
		$this->assertFalse( $this->handbook->disable_o2_processing( false ) );
	}

	/*
	 * o2_application_container()
	 */

	public function test_o2_application_container_for_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( '#primary', $this->handbook->o2_application_container( '#something' ) );
	}

	public function test_o2_application_container_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( '#something', $this->handbook->o2_application_container( '#something' ) );
	}

	/*
	 * o2_view_type()
	 */

	public function test_o2_view_type_for_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( 'single', $this->handbook->o2_view_type( 'something' ) );
	}

	public function test_o2_view_type_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( 'something', $this->handbook->o2_view_type( 'something' ) );
	}

	/*
	 * o2_post_fragment()
	 */

	public function test_o2_post_fragment_for_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$post_fragment = [ 'id' => $post_id, 'isPage' => false ];
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( [ 'id' => $post_id, 'isPage' => true ], $this->handbook->o2_post_fragment( $post_fragment ) );
	}

	public function test_o2_post_fragment_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$post_fragment = [ 'id' => $post_id, 'isPage' => false ];
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( $post_fragment, $this->handbook->o2_post_fragment( $post_fragment ) );
	}

	public function test_o2_post_fragment_with_invalid_id() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$post_fragment = [ 'id' => 'cat', 'isPage' => false ];
		$this->go_to( get_permalink( $post_id ) );

		$this->assertEquals( $post_fragment, $this->handbook->o2_post_fragment( $post_fragment ) );
	}

/*
public function test_o2_post_fragment_with_invalid_arg() {
	$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
	$post_fragment = 'cat';
	$this->go_to( get_permalink( $post_id ) );

	$this->assertEquals( $post_fragment, $this->handbook->o2_post_fragment( $post_fragment ) );
}
*/

	/*
	 * comments_open()
	 */

	public function test_comments_open_for_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );

		$this->assertFalse( $this->handbook->comments_open( true, $post_id ) );
		$this->assertFalse( $this->handbook->comments_open( false, $post_id ) );
	}

	public function test_comments_open_for_non_handbook() {
		$post_id = $this->factory()->post->create();

		$this->assertTrue( $this->handbook->comments_open( true, $post_id ) );
		$this->assertFalse( $this->handbook->comments_open( false, $post_id ) );
	}

	/*
	 * highlight_menu_handbook_link()
	 */

	// menu has page marked as current
	// menu has link to handbook homepage
	// menu has a link to handbook and/or handbooks
	public function test_highlight_menu_handbook_link_for_non_handbook() {
		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$menu_items = [
			(object)[ 'object_id' => 0, 'url' => 'http://example.org/', 'classes' => [], 'current' => false ],
			(object)[ 'object_id' => 0, 'url' => 'http://example.org/?p=4', 'classes' => [], 'current' => false ],
		];

		$this->assertEquals( $menu_items, $this->handbook->highlight_menu_handbook_link( $menu_items ) );
	}

	public function test_highlight_menu_handbook_link_for_handbook() {
		$post_id = $this->factory()->post->create( [ 'post_type' => 'handbook' ] );
		$this->go_to( get_permalink( $post_id ) );

		$menu_items = [
			(object)[ 'object_id' => 0, 'url' => 'http://example.org/', 'classes' => [], 'current' => false ],
			(object)[ 'object_id' => 0, 'url' => 'http://example.org/?post_type=handbook', 'classes' => [], 'current' => false ],
		];

		$expected = [
			(object)[ 'object_id' => 0, 'url' => 'http://example.org/', 'classes' => [], 'current' => false ],
			(object)[ 'object_id' => 0, 'url' => 'http://example.org/?post_type=handbook', 'classes' => ['current-menu-item'], 'current' => false ],
		];

		$this->assertEquals( $expected, $this->handbook->highlight_menu_handbook_link( $menu_items ) );
	}

}
