<?php

/*
 * Register the Regions taxonomy and manage all of its related functionality
 *
 * See note in MES_Sponsor class documentation on the use of custom post types and taxonomies for regions and
 * sponsorship levels.
 */

class MES_Region {
	const TAXONOMY_SLUG = 'mes-regions';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',               array( $this, 'create_taxonomy' ) );
		add_action( 'add_meta_boxes' ,    array( $this, 'remove_meta_box' ),             10, 2 );
		add_action( 'wcpt_metabox_value', array( $this, 'wcpt_region_dropdown_render' ), 10, 3 );
		add_action( 'wcpt_metabox_save',  array( $this, 'wcpt_region_dropdown_save' ),   10, 3 );
	}

	/**
	 * Registers the regions taxonomy
	 */
	public function create_taxonomy() {
		$region_params = array(
			'label'                 => __( 'Region', 'wordcamporg' ),
			'labels'                => array(
				'name'          => __( 'Regions', 'wordcamporg' ),
				'singular_name' => __( 'Region', 'wordcamporg' )
			),
			'hierarchical'          => true,
			'rewrite'               => array( 'slug' => self::TAXONOMY_SLUG ),
		);

		if ( ! taxonomy_exists( self::TAXONOMY_SLUG ) ) {
			register_taxonomy( self::TAXONOMY_SLUG, MES_Sponsor::POST_TYPE_SLUG, $region_params );
		}
	}

	/**
	 * Remove the region meta box from the post types it's registered with, since the terms aren't assigned to
	 * them directly.
	 *
	 * @param string  $post_type
	 * @param WP_Post $post
	 */
	public function remove_meta_box( $post_type, $post ) {
		$registered_post_types = array(
			MES_Sponsor::POST_TYPE_SLUG,
			MES_Sponsorship_Level::POST_TYPE_SLUG
		);

		if ( ! in_array( $post_type, $registered_post_types ) ) {
			return;
		}

		remove_meta_box( 'mes-regionsdiv', null, 'side' );
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

		$regions         = get_terms( self::TAXONOMY_SLUG, array( 'hide_empty' => false ) );
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
} // end MES_Region
