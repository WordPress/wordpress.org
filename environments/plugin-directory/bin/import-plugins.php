<?php
/**
 * Import plugins from WordPress.org for local development.
 *
 * Fetches plugin slugs from featured, popular, and beta browse views,
 * imports full data for each using the /wp/v2/plugin REST API endpoint,
 * and tags them with the appropriate section terms.
 */

namespace WordPressdotorg\Plugin_Directory\Env;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Skip if already imported.
if ( get_option( 'wporg_env_imported' ) ) {
	echo "Already imported, skipping.\n";
	return;
}

update_option( 'wporg_env_imported', time() );

$per_section     = 10;
$base_url        = 'https://wordpress.org/plugins/wp-json';
$browse_sections = array( 'featured', 'popular', 'beta' );

update_option( 'blogname', 'Plugin Directory' );

/**
 * Fetch slugs for a given browse section.
 */
function fetch_slugs( $base_url, $section, $count ) {
	$slugs = array();
	$page  = 1;

	while ( count( $slugs ) < $count ) {
		$response = wp_remote_get( "{$base_url}/plugins/v1/query-plugins/?browse={$section}&page={$page}" );
		if ( is_wp_error( $response ) ) {
			break;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $data->plugins ) ) {
			break;
		}

		$slugs = array_merge( $slugs, $data->plugins );
		$page++;
	}

	return array_slice( $slugs, 0, $count );
}

/**
 * Ensure a WordPress user exists for a contributor.
 *
 * @param string $nicename     User nicename/slug.
 * @param string $display_name Display name.
 */
function ensure_user( $nicename, $display_name = '' ) {
	if ( get_user_by( 'slug', $nicename ) ) {
		return;
	}

	wp_insert_user( array(
		'user_login'    => $nicename,
		'user_nicename' => $nicename,
		'user_email'    => $nicename . '@example.invalid',
		'display_name'  => $display_name ?: $nicename,
		'user_pass'     => wp_generate_password(),
		'role'          => 'subscriber',
	) );
}

/**
 * Extract taxonomy terms grouped by taxonomy from an embedded REST response.
 */
function extract_embedded_terms( $data ) {
	$terms = array();

	foreach ( $data['_embedded']['wp:term'] ?? [] as $group ) {
		foreach ( $group as $term ) {
			$terms[ $term['taxonomy'] ][] = $term;
		}
	}

	return $terms;
}

/**
 * Import a single plugin using the /wp/v2/plugin endpoint.
 */
function import_plugin( $base_url, $slug, $existing_post = null ) {
	$response = wp_remote_get( "{$base_url}/wp/v2/plugin?slug={$slug}&_embed" );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$results = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $results[0]['slug'] ) ) {
		return null;
	}

	$data       = $results[0];
	$meta       = $data['meta'] ?? [];
	$taxonomies = extract_embedded_terms( $data );

	// Ensure the plugin author user exists.
	$author_id  = 0;
	$author     = $data['_embedded']['author'][0] ?? [];
	if ( ! empty( $author['slug'] ) ) {
		ensure_user( $author['slug'], $author['name'] ?? '' );
		$author_user = get_user_by( 'slug', $author['slug'] );
		if ( $author_user ) {
			$author_id = $author_user->ID;
		}
	}

	// Build the post args from the REST API response.
	// The plugin post type doesn't support 'title', so use header_name meta instead.
	$post_args = array(
		'post_title'   => $meta['header_name'] ?? $data['slug'],
		'post_name'    => $data['slug'],
		'post_status'  => 'publish',
		'post_content' => $data['raw_content'] ?? '',
		'post_excerpt' => $data['raw_excerpt'] ?? '',
		'post_date'    => $data['date'] ?? '',
	);

	if ( $author_id ) {
		$post_args['post_author'] = $author_id;
	}

	if ( $existing_post ) {
		$post_args['ID'] = $existing_post->ID;
		wp_update_post( $post_args );
		$post = get_post( $existing_post->ID );
	} else {
		$post = Plugin_Directory::create_plugin_post( $post_args );
	}

	if ( is_wp_error( $post ) || ! $post ) {
		return null;
	}

	// Store meta values from the standard REST meta object.
	foreach ( $meta as $key => $value ) {
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $post->ID, $key, wp_slash( $value ) );
		}
	}

	// Taxonomies from embedded terms.
	foreach ( $taxonomies as $taxonomy => $terms ) {
		if ( 'plugin_contributors' === $taxonomy ) {
			// Create users and grant committer access.
			$contributor_slugs = array();
			foreach ( $terms as $term ) {
				$contributor_slugs[] = $term['slug'];
				ensure_user( $term['slug'], $term['display_name'] ?? $term['name'] ?? '' );
			}
			wp_set_object_terms( $post->ID, $contributor_slugs, $taxonomy );

			foreach ( $contributor_slugs as $contributor_slug ) {
				Tools::grant_plugin_committer( $post, $contributor_slug );
			}
		} else {
			$term_slugs = wp_list_pluck( $terms, 'slug' );
			wp_set_object_terms( $post->ID, $term_slugs, $taxonomy );
		}
	}

	return $post;
}

// Main loop.
foreach ( $browse_sections as $section ) {
	echo "Fetching {$per_section} plugins from '{$section}'...\n";

	$slugs = fetch_slugs( $base_url, $section, $per_section );
	echo "  Found " . count( $slugs ) . " slugs.\n";

	$imported = 0;
	foreach ( $slugs as $slug ) {
		$existing = get_posts( array(
			'post_type'   => 'plugin',
			'name'        => $slug,
			'post_status' => 'any',
			'numberposts' => 1,
		) );

		$post = import_plugin( $base_url, $slug, $existing[0] ?? null );
		if ( ! $post ) {
			continue;
		}

		wp_set_object_terms( $post->ID, $section, 'plugin_section', true );
		$action = $existing ? 'Updated' : 'Imported';
		echo "    {$action}: {$post->post_title} ({$slug})\n";
		$imported++;
	}

	echo "  {$section}: {$imported} plugins imported/updated.\n\n";
}

// Flush rewrite rules to generate .htaccess.
flush_rewrite_rules();

echo "Done!\n";
