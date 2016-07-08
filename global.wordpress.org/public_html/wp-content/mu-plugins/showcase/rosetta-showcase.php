<?php
/**
 * Plugin Name: Rosetta Showcase
 * Plugin URI: https://wordpress.org/
 * Description: Showcase for local sites.
 * Author: Zé Fontainhas and Andrew Nacin
 * Version: 2.0
 */

class Rosetta_Showcase {

	/**
	 * Name of the custom post type.
	 *
	 * @var string
	 */
	public $post_type = 'showcase';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Attaches hooks once plugins are loaded.
	 */
	public function plugins_loaded() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_filter( 'post_type_link', [ $this, 'filter_showcase_permalink' ], 10, 2 );
		add_action( 'save_post', [ $this, 'save_showcase_url' ], 10, 2 );
		add_action( 'manage_' . $this->post_type . '_posts_columns',  [ $this, 'showcase_custom_columns' ] );
		add_filter( 'manage_' . $this->post_type . '_posts_custom_column', [ $this, 'showcase_custom_column' ], 10, 2 );
		add_filter( 'manage_edit-' . $this->post_type . '_sortable_columns', [ $this, 'showcase_sortable_columns' ], 10, 0 );
		add_filter( 'sharing_meta_box_show', [ $this, 'disable_sharing_meta_box' ], 10, 2 );
	}

	/**
	 * Registers the custom post type used for the showcase.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Showcase', 'post type general name', 'rosetta' ),
			'singular_name'      => _x( 'Showcase Site', 'post type singular name', 'rosetta' ),
			'add_new'            => _x( 'Add New', 'showcase item', 'rosetta' ),
			'add_new_item'       => __( 'Add New Site', 'rosetta' ),
			'edit_item'          => __( 'Edit Site', 'rosetta' ),
			'new_item'           => __( 'New Site', 'rosetta' ),
			'view_item'          => __( 'View Site', 'rosetta' ),
			'search_items'       => __( 'Search Showcase', 'rosetta' ),
			'not_found'          => __( 'Nothing found', 'rosetta' ),
			'not_found_in_trash' => __( 'Nothing found in Trash', 'rosetta' ),
		);

		$args = array(
			'labels'               => $labels,
			'public'               => true,
			'show_ui'              => true,
			'rewrite'              => false,
			'has_archive'          => false,
			'capability_type'      => 'post',
			'map_meta_cap'         => true,
			'hierarchical'         => false,
			'show_in_nav_menus'    => false,
			'can_export'           => false,
			'exclude_from_search'  => true,
			'supports'             => array( 'title', 'excerpt' ),
			'menu_icon'            => 'dashicons-slides',
			'register_meta_box_cb' => [ $this, 'register_showcase_meta_box' ],
		);

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Replaces the default permalink with the URL to the website.
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @return string The filtered URL.
	 */
	public function filter_showcase_permalink( $post_link, $post ) {
		if ( $this->post_type === $post->post_type ) {
			$url = get_post_meta( $post->ID, '_rosetta_showcase_url', true );
			if ( $url ) {
				return $url;
			}
		}

		return $post_link;
	}

	/**
	 * Replaces the default excerpt metabox with a custom one.
	 */
	public function register_showcase_meta_box() {
		remove_meta_box(
			'postexcerpt',
			$this->post_type,
			'normal'
		);

		add_meta_box(
			'rosetta_showcase_meta',
			__( 'Description and URL', 'rosetta' ),
			[ $this, 'showcase_meta_box' ],
			$this->post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Prints the metabox for the URL and description of a website.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function showcase_meta_box( $post ) {
		$url = get_post_meta( $post->ID, '_rosetta_showcase_url', true );
		?>
		<p><label for="rosetta_showcase_url"><?php _e( 'URL', 'rosetta' ); ?></label>
			<input style="margin-left: 0; width: 98%" name="rosetta_showcase_url" type="text" value="<?php echo esc_url( $url ); ?>" /></p>
		<label for="excerpt"><?php _e( 'Description', 'rosetta' ); ?></label>
		<textarea rows="1" cols="40" name="excerpt" tabindex="6" id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped ?></textarea>
		<?php
	}

	/**
	 * Saves the URL of a website as a post meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_showcase_url( $post_id, $post ) {
		if ( $this->post_type !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! isset( $_POST['rosetta_showcase_url'] ) ) {
			return;
		}

		$url = esc_url_raw( $_POST['rosetta_showcase_url'] );

		update_post_meta( $post_id, '_rosetta_showcase_url', $url );
	}

	/**
	 * Disables Jetpack's sharing meta box for a showcase item.
	 *
	 * @param $enable Whether the metabox is visible
	 * @param WP_Post $post    Post object.
	 * @return bool True if metabox is visible, false if not.
	 */
	public function disable_sharing_meta_box( $enable, $post ) {
		if ( $this->post_type === $post->post_type ) {
			return false;
		}

		return $enable;
	}

	/**
	 * Filters the columns displayed in the list table.
	 *
	 * @param array $columns An array of column names.
	 * @return array An array of column names.
	 */
	public function showcase_custom_columns( $columns ) {
		$columns = [
			'cb'          => $columns['cb'],
			'shot'        => __( 'Image', 'rosetta' ),
			'website'     => __( 'Website', 'rosetta' ),
			'description' => __( 'Description', 'rosetta' ),
			'url'         => __( 'URL', 'rosetta' ),
		];

		return $columns;
	}

	/**
	 * Prints the content for each custom column in the list table.
	 *
	 * @param string $column   The name of the column to display.
	 * @param int    $post_id  The current post ID.
	 */
	public function showcase_custom_column( $column, $post_id ) {
		switch ( $column ) {
			case 'website' :
				$title = _draft_or_post_title();
				printf(
					'<a class="row-title" href="%s" aria-label="%s">%s</a>',
					get_edit_post_link( $post_id ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)', 'rosetta' ), $title ) ),
					$title
				);
				break;

			case 'description' :
				the_excerpt();
				break;

			case 'url' :
				$url = get_post_meta( $post_id, '_rosetta_showcase_url', true );
				echo '<a href="' . esc_url( $url ) . '">' . esc_url_raw( $url ) . '</a>';
				break;

			case 'shot' :
				$url = esc_url( get_post_meta( $post_id, '_rosetta_showcase_url', true ) );
				if ( $url ) {
					echo '<a href="' . esc_url( $url ) . '" target="_blank"><img width="200" src="https://wordpress.com/mshots/v1/' . urlencode( $url ) . '?w=400" /></a>';
				}
				break;
		}
	}

	/**
	 * Filters the list table sortable columns.
	 *
	 * @return array An array of sortable columns.
	 */
	public function showcase_sortable_columns() {
		$columns = [
			'website' => 'title',
		];

		return $columns;
	}

	/**
	 * Retrieves four random showcase posts to be used on the front end.
	 *
	 * @return array List of showcase posts.
	 */
	public function front() {
		$posts = get_posts( [
			'post_type'   => $this->post_type,
			'numberposts' => - 1,
		] );

		shuffle( $posts );

		return array_slice( $posts, 0, 4 );
	}
}
