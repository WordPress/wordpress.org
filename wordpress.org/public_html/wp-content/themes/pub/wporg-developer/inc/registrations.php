<?php
/**
 * Code Reference registrations.
 *
 * @package wporg-developer
 */

/**
 * Class to register post types, taxonomies, etc.
 */
class DevHub_Registrations {

	/**
	 * Initializer.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding/removing hooks.
	 */
	public static function do_init() {
		// Register post types.
		self::register_post_types();

		// Register taxonomies.
		self::register_taxonomies();

		// Register P2P relationships.
		add_action( 'p2p_init', array( __CLASS__, 'register_post_relationships' ) );
	}

	/**
	 * Registers post types.
	 */
	public static function register_post_types() {
		$supports = array(
			'comments',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'title',
		);

		// Functions
		register_post_type( 'wp-parser-function', array(
			'has_archive' => 'reference/functions',
			'label'       => __( 'Functions', 'wporg' ),
			'labels'      => array(
				'name'               => __( 'Functions', 'wporg' ),
				'singular_name'      => __( 'Function', 'wporg' ),
				'all_items'          => __( 'Functions', 'wporg' ),
				'new_item'           => __( 'New Function', 'wporg' ),
				'add_new'            => __( 'Add New', 'wporg' ),
				'add_new_item'       => __( 'Add New Function', 'wporg' ),
				'edit_item'          => __( 'Edit Function', 'wporg' ),
				'view_item'          => __( 'View Function', 'wporg' ),
				'search_items'       => __( 'Search Functions', 'wporg' ),
				'not_found'          => __( 'No Functions found', 'wporg' ),
				'not_found_in_trash' => __( 'No Functions found in trash', 'wporg' ),
				'parent_item_colon'  => __( 'Parent Function', 'wporg' ),
				'menu_name'          => __( 'Functions', 'wporg' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'reference/functions',
				'with_front' => false,
			),
			'supports'    => $supports,
		) );

		// Methods
		add_rewrite_rule( 'reference/classes/page/([0-9]{1,})/?$', 'index.php?post_type=wp-parser-class&paged=$matches[1]', 'top' );
		add_rewrite_rule( 'reference/classes/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top' );

		// Classes
		register_post_type( 'wp-parser-class', array(
			'has_archive' => 'reference/classes',
			'label'       => __( 'Classes', 'wporg' ),
			'labels'      => array(
				'name'               => __( 'Classes', 'wporg' ),
				'singular_name'      => __( 'Class', 'wporg' ),
				'all_items'          => __( 'Classes', 'wporg' ),
				'new_item'           => __( 'New Class', 'wporg' ),
				'add_new'            => __( 'Add New', 'wporg' ),
				'add_new_item'       => __( 'Add New Class', 'wporg' ),
				'edit_item'          => __( 'Edit Class', 'wporg' ),
				'view_item'          => __( 'View Class', 'wporg' ),
				'search_items'       => __( 'Search Classes', 'wporg' ),
				'not_found'          => __( 'No Classes found', 'wporg' ),
				'not_found_in_trash' => __( 'No Classes found in trash', 'wporg' ),
				'parent_item_colon'  => __( 'Parent Class', 'wporg' ),
				'menu_name'          => __( 'Classes', 'wporg' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'reference/classes',
				'with_front' => false,
			),
			'supports'    => $supports,
		) );

		// Hooks
		register_post_type( 'wp-parser-hook', array(
			'has_archive' => 'reference/hooks',
			'label'       => __( 'Hooks', 'wporg' ),
			'labels'      => array(
				'name'               => __( 'Hooks', 'wporg' ),
				'singular_name'      => __( 'Hook', 'wporg' ),
				'all_items'          => __( 'Hooks', 'wporg' ),
				'new_item'           => __( 'New Hook', 'wporg' ),
				'add_new'            => __( 'Add New', 'wporg' ),
				'add_new_item'       => __( 'Add New Hook', 'wporg' ),
				'edit_item'          => __( 'Edit Hook', 'wporg' ),
				'view_item'          => __( 'View Hook', 'wporg' ),
				'search_items'       => __( 'Search Hooks', 'wporg' ),
				'not_found'          => __( 'No Hooks found', 'wporg' ),
				'not_found_in_trash' => __( 'No Hooks found in trash', 'wporg' ),
				'parent_item_colon'  => __( 'Parent Hook', 'wporg' ),
				'menu_name'          => __( 'Hooks', 'wporg' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'reference/hooks',
				'with_front' => false,
			),
			'supports'    => $supports,
		) );

		// Methods
		register_post_type( 'wp-parser-method', array(
			'has_archive' => 'reference/methods',
			'label'       => __( 'Methods', 'wporg' ),
			'labels'      => array(
				'name'               => __( 'Methods', 'wporg' ),
				'singular_name'      => __( 'Method', 'wporg' ),
				'all_items'          => __( 'Methods', 'wporg' ),
				'new_item'           => __( 'New Method', 'wporg' ),
				'add_new'            => __( 'Add New', 'wporg' ),
				'add_new_item'       => __( 'Add New Method', 'wporg' ),
				'edit_item'          => __( 'Edit Method', 'wporg' ),
				'view_item'          => __( 'View Method', 'wporg' ),
				'search_items'       => __( 'Search Methods', 'wporg' ),
				'not_found'          => __( 'No Methods found', 'wporg' ),
				'not_found_in_trash' => __( 'No Methods found in trash', 'wporg' ),
				'parent_item_colon'  => __( 'Parent Method', 'wporg' ),
				'menu_name'          => __( 'Methods', 'wporg' ),
			),
			'public'      => true,
			'rewrite'     => array(
				'feeds'      => false,
				'slug'       => 'classes',
				'with_front' => false,
			),
			'supports'    => $supports,
		) );
	}

	/**
	 * Registers taxonomies.
	 */
	public static function register_taxonomies() {
		// Files
		register_taxonomy( 'wp-parser-source-file', \DevHub\get_parsed_post_types(), array(
			'label'                 => __( 'Files', 'wporg' ),
			'labels'                => array(
				'name'                       => __( 'Files', 'wporg' ),
				'singular_name'              => _x( 'File', 'taxonomy general name', 'wporg' ),
				'search_items'               => __( 'Search Files', 'wporg' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Files', 'wporg' ),
				'parent_item'                => __( 'Parent File', 'wporg' ),
				'parent_item_colon'          => __( 'Parent File:', 'wporg' ),
				'edit_item'                  => __( 'Edit File', 'wporg' ),
				'update_item'                => __( 'Update File', 'wporg' ),
				'add_new_item'               => __( 'New File', 'wporg' ),
				'new_item_name'              => __( 'New File', 'wporg' ),
				'separate_items_with_commas' => __( 'Files separated by comma', 'wporg' ),
				'add_or_remove_items'        => __( 'Add or remove Files', 'wporg' ),
				'choose_from_most_used'      => __( 'Choose from the most used Files', 'wporg' ),
				'menu_name'                  => __( 'Files', 'wporg' ),
			),
			'public'                => true,
			// Hierarchical x 2 to enable (.+) rather than ([^/]+) for rewrites.
			'hierarchical'          => true,
			'rewrite'               => array( 'with_front' => false, 'slug' => 'reference/files', 'hierarchical' => true ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		) );

		// Package
		register_taxonomy( 'wp-parser-package', \DevHub\get_parsed_post_types(), array(
			'hierarchical'          => true,
			'label'                 => '@package',
			'public'                => true,
			'rewrite'               => array( 'with_front' => false, 'slug' => 'reference/package' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		) );

		// @since
		register_taxonomy( 'wp-parser-since', \DevHub\get_parsed_post_types(), array(
			'hierarchical'          => true,
			'label'                 => __( '@since', 'wporg' ),
			'public'                => true,
			'rewrite'               => array( 'with_front' => false, 'slug' => 'reference/since' ),
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
		) );
	}

	/**
	 * Registers P2P post relationships.
	 */
	public static function register_post_relationships() {

		/*
		 * Functions to functions, methods and hooks
		 */
		p2p_register_connection_type( array(
			'name'             => 'functions_to_functions',
			'from'             => 'wp-parser-function',
			'to'               => 'wp-parser-function',
			'can_create_post'  => false,
			'self_connections' => true,
			'from_query_vars'  => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'to_query_vars'    => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'title'            => array( 'from' => __( 'Uses Functions', 'wporg' ), 'to' => __( 'Used by Functions', 'wporg' ) ),
		) );

		p2p_register_connection_type( array(
			'name'             => 'functions_to_methods',
			'from'             => 'wp-parser-function',
			'to'               => 'wp-parser-method',
			'can_create_post'  => false,
			'from_query_vars'  => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'to_query_vars'    => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'title'            => array( 'from' => __( 'Uses Methods', 'wporg' ), 'to' => __( 'Used by Functions', 'wporg' ) ),
		) );

		p2p_register_connection_type( array(
			'name'             => 'functions_to_hooks',
			'from'             => 'wp-parser-function',
			'to'               => 'wp-parser-hook',
			'can_create_post'  => false,
			'from_query_vars'  => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'to_query_vars'    => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'title'            => array( 'from' => __( 'Uses Hooks', 'wporg' ), 'to' => __( 'Used by Functions', 'wporg' ) ),
		) );

		/*
		 * Methods to functions, methods and hooks
		 */
		p2p_register_connection_type( array(
			'name'             => 'methods_to_functions',
			'from'             => 'wp-parser-method',
			'to'               => 'wp-parser-function',
			'can_create_post'  => false,
			'from_query_vars'  => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'to_query_vars'    => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'title'            => array( 'from' => __( 'Uses Functions', 'wporg' ), 'to' => __( 'Used by Methods', 'wporg' ) ),
		) );

		p2p_register_connection_type( array(
			'name'             => 'methods_to_methods',
			'from'             => 'wp-parser-method',
			'to'               => 'wp-parser-method',
			'can_create_post'  => false,
			'self_connections' => true,
			'from_query_vars'  => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'to_query_vars'    => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'title'            => array( 'from' => __( 'Uses Methods', 'wporg' ), 'to' => __( 'Used by Methods', 'wporg' ) ),
		) );

		p2p_register_connection_type( array(
			'name'             => 'methods_to_hooks',
			'from'             => 'wp-parser-method',
			'to'               => 'wp-parser-hook',
			'can_create_post'  => false,
			'from_query_vars'  => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'to_query_vars'    => array( 'orderby' => 'post_title', 'order' => 'ASC' ),
			'title'            => array( 'from' => __( 'Used by Methods', 'wporg' ), 'to' => __( 'Uses Hooks', 'wporg' ) ),
		) );

	}

} // DevHub_Registrations

DevHub_Registrations::init();
