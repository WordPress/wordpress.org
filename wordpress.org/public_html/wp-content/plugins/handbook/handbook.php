<?php
/**
 * Plugin Name: Handbook
 * Description: Features for a handbook, complete with glossary and table of contents
 * Author: Nacin
 */

require_once dirname( __FILE__ ) . '/inc/glossary.php';
require_once dirname( __FILE__ ) . '/inc/table-of-contents.php';
require_once dirname( __FILE__ ) . '/inc/email-post-changes.php';

WPorg_Handbook_Glossary::init();
new WPorg_Handbook_TOC;

class WPorg_Handbook {

	static function caps() {
		return array(
			'edit_handbook_pages', 'edit_others_handbook_pages',
			'edit_published_handbook_pages',
		);
	}

	static function editor_caps() {
		return array(
			'publish_handbook_pages',
			'delete_handbook_pages', 'delete_others_handbook_pages',
			'delete_published_handbook_pages', 'delete_private_handbook_pages',
			'edit_private_handbook_pages', 'read_private_handbook_pages',
		);
	}

	function __construct() {
		add_filter( 'user_has_cap', array( $this, 'grant_handbook_caps' ) );
		add_filter( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_page_access_denied', array( $this, 'admin_page_access_denied' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 2 );
		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_action( 'widgets_init', array( $this, 'handbook_sidebar' ), 11 ); // After P2
		add_action( 'wporg_email_changes_for_post_types', array( $this, 'wporg_email_changes_for_post_types' ) );
	}

	function grant_handbook_caps( $caps ) {
		if ( ! is_user_member_of_blog() )
			return $caps;
		foreach ( self::caps() as $cap ) {
			$caps[ $cap ] = true;
		}
		if ( ! empty( $caps['edit_pages'] ) ) {
			foreach ( self::editor_caps() as $cap ) {
				$caps[ $cap ] = true;
			}
		}
		return $caps;
	}

	function register_post_type() {
		register_post_type( 'handbook', array(
			'labels' => array(
				'name' => 'Handbook Pages',
				'singular_name' => 'Handbook Page',
				'menu_name' => 'Handbook',
			),
			'public' => true,
			'show_ui' => true,
			'capability_type' => 'handbook_page',
			'map_meta_cap' => true,
			'has_archive' => true,
			'hierarchical' => true,
			'menu_position' => 11,
			'rewrite' => true,
			'delete_with_user' => false,
			'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'comments', 'revisions' ),
		) );
	}

	function admin_page_access_denied() {
		if ( ! current_user_can( 'read' ) ) {
			wp_redirect( admin_url( 'edit.php?post_type=handbook' ) );
			exit;
		}
	}

	function post_type_link( $link, $post ) {
		if ( $post->post_type === 'handbook' && $post->post_name === 'handbook' )
			return get_post_type_archive_link( 'handbook' );
		return $link;
	}

	function pre_get_posts( $query ) {
		if ( $query->is_main_query() && ! $query->is_admin && $query->is_post_type_archive( 'handbook' ) ) {
			$query->set( 'handbook', 'handbook' );
		}
	}

	function handbook_sidebar() {
		if ( ! class_exists( 'P2' ) )
			return;

		register_sidebar( array( 'id' => 'handbook', 'name' => 'Handbook', 'description' => 'Used on handbook pages' ) );

		require_once dirname( __FILE__ ) . '/inc/widgets.php';
		register_widget( 'WPorg_Handbook_Pages_Widget' );
	}

	function wporg_email_changes_for_post_types( $post_types ) {
		if ( ! in_array( 'handbook', $post_types ) )
			$post_types[] = 'handbook';
		return $post_types;
	}
}

new WPorg_Handbook;
