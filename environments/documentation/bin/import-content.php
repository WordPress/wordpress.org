<?php
/**
 * Import Documentation content from WordPress.org for local development.
 *
 * Copies the categories, pages, articles, and WordPress version pages from the
 * live site's REST API. Posts keep their production IDs, so links between them
 * and the IDs in production URLs line up locally.
 *
 * The REST API only exposes rendered content to logged-out requests, so posts
 * are imported as rendered HTML rather than block markup.
 *
 * Usage:
 *   wp eval-file wp-content/env-bin/import-content.php --user=admin
 *
 * No strict_types declaration: eval-file evaluates the file inline, where a
 * declare() cannot be the first statement.
 *
 * @package wporg-env
 */

namespace WordPressdotorg\Documentation\Env;

use RuntimeException;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const API_BASE = 'https://wordpress.org/documentation/wp-json/wp/v2/';

/**
 * Fetches every item from a REST collection, following pagination.
 *
 * @param string $endpoint Collection route, relative to the wp/v2 namespace.
 * @return array[] Decoded items, in API order.
 *
 * @throws RuntimeException When a page of the collection cannot be fetched.
 */
function fetch_all( string $endpoint ): array {
	$items       = array();
	$page        = 1;
	$total_pages = 1;

	do {
		$url      = add_query_arg(
			array(
				'per_page' => 100,
				'page'     => $page,
			),
			API_BASE . $endpoint
		);
		$response = wp_remote_get( $url, array( 'timeout' => 60 ) );

		$batch = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $batch ) ) {
			throw new RuntimeException( esc_html( "Could not fetch {$endpoint} (page {$page}) from wordpress.org." ) );
		}

		$items       = array_merge( $items, $batch );
		$total_pages = (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' );
		++$page;
	} while ( $page <= $total_pages );

	return $items;
}

/**
 * Imports the terms of a taxonomy, parents before their children.
 *
 * @param string $endpoint Terms route, relative to the wp/v2 namespace.
 * @param string $taxonomy Local taxonomy name.
 * @return int[] Local term IDs, keyed by production term ID.
 */
function import_terms( string $endpoint, string $taxonomy ): array {
	$terms = fetch_all( $endpoint );
	$map   = array();

	while ( $terms ) {
		$deferred = array();

		foreach ( $terms as $term ) {
			$parent = (int) ( $term['parent'] ?? 0 );
			if ( $parent && ! isset( $map[ $parent ] ) ) {
				$deferred[] = $term;
				continue;
			}

			$existing = get_term_by( 'slug', $term['slug'], $taxonomy );
			if ( $existing ) {
				$map[ (int) $term['id'] ] = (int) $existing->term_id;
				continue;
			}

			$result = wp_insert_term(
				wp_specialchars_decode( $term['name'], ENT_QUOTES ),
				$taxonomy,
				array(
					'slug'        => $term['slug'],
					'description' => $term['description'] ?? '',
					'parent'      => $parent ? $map[ $parent ] : 0,
				)
			);

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( "Could not create {$taxonomy} '{$term['slug']}': " . $result->get_error_message() );

				// Its children still import, at the top level.
				$map[ (int) $term['id'] ] = 0;
				continue;
			}

			$map[ (int) $term['id'] ] = (int) $result['term_id'];
		}

		// Whatever is left has a parent the API did not return.
		if ( count( $deferred ) === count( $terms ) ) {
			WP_CLI::warning( count( $deferred ) . " {$taxonomy} terms skipped: their parents are missing." );
			break;
		}

		$terms = $deferred;
	}

	WP_CLI::log( sprintf( 'Imported %d %s terms.', count( array_filter( $map ) ), $taxonomy ) );

	return $map;
}

/**
 * Imports the posts of a post type, keeping their production IDs.
 *
 * @param string  $endpoint   Posts route, relative to the wp/v2 namespace.
 * @param string  $post_type  Local post type name.
 * @param array[] $taxonomies Term ID maps from import_terms(), keyed by the
 *                            REST field that holds the post's term IDs, e.g.
 *                            `array( 'category' => array( 'taxonomy' => 'category', 'map' => $map ) )`.
 * @return void
 */
function import_posts( string $endpoint, string $post_type, array $taxonomies = array() ): void {
	$imported = 0;

	foreach ( fetch_all( $endpoint ) as $item ) {
		if ( get_post( (int) $item['id'] ) ) {
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'import_id'     => (int) $item['id'],
				'post_type'     => $post_type,
				'post_status'   => 'publish',
				'post_title'    => html_entity_decode( $item['title']['rendered'], ENT_QUOTES, 'UTF-8' ),
				'post_name'     => $item['slug'],
				'post_content'  => $item['content']['rendered'],
				'post_excerpt'  => wp_strip_all_tags( $item['excerpt']['rendered'] ?? '' ),
				'post_date'     => $item['date'],
				'post_date_gmt' => $item['date_gmt'],
				'post_parent'   => (int) ( $item['parent'] ?? 0 ),
				'menu_order'    => (int) ( $item['menu_order'] ?? 0 ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::warning( "Could not create {$post_type} '{$item['slug']}': " . $post_id->get_error_message() );
			continue;
		}

		foreach ( $taxonomies as $field => $taxonomy ) {
			$term_ids = array_filter(
				array_map(
					static fn ( $id ): int => $taxonomy['map'][ (int) $id ] ?? 0,
					(array) ( $item[ $field ] ?? array() )
				)
			);

			if ( $term_ids ) {
				wp_set_object_terms( $post_id, array_values( $term_ids ), $taxonomy['taxonomy'] );
			}
		}

		++$imported;
	}

	WP_CLI::log( "Imported {$imported} {$post_type} posts." );
}

if ( get_option( 'wporg_docs_env_imported' ) ) {
	WP_CLI::log( 'Already imported, skipping.' );
	return;
}

WP_CLI::log( 'Importing Documentation content from WordPress.org...' );

/*
 * Only mark the import done once everything arrived. Every step skips what
 * already exists, so an interrupted import resumes on the next start.
 */
try {
	$categories = import_terms( 'category', 'category' );
	$releases   = import_terms( 'helphub_major_release', 'helphub_major_release' );

	import_posts( 'pages', 'page' );
	import_posts(
		'articles',
		'helphub_article',
		array(
			'category' => array(
				'taxonomy' => 'category',
				'map'      => $categories,
			),
		)
	);
	import_posts(
		'wordpress-versions',
		'helphub_version',
		array(
			'helphub_major_release' => array(
				'taxonomy' => 'helphub_major_release',
				'map'      => $releases,
			),
		)
	);

	update_option( 'wporg_docs_env_imported', time() );
} catch ( RuntimeException $e ) {
	WP_CLI::warning( $e->getMessage() . ' The import resumes on the next start.' );
}
