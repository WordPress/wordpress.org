<?php

if ( !class_exists( 'Organizer_Loader' ) ) :
/**
 * WCPT_Loader
 *
 * @package
 * @subpackage Loader
 * @since Organizer Post Type (0.1)
 *
 */
class Organizer_Loader {

	/**
	 * The main Organizer Post Type loader
	 */
	function organizer_loader () {

		// Attach constants to wcpt_loaded.
		add_action( 'wcpt_constants',           array ( $this, 'constants' ) );

		// Attach includes to wcpt_loaded.
		add_action( 'wcpt_includes',            array ( $this, 'includes' ) );

		// Attach post type registration to wcpt_init.
		add_action( 'wcpt_register_post_types', array ( $this, 'register_post_type' ) );
	}

	/**
	 * constants ()
	 *
	 * Default component constants that can be overridden or filtered
	 */
	function constants () {

		// The default post type ID
		if ( !defined( 'WCO_POST_TYPE_ID' ) )
			define( 'WCO_POST_TYPE_ID', apply_filters( 'wco_post_type_id', 'organizer' ) );

		// Default slug for post type
		if ( !defined( 'WCO_SLUG' ) )
			define( 'WCO_SLUG', apply_filters( 'wco_slug', 'organizers' ) );
	}

	/**
	 * includes ()
	 *
	 * Include required files
	 *
	 * @uses is_admin If in WordPress admin, load additional file
	 */
	function includes () {

		// Load the files
		require_once ( WCPT_DIR . '/wcpt-organizer/organizer-template.php' );

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once ( WCPT_DIR . '/wcpt-organizer/organizer-admin.php' );
	}

	/**
	 * register_post_type ()
	 *
	 * Setup the post types and taxonomies
	 *
	 * @todo Finish up the post type admin area with messages, columns, etc...*
	 */
	function register_post_type () {

		// Organizer post type labels
		$wco_labels = array (
			'name'                  => __( 'Organizers', 'wcpt' ),
			'singular_name'         => __( 'Organizer', 'wcpt' ),
			'add_new'               => __( 'Add New', 'wcpt' ),
			'add_new_item'          => __( 'Create New Organizer', 'wcpt' ),
			'edit'                  => __( 'Edit', 'wcpt' ),
			'edit_item'             => __( 'Edit Organizer', 'wcpt' ),
			'new_item'              => __( 'New Organizer', 'wcpt' ),
			'view'                  => __( 'View Organizer', 'wcpt' ),
			'view_item'             => __( 'View Organizer', 'wcpt' ),
			'search_items'          => __( 'Search Organizers', 'wcpt' ),
			'not_found'             => __( 'No Organizers found', 'wcpt' ),
			'not_found_in_trash'    => __( 'No Organizers found in Trash', 'wcpt' ),
			'parent_item_colon'     => __( 'Parent Organizer:', 'wcpt' )
		);

		// Organizer post type rewrite
		$wco_rewrite = array (
			'slug'        => WCO_SLUG,
			'with_front'  => false
		);

		// Organizer post type supports
		$wco_supports = array (
			'title',
			'editor',
			'thumbnail',
			'revisions'
		);

		// Register Organizer post type
		register_post_type (
			WCO_POST_TYPE_ID,
			apply_filters( 'wco_register_post_type',
				array (
					'labels'            => $wco_labels,
					'rewrite'           => $wco_rewrite,
					'supports'          => $wco_supports,
					'menu_position'     => '100',
					'public'            => false,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'query_var'         => true,
					'menu_icon'         => ''
				)
			)
		);
	}
}

endif; // class_exists check
