<?php
/*
Plugin Name: P2 New Post Categories
Plugin URI:  http://wordpress.org/plugins/p2-new-post-categories
Description: Adds a category dropdown to P2's new post form.
Version:     0.3
Author:      Ian Dunn
Author URI:  http://iandunn.name
License:     GPLv2 or Later
*/

class P2NewPostCategories {
	const VERSION = '0.3';

	/*
	 * Register hook callbacks
	 */
	public function __construct() {
		add_action( 'admin_notices',                        array( $this, 'check_dependencies' ) );
		add_action( 'wp_enqueue_scripts',                   array( $this, 'enqueue_scripts' ) );
		add_action( 'p2_post_form',                         array( $this, 'add_new_post_category_dropdown' ) );
		add_action( 'wp_ajax_p2npc_assign_category',        array( $this, 'assign_category_to_post' ) );
		add_action( 'wp_ajax_nopriv_p2npc_assign_category', array( $this, 'assign_category_to_post' ) );
	}

	/**
	 * The p2_new_post_submit_success trigger wasn't added until p2 1.5.2 and we can't work without it.  
	 */
	public function check_dependencies() {
		$current_theme = wp_get_theme();
		$parent_theme  = $current_theme->parent();
		if ( ! $parent_theme ) {
			$parent_theme = $current_theme;
		}
		
		if ( 'P2' != $parent_theme->get( 'Name' ) || version_compare( $parent_theme->get( 'Version' ), '1.5.2', '<' ) ) {
			echo '<div class="error">P2 New Post Categories requires p2 version 1.5.2 or above in order to work.</div>';
		}
	}
	
	/*
	 * Enqueue our scripts and styles
	 */
	public function enqueue_scripts() {
		wp_register_script(
			'P2NewPostCategories',
			plugins_url( 'functions.js', __FILE__ ),
			array( 'jquery', ),
			self::VERSION,
			true
		);

		wp_register_style(
			'P2NewPostCategories',
			plugins_url( 'style.css', __FILE__ ),
			array(),
			self::VERSION
		);

		wp_enqueue_script( 'P2NewPostCategories' );
		wp_enqueue_style( 'P2NewPostCategories' );
	}

	public function add_new_post_category_dropdown() {
		$params = apply_filters( 'p2npc_category_dropdown_params', array(
			'orderby'    => 'name',
			'id'         => 'p2-new-post-category',
			'hide_empty' => false,
			'selected'   => get_option( 'default_category' ),
		) );

		wp_dropdown_categories( $params );
		wp_nonce_field( 'p2npc_assign_category', 'p2npc_assign_category_nonce' );
	}

	/*
	 * Assign a category to a post
	 * This is an AJAX handler.
	 */
	public function assign_category_to_post() {
		$assigned    = false;
		$post_id     = absint( $_REQUEST['post_id'] );
		$category_id = absint( $_REQUEST['category_id'] );
		
		check_ajax_referer( 'p2npc_assign_category', 'p2npc_assign_category_nonce' );
		
		if ( current_user_can( 'edit_post', $post_id ) ) { 
			$assigned = wp_set_object_terms( $post_id, $category_id, 'category' );
			$assigned = is_array( $assigned ) && ! empty( $assigned );
		}
		
		wp_die( $assigned );
	}

} // end P2NewPostCategories

$GLOBALS['P2NewPostCategories'] = new P2NewPostCategories();