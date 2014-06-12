<?php

/*
 * Register the Multi-Event Sponsors custom post type and manage all of its functionality
 *
 * The use of custom post types and taxonomies for the regions and sponsorships levels is a little questionable,
 * since neither fits perfectly. In an abstract sense, they're taxonomies, but they map between terms for a given post,
 * rather than mapping terms to posts directly. Also, the sponsorship levels have extra meta data associated with them.
 *
 * So, a custom taxonomy is used for regions, and a custom post type is used for sponsorship levels, and then meta data
 * is used on each sponsor post to map each region to a sponsorship level.
 */

class MES_Sponsor {
	const POST_TYPE_SLUG     = 'mes';
	const REGIONS_SLUG       = 'mes-regions';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',                                   array( $this, 'create_post_type' ) );
		add_action( 'init',                                   array( $this, 'create_taxonomies' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE_SLUG, array( $this, 'remove_meta_boxes' ) );
		add_action( 'admin_init',                             array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post',                              array( $this, 'save_post' ), 10, 2 );
		add_action( 'wcpt_metabox_value',                     array( $this, 'wcpt_region_dropdown_render' ), 10, 3 );
		add_action( 'wcpt_metabox_save',                      array( $this, 'wcpt_region_dropdown_save' ), 10, 3 );
		add_filter( 'the_content',                            array( $this, 'add_header_footer_text' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function create_post_type() {
		if ( post_type_exists( self::POST_TYPE_SLUG ) ) {
			return;
		}

		$labels = array(
			'name'               => __( 'Multi-Event Sponsors',                   'wordcamporg' ),
			'singular_name'      => __( 'Multi-Event Sponsor',                    'wordcamporg' ),
			'add_new'            => __( 'Add New',                                'wordcamporg' ),
			'add_new_item'       => __( 'Add New Multi-Event Sponsor',            'wordcamporg' ),
			'edit'               => __( 'Edit',                                   'wordcamporg' ),
			'edit_item'          => __( 'Edit Multi-Event Sponsor',               'wordcamporg' ),
			'new_item'           => __( 'New Multi-Event Sponsor',                'wordcamporg' ),
			'view'               => __( 'View Multi-Event Sponsors',              'wordcamporg' ),
			'view_item'          => __( 'View Multi-Event Sponsor',               'wordcamporg' ),
			'search_items'       => __( 'Search Multi-Event Sponsors',            'wordcamporg' ),
			'not_found'          => __( 'No Multi-Event Sponsors found',          'wordcamporg' ),
			'not_found_in_trash' => __( 'No Multi-Event Sponsors found in Trash', 'wordcamporg' ),
			'parent'             => __( 'Parent Multi-Event Sponsor',             'wordcamporg' ),
		);

		$post_type_params = array(
			'labels'          => $labels,
			'singular_label'  => __( 'Multi-Event Sponsor', 'wordcamporg' ),
			'public'          => true,
			'menu_position'   => 20,
			'hierarchical'    => false,
			'capability_type' => 'page',
			'has_archive'     => true,
			'rewrite'         => array( 'slug' => 'multi-event-sponsor', 'with_front' => false ),
			'query_var'       => true,
			'supports'        => array( 'title', 'editor', 'author', 'revisions', 'thumbnail' ),
			'taxonomies'      => array( self::REGIONS_SLUG ),
		);

		register_post_type( self::POST_TYPE_SLUG, $post_type_params );
	}

	/**
	 * Registers the category taxonomy
	 */
	public function create_taxonomies() {
		$region_params = array(
			'label'                 => __( 'Region', 'wordcamporg' ),
			'labels'                => array(
				'name'          => __( 'Regions', 'wordcamporg' ),
				'singular_name' => __( 'Region', 'wordcamporg' )
			),
			'hierarchical'          => true,
			'rewrite'               => array( 'slug' => self::REGIONS_SLUG ),
		);

		if ( ! taxonomy_exists( self::REGIONS_SLUG ) ) {
			register_taxonomy( self::REGIONS_SLUG, self::POST_TYPE_SLUG, $region_params );
		}
	}

	/**
	 * Adds meta boxes for the custom post type
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'mes_regional_sponsorships',
			__( 'Regional Sponsorships', 'wordcamporg' ),
			array( $this, 'markup_meta_boxes' ),
			self::POST_TYPE_SLUG,
			'normal',
			'core'
		);

		add_meta_box(
			'mes_contact_information',
			__( 'Contact Information', 'wordcamporg' ),
			array( $this, 'markup_meta_boxes' ),
			self::POST_TYPE_SLUG,
			'normal',
			'core'
		);
	}

	/**
	 * Remove the taxonomy meta boxes, since we don't assign them directly.
	 *
	 * @param WP_Post $post
	 */
	public function remove_meta_boxes( $post ) {
		remove_meta_box( 'mes-regionsdiv', null, 'side' );
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
			case 'mes_regional_sponsorships':
				$regions               = get_terms( self::REGIONS_SLUG, array( 'hide_empty' => false ) );
				$sponsorship_levels    = get_posts( array( 'post_type' => MES_Sponsorship_Level::POST_TYPE_SLUG, 'numberposts' => -1 ) );
				$regional_sponsorships = $this->populate_default_regional_sponsorships( get_post_meta( $post->ID, 'mes_regional_sponsorships', true ), $regions );
				$view                  = 'metabox-regional-sponsorships.php';
				break;

			case 'mes_contact_information':
				$first_name    = get_post_meta( $post->ID, 'mes_first_name', true );
				$last_name     = get_post_meta( $post->ID, 'mes_last_name', true );
				$email_address = get_post_meta( $post->ID, 'mes_email_address', true );
				$view          = 'metabox-contact-information.php';
				break;
		}

		require_once( dirname( __DIR__ ) . '/views/'. $view );
	}

	/**
	 * Populate the regional sponsorships array with default values.
	 *
	 * This helps to avoid any PHP notices from trying to access undefined indices.
	 *
	 * @param array $regional_sponsorships
	 * @param array $regions
	 * @return array
	 */
	protected function populate_default_regional_sponsorships( $regional_sponsorships, $regions ) {
		$region_ids = wp_list_pluck( $regions, 'term_id' );

		foreach ( $region_ids as $region_id ) {
			if ( empty ( $regional_sponsorships[ $region_id ] ) ) {
				$regional_sponsorships[ $region_id ] = 'null';
			}
		}

		return $regional_sponsorships;
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
		if ( isset( $new_values[ 'mes_regional_sponsorships' ] ) ) {
			array_walk( $new_values[ 'mes_regional_sponsorships' ], 'absint' );
			update_post_meta( $post_id, 'mes_regional_sponsorships', $new_values[ 'mes_regional_sponsorships' ] );
		} else {
			delete_post_meta( $post_id, 'mes_regional_sponsorships' );
		}

		if ( isset( $new_values[ 'mes_first_name' ] ) ) {
			update_post_meta( $post_id, 'mes_first_name', sanitize_text_field( $new_values[ 'mes_first_name' ] ) );
		} else {
			delete_post_meta( $post_id, 'mes_first_name' );
		}

		if ( isset( $new_values[ 'mes_last_name' ] ) ) {
			update_post_meta( $post_id, 'mes_last_name', sanitize_text_field( $new_values[ 'mes_last_name' ] ) );
		} else {
			delete_post_meta( $post_id, 'mes_last_name' );
		}

		if ( isset( $new_values[ 'mes_email_address' ] ) ) {
			if ( is_email( $new_values[ 'mes_email_address' ] ) ) {
				update_post_meta( $post_id, 'mes_email_address', sanitize_text_field( $new_values[ 'mes_email_address' ] ) );
			}
		} else {
			delete_post_meta( $post_id, 'mes_email_address' );
		}
	}

	/**
	 * Render the dropdown element with regions for the WordCamp Post Type plugin
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $field_name
	 */
	public function wcpt_region_dropdown_render( $key, $value, $field_name ) {
		if ( 'Multi-Event Sponsor Region' != $key ) {
			return;
		}

		global $post;

		$regions         = get_terms( self::REGIONS_SLUG, array( 'hide_empty' => false ) );
		$selected_region = get_post_meta( $post->ID, $key, true );

		require( dirname( __DIR__ ) . '/views/template-region-dropdown.php' );
	}

	/**
	 * Save the dropdown element with regions for the WordCamp Post Type plugin
	 *
	 * @param string $key
	 * @param string $value
	 * @param int    $post_id
	 */
	public function wcpt_region_dropdown_save( $key, $value, $post_id ) {
		if ( 'Multi-Event Sponsor Region' != $key ) {
			return;
		}

		$post_key = wcpt_key_to_str( $key, 'wcpt_' );
		if ( isset( $_POST[ $post_key ] ) ) {
			update_post_meta( $post_id, $key, absint( $_POST[ $post_key ] ) );
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
				'<p>Here’s the company description for %s for your website:</p>
				<div>%s</div>',
				$post->post_title,
				$content
			);
		}

		return $content;
	}
} // end MES_Sponsor
