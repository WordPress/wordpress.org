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
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Start initialize without prompting for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     # Initialize site.
	 *     $ wp photos init
	 *
	 * @when after_wp_load
	 */
	public function init( $args, $assoc_args ) {
		$force = $assoc_args['force'] ?? false;

		if ( ! $force ) {
			WP_CLI::confirm( 'Continue with site initialization (to include creation of pages, taxonomy terms, and more)?' );
		}

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

	/**
	 * Points scaled-down photos back at their original files.
	 *
	 * Photos uploaded while a lower 'big_image_size_threshold' was in effect
	 * have a "-scaled" copy as their attached file, so their "Original Size"
	 * download served the copy. The original file is still stored next to it,
	 * and the "-scaled" copy is left in place.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List the photos that would be updated without changing them.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview which photos would be updated.
	 *     $ wp photos restore-originals --dry-run
	 *
	 *     # Update them.
	 *     $ wp photos restore-originals
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @subcommand restore-originals
	 * @when after_wp_load
	 */
	public function restore_originals( $args, $assoc_args ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$dry_run   = $assoc_args['dry-run'] ?? false;
		$threshold = Uploads::big_image_size_threshold( 0 );
		$updated   = 0;

		$attachment_ids = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => '_wp_attachment_metadata',
						'value'   => 'original_image',
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $attachment_ids as $attachment_id ) {
			$parent = get_post_parent( $attachment_id );
			if ( ! $parent || Registrations::get_post_type() !== $parent->post_type ) {
				continue;
			}

			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( empty( $meta['original_image'] ) || empty( $meta['file'] ) ) {
				continue;
			}

			// Core also sets 'original_image' for copies that were only EXIF-rotated.
			if ( ! str_contains( wp_basename( $meta['file'] ), '-scaled.' ) ) {
				continue;
			}

			$original_path = wp_get_original_image_path( $attachment_id );
			$image_size    = $original_path ? wp_getimagesize( $original_path ) : false;

			if ( ! $image_size ) {
				WP_CLI::warning( "Photo {$parent->ID}: original file {$meta['original_image']} could not be read." );
				continue;
			}

			// Still above the current threshold, so scaling it was intended.
			if ( max( $image_size[0], $image_size[1] ) > $threshold ) {
				continue;
			}

			// Core rotates the copy per the original's EXIF orientation, but stores the original unrotated.
			$exif = wp_read_image_metadata( $original_path );
			if ( ( $exif['orientation'] ?? 0 ) > 1 || ( $meta['width'] > $meta['height'] ) !== ( $image_size[0] > $image_size[1] ) ) {
				WP_CLI::warning( "Photo {$parent->ID}: {$meta['file']} was rotated from its original, skipping." );
				continue;
			}

			$file = path_join( dirname( $meta['file'] ), $meta['original_image'] );

			WP_CLI::log( "Photo {$parent->ID}: {$meta['file']} ({$meta['width']}x{$meta['height']}) -> {$file} ({$image_size[0]}x{$image_size[1]})" );
			++$updated;

			if ( $dry_run ) {
				continue;
			}

			update_attached_file( $attachment_id, $file );

			// Keep the "-scaled" copy tracked so deleting the photo deletes it too.
			$meta['sizes']['scaled'] = [
				'file'      => wp_basename( $meta['file'] ),
				'width'     => $meta['width'],
				'height'    => $meta['height'],
				'mime-type' => get_post_mime_type( $attachment_id ),
			];

			$meta['file']     = $file;
			$meta['width']    = $image_size[0];
			$meta['height']   = $image_size[1];
			$meta['filesize'] = wp_filesize( $original_path );
			unset( $meta['original_image'] );

			if ( isset( $meta['gcs_name'] ) ) {
				$meta['gcs_name'] = $file;
			}
			if ( isset( $meta['gcs_url'] ) ) {
				$meta['gcs_url'] = wp_get_attachment_url( $attachment_id );
			}

			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		if ( $dry_run ) {
			WP_CLI::success( "{$updated} photo(s) would be updated." );
		} else {
			WP_CLI::success( "{$updated} photo(s) updated." );
		}
	}

}

WP_CLI::add_command( 'photos', 'WordPressdotorg\Photo_Directory\CLI' );
