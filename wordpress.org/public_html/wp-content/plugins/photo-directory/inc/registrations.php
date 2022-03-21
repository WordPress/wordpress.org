<?php
/**
 * Registration handler.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Registrations {

	/**
	 * Initializes component.
	 */
	public static function init() {
		// Register post types.
		add_action( 'init', [ __CLASS__, 'register_post_type' ], 1 );

		// Register taxonomies.
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );

		// Register post meta fields.
		add_action( 'init', [ __CLASS__, 'register_post_meta' ] );
	}

	/**
	 * Returns the photo post type slug.
	 */
	public static function get_post_type() {
		return 'photo';
	}

	/**
	 * Returns the taxonomy slug(s).
	 *
	 * @param string $type The type(s) of taxnomy slugs to return. One of 'category',
	 *                     'categories', 'color', 'colors', 'tag', 'tags', or 'all'.
	 * @return string|array The taxonomy slug, or array of all taxnomy slugs if
	 *                      requested type was 'all'.
	 */
	public static function get_taxonomy( $type ) {
		$taxonomy = false;

		$taxonomies = [
			'categories'   => 'photo_category',
			'colors'       => 'photo_color',
			'orientations' => 'photo_orientation',
			'tags'         => 'photo_tag',
		];

		switch ( $type ) {
			case 'category':
			case 'categories':
				$taxonomy = $taxonomies['categories'];
				break;
			case 'color':
			case 'colors':
				$taxonomy = $taxonomies['colors'];
				break;
			case 'orientation':
			case 'orientations':
				$taxonomy = $taxonomies['orientations'];
				break;
			case 'tag':
			case 'tags':
				$taxonomy = $taxonomies['tags'];
				break;
			case 'all':
				$taxonomy = array_values( $taxonomies );
				break;
		}

		return $taxonomy;
	}

	/**
	 * Registers post type.
	 */
	public static function register_post_type() {
		$post_type = self::get_post_type();
		$slug = 'photo';
		$label = __( 'Photos', 'wporg-photos' );
	
		$default_config = [
			'labels' => [
				'name'          => $label,
				'singular_name' => sprintf( __( 'Photo', 'wporg-photos' ), $label ),
				'menu_name'     => $label,
				'all_items'     => $label,
			],
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'rest_base'         => 'photos',
			'capability_type'   => 'photos',
			'capabilities'      => [
				'edit_posts'             => 'edit_photos',
				'delete_posts'           => 'delete_photos',
				'publish_posts'          => 'publish_photos',
				'edit_others_posts'      => 'edit_others_photos',
				'delete_others_posts'    => 'delete_others_photos',
				'edit_published_posts'   => 'edit_published_photos',
				'delete_published_posts' => 'delete_published_photos',
				'edit_private_posts'     => 'edit_private_photos',
				'delete_private_posts'   => 'delete_private_photos',
				'read_private_posts'     => 'read_private_photos',
				'edit_post'              => 'edit_photo',
				'delete_post'            => 'delete_photo',
				'read_post'              => 'read_photo',
			],
			'map_meta_cap'      => true,
			'has_archive'       => true,
			'hierarchical'      => false,
			'menu_icon'         => 'dashicons-format-image',
			'menu_position'     => 4,
			'rewrite' => [
				'feeds'         => false,
				'slug'          => $slug,
				'with_front'    => false,
			],
			'delete_with_user'  => false,
			'supports'          => [ 'title', 'editor', 'author', 'thumbnail', 'custom-fields', 'revisions' ],
		];
	
		/**
		 * Filters the default photo post type configuration prior to post type
		 * registration.
		 *
		 * @param array $default_config Configuration array.
		 * @param array $slug           Post type slug.
		 */
		$config = (array) apply_filters( 'wporg_photos_post_type_defaults', $default_config, $slug );
	
		\register_post_type( $post_type, $config );
	
	}

	/**
	 * Reggisters taxonomies.
	 */
	public static function register_taxonomies() {
		register_taxonomy( self::get_taxonomy( 'categories' ), self::get_post_type(), [
			'label'                 => __( 'Photo Categories', 'wporg-photos' ),
			'labels'                => [
				'name'                       => __( 'Categories', 'wporg-photos' ),
				'singular_name'              => _x( 'Category', 'taxonomy general name', 'wporg-photos' ),
				'search_items'               => __( 'Search Categories', 'wporg-photos' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Categories', 'wporg-photos' ),
				'parent_item'                => __( 'Parent Category', 'wporg-photos' ),
				'parent_item_colon'          => __( 'Parent Category:', 'wporg-photos' ),
				'edit_item'                  => __( 'Edit Category', 'wporg-photos' ),
				'update_item'                => __( 'Update Category', 'wporg-photos' ),
				'add_new_item'               => __( 'New Category', 'wporg-photos' ),
				'new_item_name'              => __( 'New Category', 'wporg-photos' ),
				'separate_items_with_commas' => __( 'Categories separated by comma', 'wporg-photos' ),
				'add_or_remove_items'        => __( 'Add or remove categories', 'wporg-photos' ),
				'choose_from_most_used'      => __( 'Choose from the most used categories', 'wporg-photos' ),
				'menu_name'                  => __( 'Photo Categories', 'wporg-photos' ),
			],
			'public'                => true,
			'hierarchical'          => true,
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
			'show_admin_column'     => true,
			'show_in_rest'          => true,
			'rest_base'             => 'photo-categories',
			'rewrite'               => [ 'slug' => 'c' ],
			'capabilities'          => [
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_photos',
			]
		] );

		register_taxonomy( self::get_taxonomy( 'colors' ), self::get_post_type(), [
			'label'                 => __( 'Photo Colors', 'wporg-photos' ),
			'labels'                => [
				'name'                       => __( 'Colors', 'wporg-photos' ),
				'singular_name'              => _x( 'Color', 'taxonomy general name', 'wporg-photos' ),
				'search_items'               => __( 'Search Colors', 'wporg-photos' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Colors', 'wporg-photos' ),
				'parent_item'                => __( 'Parent Color', 'wporg-photos' ),
				'parent_item_colon'          => __( 'Parent Color:', 'wporg-photos' ),
				'edit_item'                  => __( 'Edit Color', 'wporg-photos' ),
				'update_item'                => __( 'Update Color', 'wporg-photos' ),
				'add_new_item'               => __( 'New Color', 'wporg-photos' ),
				'new_item_name'              => __( 'New Color', 'wporg-photos' ),
				'separate_items_with_commas' => __( 'Colors separated by comma', 'wporg-photos' ),
				'add_or_remove_items'        => __( 'Add or remove colors', 'wporg-photos' ),
				'choose_from_most_used'      => __( 'Choose from the most used color', 'wporg-photos' ),
				'menu_name'                  => __( 'Photo Colors', 'wporg-photos' ),
			],
			'public'                => true,
			'hierarchical'          => false,
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
			'show_admin_column'     => true,
			'show_in_rest'          => true,
			'rest_base'             => 'photo-colors',
			'rewrite'               => [ 'slug' => 'color' ],
			'capabilities'          => [
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_photos',
			]
		] );

		register_taxonomy( self::get_taxonomy( 'orientations' ), self::get_post_type(), [
			'label'                 => __( 'Photo Orientations', 'wporg-photos' ),
			'labels'                => [
				'name'                       => __( 'Orientations', 'wporg-photos' ),
				'singular_name'              => _x( 'Orientation', 'taxonomy general name', 'wporg-photos' ),
				'search_items'               => __( 'Search Orientations', 'wporg-photos' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Orientations', 'wporg-photos' ),
				'parent_item'                => __( 'Parent Orientation', 'wporg-photos' ),
				'parent_item_colon'          => __( 'Parent Orientation:', 'wporg-photos' ),
				'edit_item'                  => __( 'Edit Orientation', 'wporg-photos' ),
				'update_item'                => __( 'Update Orientation', 'wporg-photos' ),
				'add_new_item'               => __( 'New Orientation', 'wporg-photos' ),
				'new_item_name'              => __( 'New Orientation', 'wporg-photos' ),
				'separate_items_with_commas' => __( 'Orientations separated by comma', 'wporg-photos' ),
				'add_or_remove_items'        => __( 'Add or remove orientations', 'wporg-photos' ),
				'choose_from_most_used'      => __( 'Choose from the most used orientations', 'wporg-photos' ),
				'menu_name'                  => __( 'Photo Orientations', 'wporg-photos' ),
			],
			'public'                => true,
			'hierarchical'          => false,
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
			'show_admin_column'     => true,
			'show_in_rest'          => true,
			'rest_base'             => 'photo-orientations',
			'rewrite'               => [ 'slug' => 'orientation' ],
			'capabilities'          => [
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_photos',
			]
		] );

		register_taxonomy( self::get_taxonomy( 'tags' ), self::get_post_type(), [
			'label'                 => __( 'Photo Tags', 'wporg-photos' ),
			'labels'                => [
				'name'                       => __( 'Tags', 'wporg-photos' ),
				'singular_name'              => _x( 'Tag', 'taxonomy general name', 'wporg-photos' ),
				'search_items'               => __( 'Search Tags', 'wporg-photos' ),
				'popular_items'              => null,
				'all_items'                  => __( 'All Tags', 'wporg-photos' ),
				'parent_item'                => __( 'Parent Tag', 'wporg-photos' ),
				'parent_item_colon'          => __( 'Parent Tag:', 'wporg-photos' ),
				'edit_item'                  => __( 'Edit Tag', 'wporg-photos' ),
				'update_item'                => __( 'Update Tag', 'wporg-photos' ),
				'add_new_item'               => __( 'New Tag', 'wporg-photos' ),
				'new_item_name'              => __( 'New Tag', 'wporg-photos' ),
				'separate_items_with_commas' => __( 'Tags separated by comma', 'wporg-photos' ),
				'add_or_remove_items'        => __( 'Add or remove tags', 'wporg-photos' ),
				'choose_from_most_used'      => __( 'Choose from the most used tags', 'wporg-photos' ),
				'menu_name'                  => __( 'Photo Tags', 'wporg-photos' ),
			],
			'public'                => true,
			'hierarchical'          => false,
			'sort'                  => false,
			'update_count_callback' => '_update_post_term_count',
			'show_admin_column'     => true,
			'show_in_rest'          => true,
			'rest_base'             => 'photo-tags',
			'rewrite'               => [ 'slug' => 't' ],
			'capabilities'          => [
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_photos',
			]
		] );
	}

	/**
	 * Returns the meta key name.
	 *
	 * @param string|false $type The label for the meta key, or false to get list
	 *                           of all meta keys. One of 'file_hash', 'moderator',
	 *                           'original_filename', 'original_filesize'. Default
	 *                           false.
	 * @return string|array The meta key, or all meta keys if $type is false.
	 */
	public static function get_meta_key( $type = false ) {
		$meta_keys = [
			// Name for the meta key used to store the photo contributor's IP address.
			'contributor_ip'    => 'photo_contributor_ip',
			// Name for the meta key used to store the MD5 hash of the photo.
			'file_hash'         => 'photo_file_md5_hash',
			// Name for the meta key used to store the moderator of the photo.
			'moderator'         => 'photo_moderator',
			// Name for the meta key used to store the original filename of the photo.
			'original_filename' => 'photo_original_filename',
			// Name for the meta key used to store the original filesize of the photo.
			'original_filesize' => 'photo_original_filesize',
		];

		return $type ? ( $meta_keys[ $type ] ?: '' ) : array_values( $meta_keys );
	}

	/**
	 * Registers post meta fields.
	 */
	public static function register_post_meta() {
		// Default post meta configuration.
		$default_config = [
			'single'            => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function() {
				return current_user_can( 'edit_photos' );
			},
			'show_in_rest'      => false,
		];

		// Register the meta key for the moderator.
		register_post_meta( self::get_post_type(), self::get_meta_key( 'moderator' ), [
			'type'              => 'integer',
			'description'       => __( 'The user who moderated the photo.', 'wporg-photos' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function() {
				return current_user_can( 'edit_photos' );
			},
			'show_in_rest'      => false,
		] );

		// Register the meta key for the moderator.
		register_post_meta( self::get_post_type(), self::get_meta_key( 'contributor_ip' ), [
			'type'              => 'string',
			'description'       => __( 'The IP address of the photo contributor.', 'wporg-photos' ),
			'single'            => true,
			'auth_callback'     => function() {
				return current_user_can( 'edit_photos' );
			},
			'show_in_rest'      => false,
		] );

		// Register the meta key for the file hash.
		register_post_meta( self::get_post_type(), self::get_meta_key( 'file_hash' ), [
			'type'              => 'string',
			'description'       => __( 'An MD5 hash of the photo file.', 'wporg-photos' ),
			'single'            => true,
			'auth_callback'     => function() {
				return current_user_can( 'edit_photos' );
			},
			'show_in_rest'      => false,
		] );

		// Register the meta key for the original filename.
		register_post_meta( self::get_post_type(), self::get_meta_key( 'original_filename' ), [
			'type'              => 'string',
			'description'       => __( 'The original filename of the photo file.', 'wporg-photos' ),
			'single'            => true,
			'auth_callback'     => function() {
				return current_user_can( 'edit_photos' );
			},
			'show_in_rest'      => false,
		] );

		// Register the meta key for the original filesize.
		register_post_meta( self::get_post_type(), self::get_meta_key( 'original_filesize' ), [
			'type'              => 'int',
			'description'       => __( 'The original filesize of the photo file.', 'wporg-photos' ),
			'single'            => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function() {
				return current_user_can( 'edit_photos' );
			},
			'show_in_rest'      => false,
		] );
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Registrations', 'init' ] );
