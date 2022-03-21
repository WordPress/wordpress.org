<?php
/**
 * WP-CLI commands.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

use WP_CLI;

class CLI extends \WP_CLI_Command {

	/**
	 * Initializes the state of the site.
	 *
	 * Creates:
	 * - Pages (c, color, submit)
	 * - Taxonomy terms (categories, colors, orientations)
	 *
	 * ## EXAMPLES
	 *
	 *     # Initialize site.
	 *     $ wp photos init
	 *
	 * @when after_wp_load
	 */
	public function init( $args, $assoc_args ) {
		WP_CLI::confirm( 'Continue with site initialization (to include creation of pages, taxonomy terms, and more)?' );

		// Create pages.
		$pages_to_create = [
			'c' => [
				'post_title' => __( 'Categories', 'wporg-photos' ),
			],
			'color' => [
				'post_title' => __( 'Colors', 'wporg-photos' ),
			],
			'faq' => [
				'post_title' => __( 'Frequently Asked Questions', 'wporg-photos' ),
			],
			'guidelines' => [
				'post_title' => __( 'Guidelines', 'wporg-photos' ),
			],
			'license' => [
				'post_title' => __( 'License', 'wporg-photos' ),
			],
			'orientation' => [
				'post_title' => __( 'Orientations', 'wporg-photos' ),
			],
			'submit' => [
				'post_title' => __( 'Submit Your Photo', 'wporg-photos' ),
			],
		];
		$post_type =  'page';

		WP_CLI::log( "Creating pages..." );

		foreach ( $pages_to_create as $path => $data ) {
			$exists =  get_page_by_path( $path, OBJECT, $post_type );
			if ( $exists ) {
				WP_CLI::log( "\tPage '{$path}' already exists." );
			} else {
				WP_CLI::log( "\tCreating page '{$path}'..." );
				$slug_parts = explode( '/', $path );
				$slug = array_pop( $slug_parts );
				$default_attributes = [
					'post_name'   => str_replace( '-', ' & ', $slug ),
					'post_status' => 'publish',
					'post_type'   => $post_type,
				];
				$attribs = wp_parse_args( $data, $default_attributes );

				// Set title if not explicitly set.
				if ( empty( $attribs['post_title'] ) ) {
					$attribs['post_title'] = ucwords( $slug );
				}

				// Determine post parent if necessary.
				if ( $slug_parts || isset( $attribs['post_parent'] ) ) {
					if ( isset( $attribs['post_parent'] ) ) {
						$parent = get_post( [ 'post_name' => $attribs['post_parent'], 'post_type' => $post_type ] );
						$parent = $parent ? $parent[0] : [];
					} else {
						$parent_path = implode( '/', $slug_parts );
						$parent = get_page_by_path( $parent_path, OBJECT, $post_type );
					}
					if ( $parent ) {
						$attribs['post_parent'] = $parent->ID;
					}
				}

				$attribs_str = '';
				foreach ( $attribs as $key => $val ) {
					$attribs_str .= "--{$key}='{$val}' ";
				}

				WP_CLI::runcommand( 'post create ' . trim( $attribs_str ) );
			}
		}

		// Create categories.
		$tax_cats = Registrations::get_taxonomy( 'categories' );
		$cats = array_keys( Photo::TAGS_TO_CATEGORY );

		WP_CLI::log( "Creating category terms for taxonomy {$tax_cats}..." );

		foreach ( $cats as $cat ) {
			$exists = get_term_by( 'slug', $cat, $tax_cats );
			if ( $exists ) {
				WP_CLI::log( "\tTerm '{$cat}' already exists." );
			} else {
				WP_CLI::log( "\tCreating category '{$cat}'..." );
				WP_CLI::runcommand( "term create {$tax_cats} {$cat}" );
			}
		}

		// Create colors.
		$tax_colors = Registrations::get_taxonomy( 'colors' );
		$colors = array_keys( ColorUtils::COLORS );

		WP_CLI::log( "Creating color terms for taxonomy {$tax_colors}..." );

		foreach ( $colors as $color ) {
			$exists = get_term_by( 'slug', $color, $tax_colors );
			if ( $exists ) {
				WP_CLI::log( "\tTerm '{$color}' already exists." );
			} else {
				WP_CLI::log( "\tCreating color '{$color}'..." );
				WP_CLI::runcommand( "term create {$tax_colors} {$color}" );
			}
		}

		// Create orientations.
		$tax_orientation = Registrations::get_taxonomy( 'orientations' );
		$orientations = [ 'landscape', 'portrait', 'square' ];

		WP_CLI::log( "Creating orientation terms for taxonomy {$tax_orientation}..." );

		foreach ( $orientations as $orientation ) {
			$exists = get_term_by( 'slug', $orientation, $tax_orientation );
			if ( $exists ) {
				WP_CLI::log( "\tTerm '{$orientation}' already exists." );
			} else {
				WP_CLI::log( "\tCreating orientation '{$orientation}'..." );
				WP_CLI::runcommand( "term create {$tax_orientation} {$orientation}" );
			}
		}
	}

}

WP_CLI::add_command( 'photos', 'WordPressdotorg\Photo_Directory\CLI' );
