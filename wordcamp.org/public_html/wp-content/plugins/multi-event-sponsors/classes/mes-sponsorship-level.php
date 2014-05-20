<?php

/*
 * Register the Sponsorship Levels custom post type and manage all of its functionality
 *
 * See note in MES_Sponsor class documentation on the use of custom post types and taxonomies for regions and
 * sponsorship levels.
 */

class MES_Sponsorship_Level {
	const POST_TYPE_SLUG = 'mes-sponsor-level';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',        array( $this, 'create_post_type' ) );
		add_action( 'admin_init',  array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post',   array( $this, 'save_post' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'add_header_footer_text' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function create_post_type() {
		if ( post_type_exists( self::POST_TYPE_SLUG ) ) {
			return;
		}

		$labels = array(
			'name'               => __( 'Sponsorship Levels',                   'wordcamporg' ),
			'singular_name'      => __( 'Sponsorship Level',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                              'wordcamporg' ),
			'add_new_item'       => __( 'Add New Sponsorship Level',            'wordcamporg' ),
			'edit'               => __( 'Edit',                                 'wordcamporg' ),
			'edit_item'          => __( 'Edit Sponsorship Level',               'wordcamporg' ),
			'new_item'           => __( 'New Sponsorship Level',                'wordcamporg' ),
			'view'               => __( 'View Sponsorship Levels',              'wordcamporg' ),
			'view_item'          => __( 'View Sponsorship Level',               'wordcamporg' ),
			'search_items'       => __( 'Search Sponsorship Levels',            'wordcamporg' ),
			'not_found'          => __( 'No Sponsorship Levels found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No Sponsorship Levels found in Trash', 'wordcamporg' ),
			'parent'             => __( 'Parent Sponsorship Level',             'wordcamporg' ),
		);

		$post_type_params = array(
			'labels'          => $labels,
			'singular_label'  => __( 'Sponsorship Level', 'wordcamporg' ),
			'public'          => true,
			'show_in_menu'    => 'edit.php?post_type=' . MES_Sponsor::POST_TYPE_SLUG,
			'menu_position'   => 20,
			'hierarchical'    => false,
			'capability_type' => 'page',
			'has_archive'     => true,
			'rewrite'         => array( 'slug' => 'sponsorship-level', 'with_front' => false ),
			'query_var'       => true,
			'supports'        => array( 'title', 'editor', 'author', 'revisions' ),
			'taxonomies'      => array( MES_Sponsor::REGIONS_SLUG ),
		);

		register_post_type( self::POST_TYPE_SLUG, $post_type_params );
	}

	/**
	 * Adds meta boxes for the custom post type
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'mes_contribution_per_attendee',
			__( 'Contribution Per Attendee', 'wordcamporg' ),
			array( $this, 'markup_meta_boxes' ),
			self::POST_TYPE_SLUG,
			'side',
			'low'
		);
	}

	/**
	 * Builds the markup for all meta boxes
	 *
	 * @param WP_Post $post
	 * @param array   $box
	 */
	public function markup_meta_boxes( $post, $box ) {
		/** @var $view string */

		switch ( $box['id'] ) {
			case 'mes_contribution_per_attendee':
				$contribution_per_attendee = (float) get_post_meta( $post->ID, 'mes_contribution_per_attendee', true );
				$view                      = 'metabox-contribution-per-attendee.php';
				break;
		}

		require_once( dirname( __DIR__ ) . '/views/'. $view );
	}

	/**
	 * Save the post data
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
		if ( isset( $new_values[ 'mes_contribution_per_attendee' ] ) ) {
			update_post_meta( $post_id, 'mes_contribution_per_attendee', (float) $new_values[ 'mes_contribution_per_attendee' ] );
		} else {
			delete_post_meta( $post_id, 'mes_contribution_per_attendee' );
		}
	}

	/**
	 * Add the header and footer copy to the sponsorship level content
	 *
	 * @param string $content
	 * @return string
	 */
	public function add_header_footer_text( $content ) {
		global $post;

		if ( ! empty ( $post->post_type ) && self::POST_TYPE_SLUG == $post->post_type ) {
			$content = sprintf(
				'<p>The sponsorship package associated with the %s sponsorship is:</p>
				<div>%s</div>
				<p>A %s contributes US $%s per attendee, rounded up to the nearest 50.</p>',
				$post->post_title,
				$content,
				$post->post_title,
				number_format_i18n( (float) get_post_meta( $post->ID, 'mes_contribution_per_attendee', true ), 2 )
			);
		}

		return $content;
	}
} // end MES_Sponsorship_Level
