<?php

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * @group plugin-directory
 * @group plugin-directory-registration
 */
class Tests_Plugin_Directory extends WP_UnitTestCase {

	/**
	 * Helper to create a plugin post with required fields to avoid
	 * "Undefined array key" errors in filter_wp_insert_post_data.
	 */
	private function create_plugin_post( $args = [] ) {
		$now = current_time( 'mysql' );
		$gmt = current_time( 'mysql', true );

		return self::factory()->post->create( array_merge( [
			'post_type'         => 'plugin',
			'post_status'       => 'publish',
			'post_modified'     => $now,
			'post_modified_gmt' => $gmt,
		], $args ) );
	}

	/**
	 * Test that the 'plugin' post type is registered.
	 */
	function test_plugin_post_type_registered(): void {
		$this->assertTrue( post_type_exists( 'plugin' ) );
	}

	function test_plugin_post_type_is_public(): void {
		$post_type = get_post_type_object( 'plugin' );
		$this->assertTrue( $post_type->public );
	}

	function test_plugin_post_type_has_rest_support(): void {
		$post_type = get_post_type_object( 'plugin' );
		$this->assertTrue( $post_type->show_in_rest );
	}

	/**
	 * Test that expected taxonomies are registered.
	 *
	 * @dataProvider data_expected_taxonomies
	 */
	function test_taxonomy_registered( $taxonomy ): void {
		$this->assertTrue( taxonomy_exists( $taxonomy ), "Taxonomy '{$taxonomy}' should be registered." );
	}

	function data_expected_taxonomies(): array {
		return [
			'plugin_section'       => [ 'plugin_section' ],
			'plugin_tags'          => [ 'plugin_tags' ],
			'plugin_category'      => [ 'plugin_category' ],
			'plugin_contributors'  => [ 'plugin_contributors' ],
			'plugin_built_for'     => [ 'plugin_built_for' ],
			'plugin_business_model' => [ 'plugin_business_model' ],
			'plugin_committers'    => [ 'plugin_committers' ],
			'plugin_support_reps'  => [ 'plugin_support_reps' ],
		];
	}

	/**
	 * Test that custom post statuses are registered.
	 *
	 * @dataProvider data_expected_post_statuses
	 */
	function test_post_status_registered( $status ): void {
		$this->assertNotFalse(
			get_post_status_object( $status ),
			"Post status '{$status}' should be registered."
		);
	}

	function data_expected_post_statuses(): array {
		return [
			'new'      => [ 'new' ],
			'pending'  => [ 'pending' ],
			'disabled' => [ 'disabled' ],
			'approved' => [ 'approved' ],
			'closed'   => [ 'closed' ],
			'rejected' => [ 'rejected' ],
		];
	}

	/**
	 * Test that a plugin post can be created.
	 */
	function test_can_create_plugin_post(): void {
		$post_id = $this->create_plugin_post( [
			'post_title' => 'Test Tool',
			'post_name'  => 'test-tool',
		] );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertSame( 'plugin', $post->post_type );
		$this->assertSame( 'Test Tool', $post->post_title );
	}

	/**
	 * Test that plugin meta fields can be stored and retrieved.
	 */
	function test_plugin_meta_fields(): void {
		$post_id = $this->create_plugin_post( [ 'post_name' => 'meta-test' ] );

		update_post_meta( $post_id, 'stable_tag', '2.0.0' );
		update_post_meta( $post_id, 'tested', '6.4' );
		update_post_meta( $post_id, 'requires', '5.0' );
		update_post_meta( $post_id, 'requires_php', '7.4' );
		update_post_meta( $post_id, 'active_installs', 50000 );
		update_post_meta( $post_id, 'downloads', 100000 );
		update_post_meta( $post_id, 'rating', 90 );

		$this->assertSame( '2.0.0', get_post_meta( $post_id, 'stable_tag', true ) );
		$this->assertSame( '6.4', get_post_meta( $post_id, 'tested', true ) );
		$this->assertSame( '5.0', get_post_meta( $post_id, 'requires', true ) );
		$this->assertSame( '7.4', get_post_meta( $post_id, 'requires_php', true ) );
		$this->assertEquals( 50000, get_post_meta( $post_id, 'active_installs', true ) );
		$this->assertEquals( 100000, get_post_meta( $post_id, 'downloads', true ) );
		$this->assertEquals( 90, get_post_meta( $post_id, 'rating', true ) );
	}

	/**
	 * Test that terms can be assigned to the plugin_tags taxonomy.
	 */
	function test_assign_plugin_tags(): void {
		$post_id = $this->create_plugin_post();

		wp_set_object_terms( $post_id, [ 'seo', 'performance' ], 'plugin_tags' );

		$terms = wp_get_object_terms( $post_id, 'plugin_tags', [ 'fields' => 'names' ] );
		$this->assertContains( 'seo', $terms );
		$this->assertContains( 'performance', $terms );
	}

	/**
	 * Test that terms can be assigned to the plugin_contributors taxonomy.
	 */
	function test_assign_plugin_contributors(): void {
		$post_id = $this->create_plugin_post();

		wp_set_object_terms( $post_id, [ 'johndoe' ], 'plugin_contributors' );

		$terms = wp_get_object_terms( $post_id, 'plugin_contributors', [ 'fields' => 'names' ] );
		$this->assertContains( 'johndoe', $terms );
	}

	/**
	 * Test disabled post status is public (visible to non-logged-in users).
	 */
	function test_disabled_status_is_public(): void {
		$status = get_post_status_object( 'disabled' );
		$this->assertTrue( $status->public );
	}

	/**
	 * Test closed post status is public (visible to non-logged-in users).
	 */
	function test_closed_status_is_public(): void {
		$status = get_post_status_object( 'closed' );
		$this->assertTrue( $status->public );
	}

	/**
	 * Test that 'new' post status is not public.
	 */
	function test_new_status_is_not_public(): void {
		$status = get_post_status_object( 'new' );
		$this->assertFalse( $status->public );
	}

	/**
	 * Test filter_wp_insert_post_data preserves post_modified for plugin posts.
	 */
	function test_filter_wp_insert_post_data_preserves_modified(): void {
		$instance = Plugin_Directory::instance();

		$data = [
			'post_modified'     => '2024-01-15 10:00:00',
			'post_modified_gmt' => '2024-01-15 10:00:00',
			'post_status'       => 'publish',
			'post_name'         => 'test',
		];
		$postarr = [
			'post_type'         => 'plugin',
			'post_modified'     => '2024-01-15 10:00:00',
			'post_modified_gmt' => '2024-01-15 10:00:00',
			'post_status'       => 'publish',
		];

		$result = $instance->filter_wp_insert_post_data( $data, $postarr );

		$this->assertSame( '2024-01-15 10:00:00', $result['post_modified'] );
		$this->assertSame( '2024-01-15 10:00:00', $result['post_modified_gmt'] );
	}

	/**
	 * Test filter_wp_insert_post_data ignores non-plugin post types.
	 */
	function test_filter_wp_insert_post_data_ignores_non_plugin(): void {
		$instance = Plugin_Directory::instance();

		$data = [
			'post_modified'     => '2024-01-15 10:00:00',
			'post_modified_gmt' => '2024-01-15 10:00:00',
			'post_status'       => 'publish',
			'post_name'         => 'test',
		];
		$postarr = [
			'post_type'         => 'post',
			'post_modified'     => '2024-06-01 12:00:00',
			'post_modified_gmt' => '2024-06-01 12:00:00',
		];

		$result = $instance->filter_wp_insert_post_data( $data, $postarr );

		// Should return data unchanged for non-plugin types.
		$this->assertSame( $data, $result );
	}

	/**
	 * Test filter_wp_insert_post_data preserves slug for pending plugin posts.
	 */
	function test_filter_wp_insert_post_data_preserves_pending_slug(): void {
		$instance = Plugin_Directory::instance();

		$now = current_time( 'mysql' );
		$gmt = current_time( 'mysql', true );

		$post_id = $this->create_plugin_post( [
			'post_name'   => 'my-pending-tool',
			'post_status' => 'pending',
		] );

		$data = [
			'post_modified'     => $now,
			'post_modified_gmt' => $gmt,
			'post_status'       => 'pending',
			'post_name'         => '', // WP clears slug for pending posts.
		];
		$postarr = [
			'post_type'         => 'plugin',
			'post_modified'     => $now,
			'post_modified_gmt' => $gmt,
			'post_status'       => 'pending',
			'ID'                => $post_id,
		];

		$result = $instance->filter_wp_insert_post_data( $data, $postarr );

		$this->assertSame( 'my-pending-tool', $result['post_name'] );
	}
}
