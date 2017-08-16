<?php
namespace PTR;
class Content_Model {

	/**
	 * Create custom post type to store the directories we need to process.
	 *
	 * @since 1.0.0
	 * @return  null
	 */
	public static function action_init_register_post_type() {
		register_post_type( 'result',
			array(
				'labels' => array(
					'name' => __( 'Test Results', 'ptr' ),
					'singular_name' => __( 'Test Result', 'ptr' ),
				),
				'public' => true,
				'has_archive' => false,
				'show_in_rest' => true,
				'hierarchical' => true,
				'rewrite' => array(
					'slug' => 'test-results',
				),
				'supports' => array(
					'title',
					'editor',
					'author',
					'custom-fields',
					'page-attributes',
				),
				'map_meta_cap' => true,
				'capabilities' => array(
					'edit_post' => 'edit_result',
					'edit_posts' => 'edit_results',
					'edit_others_posts' => 'edit_others_results',
					'publish_posts' => 'publish_results',
					'read_post' => 'read_result',
					'read_private_posts' => 'read_private_results',
					'delete_post' => 'delete_result',
				),
			)
		);
	}

	/**
	 * Create a custom role to manage ability to create results
	 */
	public static function action_init_register_role() {
		if ( ! get_role( 'test-reporter' ) ) {
			add_role( 'test-reporter', __( 'Test Reporter', 'ptr' ), array(
				'read' => true,
			) );
		}
		$role = get_role( 'test-reporter' );
		if ( $role ) {
			$role->add_cap( 'edit_results' );
			$role->add_cap( 'publish_results' );
		}
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'edit_results' );
			$role->add_cap( 'edit_others_results' );
			$role->add_cap( 'publish_results' );
			$role->add_cap( 'read_private_results' );
		}
	}

}
