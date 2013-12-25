<?php

if ( !class_exists( 'WordCamp_Loader' ) ) :
/**
 * WordCamp_Loader
 *
 * @package
 * @subpackage Loader
 * @since WordCamp Post Type (0.1)
 *
 */
class WordCamp_Loader {

	/**
	 * The main WordCamp Post Type loader
	 */
	function wordcamp_loader () {

		// Attach constants to wcpt_loaded.
		add_action( 'wcpt_constants',           array ( $this, 'constants' ) );

		// Attach includes to wcpt_includes.
		add_action( 'wcpt_includes',            array ( $this, 'includes' ) );

		// Attach post type registration to wcpt_register_post_types.
		add_action( 'wcpt_register_post_types', array ( $this, 'register_post_types' ) );

		// Attach tag registration wcpt_register_taxonomies.
		//add_action( 'wcpt_register_taxonomies', array ( $this, 'register_taxonomies' ) );
	}

	/**
	 * constants ()
	 *
	 * Default component constants that can be overridden or filtered
	 */
	function constants () {

		// The default post type ID
		if ( !defined( 'WCPT_POST_TYPE_ID' ) )
			define( 'WCPT_POST_TYPE_ID', apply_filters( 'wcpt_post_type_id', 'wordcamp' ) );

		// The default year ID
		if ( !defined( 'WCPT_YEAR_ID' ) )
			define( 'WCPT_YEAR_ID', apply_filters( 'wcpt_tag_id', 'wordcamp_year' ) );

		// Default slug for post type
		if ( !defined( 'WCPT_SLUG' ) )
			define( 'WCPT_SLUG', apply_filters( 'wcpt_slug', 'wordcamps' ) );
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
		require_once ( WCPT_DIR . '/wcpt-wordcamp/wordcamp-template.php' );

		// Quick admin check and load if needed
		if ( is_admin() )
			require_once ( WCPT_DIR . '/wcpt-wordcamp/wordcamp-admin.php' );
	}

	/**
	 * register_post_type ()
	 *
	 * Setup the post types and taxonomies
	 *
	 * @todo Finish up the post type admin area with messages, columns, etc...*
	 */
	function register_post_types () {

		// WordCamp post type labels
		$wcpt_labels = array (
			'name'                  => __( 'WordCamps', 'wcpt' ),
			'singular_name'         => __( 'WordCamp', 'wcpt' ),
			'add_new'               => __( 'Add New', 'wcpt' ),
			'add_new_item'          => __( 'Create New WordCamp', 'wcpt' ),
			'edit'                  => __( 'Edit', 'wcpt' ),
			'edit_item'             => __( 'Edit WordCamp', 'wcpt' ),
			'new_item'              => __( 'New WordCamp', 'wcpt' ),
			'view'                  => __( 'View WordCamp', 'wcpt' ),
			'view_item'             => __( 'View WordCamp', 'wcpt' ),
			'search_items'          => __( 'Search WordCamps', 'wcpt' ),
			'not_found'             => __( 'No WordCamps found', 'wcpt' ),
			'not_found_in_trash'    => __( 'No WordCamps found in Trash', 'wcpt' ),
			'parent_item_colon'     => __( 'Parent WordCamp:', 'wcpt' )
		);

		// WordCamp post type rewrite
		$wcpt_rewrite = array (
			'slug'        => WCPT_SLUG,
			'with_front'  => false
		);

		// WordCamp post type supports
		$wcpt_supports = array (
			'title',
			'editor',
			'thumbnail',
			'revisions',
		);

		// Register WordCamp post type
		register_post_type (
			WCPT_POST_TYPE_ID,
			apply_filters( 'wcpt_register_post_type',
				array (
					'labels'            => $wcpt_labels,
					'rewrite'           => $wcpt_rewrite,
					'supports'          => $wcpt_supports,
					'menu_position'     => '100',
					'public'            => true,
					'show_ui'           => true,
					'can_export'        => true,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'has_archive'       => true,
					'query_var'         => true,
					'menu_icon'         => ''
				)
			)
		);
	}

	/**
	 * register_taxonomies ()
	 *
	 * Register the built in WordCamp Post Type taxonomies
	 *
	 * @since WordCamp Post Type (0.1)
	 *
	 * @uses register_taxonomy()
	 * @uses apply_filters()
	 */
	function register_taxonomies () {

		// Tag labels
		$tag_labels = array (
			'name'              => __( 'Years', 'wcpt' ),
			'singular_name'     => __( 'Year', 'wcpt' ),
			'search_items'      => __( 'Search Years', 'wcpt' ),
			'popular_items'     => __( 'Popular Years', 'wcpt' ),
			'all_items'         => __( 'All Years', 'wcpt' ),
			'edit_item'         => __( 'Edit Year', 'wcpt' ),
			'update_item'       => __( 'Update Year', 'wcpt' ),
			'add_new_item'      => __( 'Add Year', 'wcpt' ),
			'new_item_name'     => __( 'New Year', 'wcpt' ),
		);

		// Tag rewrite
		$tag_rewrite = array (
			'slug' => 'year'
		);

		// Register the  tag taxonomy
		register_taxonomy (
			WCPT_TAG_ID,               // The  tag ID
			WCPT_POST_TYPE_ID,         // The  post type ID
			apply_filters( 'wcpt_register_year',
				array (
					'labels'                => $tag_labels,
					'rewrite'               => $tag_rewrite,
					//'update_count_callback' => '_update_post_term_count',
					'query_var'             => 'wc-year',
					'hierarchical'          => false,
					'public'                => true,
					'show_ui'               => true,
				)
			)
		);
	}
}

endif; // class_exists check
