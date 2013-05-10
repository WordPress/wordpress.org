<?php

class WCB_Sponsors extends WCB_Loader {
	var $meta_manager;

	function constants() {
		wcb_maybe_define( 'WCB_SPONSOR_POST_TYPE',      'wcb_sponsor',       'wcb_sponsor_post_type'      );
		wcb_maybe_define( 'WCB_SPONSOR_SLUG',           'sponsor',           'wcb_sponsor_slug'           );
		wcb_maybe_define( 'WCB_SPONSOR_LEVEL_TAXONOMY', 'wcb_sponsor_level', 'wcb_sponsor_level_taxonomy' );
		wcb_maybe_define( 'WCB_SPONSOR_LEVEL_SLUG',     'sponsor_level',     'wcb_sponsor_level_slug'     );
	}

	function includes() {
		require_once "class-wcb-sponsor-template.php";
		require_once "class-wcb-sponsor-order.php";
		require_once "class-wcb-widget-sponsors.php";
	}

	function loaded() {
		new WCB_Sponsor_Order;
		register_widget('WCB_Widget_Sponsors');
	}

	function register_post_types() {
		// Sponsor post type labels
		$labels = array (
			'name'                  => __( 'Sponsors', 'wordcampbase' ),
			'singular_name'         => __( 'Sponsor', 'wordcampbase' ),
			'add_new'               => __( 'Add New', 'wordcampbase' ),
			'add_new_item'          => __( 'Create New Sponsor', 'wordcampbase' ),
			'edit'                  => __( 'Edit', 'wordcampbase' ),
			'edit_item'             => __( 'Edit Sponsor', 'wordcampbase' ),
			'new_item'              => __( 'New Sponsor', 'wordcampbase' ),
			'view'                  => __( 'View Sponsor', 'wordcampbase' ),
			'view_item'             => __( 'View Sponsor', 'wordcampbase' ),
			'search_items'          => __( 'Search Sponsors', 'wordcampbase' ),
			'not_found'             => __( 'No sponsors found', 'wordcampbase' ),
			'not_found_in_trash'    => __( 'No sponsors found in Trash', 'wordcampbase' ),
			'parent_item_colon'     => __( 'Parent Sponsor:', 'wordcampbase' )
		);

		// Sponsor post type rewrite
		$rewrite = array (
			'slug'        => WCB_SPONSOR_SLUG,
			'with_front'  => false,
		);

		// Sponsor post type supports
		$supports = array (
			'title',
			'editor',
			'revisions',
			'thumbnail',
		);

		$menu_icon = wcb_menu_icon( WCB_SPONSOR_POST_TYPE, WCB_URL . '/images/sponsors.png' );

		// Register sponsor post type
		register_post_type (
			WCB_SPONSOR_POST_TYPE,
			apply_filters( 'wcb_sponsor_register_post_type',
				array (
					'labels'            => $labels,
					'rewrite'           => $rewrite,
					'supports'          => $supports,
					'menu_position'     => 21,
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'query_var'         => true,
					'menu_icon'         => $menu_icon,
				)
			)
		);
	}

	function register_taxonomies() {

		// Labels
		$labels = array (
			'name'              => __( 'Sponsor Levels', 'wordcampbase'),
			'singular_name'     => __( 'Sponsor Level', 'wordcampbase'),
			'search_items'      => __( 'Search Sponsor Levels', 'wordcampbase'),
			'popular_items'     => __( 'Popular Sponsor Levels', 'wordcampbase'),
			'all_items'         => __( 'All Sponsor Levels', 'wordcampbase'),
			'edit_item'         => __( 'Edit Sponsor Level', 'wordcampbase'),
			'update_item'       => __( 'Update Sponsor Level','wordcampbase'),
			'add_new_item'      => __( 'Add Sponsor Level', 'wordcampbase'),
			'new_item_name'     => __( 'New Sponsor Level', 'wordcampbase'),
		);

		// Rewrite
		$rewrite = array (
			'slug' => WCB_SPONSOR_LEVEL_SLUG
		);

		// Register the taxonomy
		register_taxonomy (
			WCB_SPONSOR_LEVEL_TAXONOMY,     // The tax ID
			WCB_SPONSOR_POST_TYPE,          // The post type ID
			apply_filters( 'wcb_sponsor_level_tax_register',
				array (
					'labels'                => $labels,
					'rewrite'               => $rewrite,
					'query_var'             => 'sponsor_level',
					'hierarchical'          => true,
					'public'                => true,
					'show_ui'               => true,
				)
			)
		);
	}
}

?>