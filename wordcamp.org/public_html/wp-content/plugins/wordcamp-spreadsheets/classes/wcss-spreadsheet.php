<?php

/*
 * Register the WordCamp Spreadsheets custom post type and manage all of its functionality
 */

class WCSS_Spreadsheet {
	const POST_TYPE_SLUG = 'wcss';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',        array( $this, 'create_post_type' ) );
		add_action( 'admin_init',  array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post',   array( $this, 'save_post' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'display_spreadsheet_on_front_end' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function create_post_type() {
		if ( post_type_exists( self::POST_TYPE_SLUG ) ) {
			return;
		}

		$labels = array(
			'name'               => __( 'WordCamp Spreadsheets',                   'wordcamporg' ),
			'singular_name'      => __( 'WordCamp Spreadsheet',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                                 'wordcamporg' ),
			'add_new_item'       => __( 'Add New WordCamp Spreadsheet',            'wordcamporg' ),
			'edit'               => __( 'Edit',                                    'wordcamporg' ),
			'edit_item'          => __( 'Edit WordCamp Spreadsheet',               'wordcamporg' ),
			'new_item'           => __( 'New WordCamp Spreadsheet',                'wordcamporg' ),
			'view'               => __( 'View WordCamp Spreadsheets',              'wordcamporg' ),
			'view_item'          => __( 'View WordCamp Spreadsheet',               'wordcamporg' ),
			'search_items'       => __( 'Search WordCamp Spreadsheets',            'wordcamporg' ),
			'not_found'          => __( 'No WordCamp Spreadsheets found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No WordCamp Spreadsheets found in Trash', 'wordcamporg' ),
			'parent'             => __( 'Parent WordCamp Spreadsheet',             'wordcamporg' ),
		);

		$post_type_params = array(
			'labels'          => $labels,
			'singular_label'  => __( 'WordCamp Spreadsheet', 'wordcamporg' ),
			'public'          => true,
			'menu_position'   => 20,
			'hierarchical'    => false,
			'capability_type' => 'post',
			'has_archive'     => true,
			'rewrite'         => array( 'slug' => self::POST_TYPE_SLUG ),
			'query_var'       => true,
			'supports'        => array( 'title', 'author', 'revisions' )
		);

		register_post_type( self::POST_TYPE_SLUG, $post_type_params );
	}
	
	/**
	 * Adds meta boxes for the custom post type
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wcss_editor',
			__( 'Spreadsheet', 'wordcamporg' ),
			array( $this, 'markup_meta_boxes' ),
			self::POST_TYPE_SLUG,
			'normal',
			'core'
		);
	}

	/**
	 * Builds the markup for all meta boxes
	 *
	 * @param WP_Post $post
	 * @param array   $box
	 */
	public function markup_meta_boxes( $post, $box ) {
		switch ( $box['id'] ) {
			case 'wcss_editor':
				$spreadsheet_data = get_post_meta( $post->ID, 'wcss_spreadsheet_data', true );
				$view             = 'spreadsheet-container.php';
			break;
		}

		require_once( dirname( __DIR__ ) . '/views/'. $view );
	}

	/**
	 * Save the spreadsheet data
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		$ignored_actions = array( 'trash', 'untrash', 'restore' );

		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], $ignored_actions ) ) {
			return;
		}

		if ( ! $post || $post->post_type != self::POST_TYPE_SLUG || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) {
			return;
		}

		$this->save_post_meta( $post_id, $_POST );
	}

	/**
	 * Save the post's meta fields
	 *
	 * @param int   $post_id
	 * @param array $new_values
	 */
	protected function save_post_meta( $post_id, $new_values ) {
		if ( isset( $new_values[ 'wcss_spreadsheet_data' ] ) ) {
			update_post_meta( $post_id, 'wcss_spreadsheet_data', json_decode( stripslashes( $new_values[ 'wcss_spreadsheet_data' ] ) ) );
		} else {
			delete_post_meta( $post_id, 'wcss_spreadsheet_data' );
		}
	}

	/**
	 * Renders the spreadsheet when viewing on the front end
	 *
	 * @param $content
	 * @return string
	 */
	public function display_spreadsheet_on_front_end( $content ) {
		global $post;

		if ( ! is_admin() && isset ( $post->post_type ) && self::POST_TYPE_SLUG == $post->post_type ) {
			$spreadsheet_data = get_post_meta( $post->ID, 'wcss_spreadsheet_data', true );

			ob_start();
			require_once( dirname( __DIR__ ) . '/views/spreadsheet-container.php' );
			$content = ob_get_clean();
		}

		return $content;
	}
} // end WCSS_Spreadsheet
