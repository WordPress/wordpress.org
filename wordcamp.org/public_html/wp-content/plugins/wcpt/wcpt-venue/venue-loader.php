<?php

if ( !class_exists( 'Venue_Loader' ) ) :
/**
 * Venue_Loader
 *
 * @package
 * @subpackage Loader
 * @since Venue Post Type (0.1)
 *
 */
class Venue_Loader {

	/**
	 * The main Venue Post Type loader
	 */
	function venue_loader () {

		// Attach constants to wcpt_constants.
		add_action( 'wcpt_constants',           array ( $this, 'constants' ) );

		// Attach includes to wcpt_includes.
		add_action( 'wcpt_includes',            array ( $this, 'includes' ) );

		// Attach post type registration to wcpt_register_post_types.
		add_action( 'wcpt_register_post_types', array ( $this, 'register_post_types' ) );
	}

	/**
	 * constants ()
	 *
	 * Default component constants that can be overridden or filtered
	 */
	function constants () {

		// The default post type ID
		if ( !defined( 'WCV_POST_TYPE_ID' ) )
			define( 'WCV_POST_TYPE_ID', apply_filters( 'wcv_post_type_id', 'venue' ) );

		// Default slug for post type
		if ( !defined( 'WCV_SLUG' ) )
			define( 'WCV_SLUG', apply_filters( 'wcv_slug', 'venues' ) );
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
		require_once ( WCPT_DIR . '/wcpt-venue/venue-template.php' );

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once ( WCPT_DIR . '/wcpt-venue/venue-admin.php' );
	}

	/**
	 * register_post_type ()
	 *
	 * Setup the post types and taxonomies
	 *
	 * @todo Finish up the post type admin area with messages, columns, etc...*
	 */
	function register_post_types () {

		// Venue post type labels
		$labels = array (
			'name'                  => __( 'Venues', 'wcpt' ),
			'singular_name'         => __( 'Venue', 'wcpt' ),
			'add_new'               => __( 'Add New', 'wcpt' ),
			'add_new_item'          => __( 'Create New Venue', 'wcpt' ),
			'edit'                  => __( 'Edit', 'wcpt' ),
			'edit_item'             => __( 'Edit Venue', 'wcpt' ),
			'new_item'              => __( 'New Venue', 'wcpt' ),
			'view'                  => __( 'View Venue', 'wcpt' ),
			'view_item'             => __( 'View Venue', 'wcpt' ),
			'search_items'          => __( 'Search Venues', 'wcpt' ),
			'not_found'             => __( 'No Venues found', 'wcpt' ),
			'not_found_in_trash'    => __( 'No Venues found in Trash', 'wcpt' ),
			'parent_item_colon'     => __( 'Parent Venue:', 'wcpt' )
		);

		// Venue post type rewrite
		$rewrite = array (
			'slug'        => WCV_SLUG,
			'with_front'  => false
		);

		// Venue post type supports
		$supports = array (
			'title',
			'editor',
			'thumbnail',
			'revisions',
		);

		// Register Venue post type
		register_post_type (
			WCV_POST_TYPE_ID,
			apply_filters( 'wco_register_post_type',
				array (
					'labels'            => $labels,
					'rewrite'           => $rewrite,
					'supports'          => $supports,
					'menu_position'     => '101',
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
