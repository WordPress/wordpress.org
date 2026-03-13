<?php
/**
 * Import plugins from WordPress.org for local development.
 *
 * Fetches plugin slugs from featured, popular, and beta browse views,
 * imports full data for each using the raw API context, and tags them
 * with the appropriate section terms.
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
$base_url        = 'https://wordpress.org/plugins/wp-json/plugins/v1';
$browse_sections = array( 'featured', 'popular', 'beta' );

update_option( 'blogname', 'Plugin Directory' );

/**
 * Fetch slugs for a given browse section.
 */
function fetch_slugs( $base_url, $section, $count ) {
	$slugs = array();
	$page  = 1;

	while ( count( $slugs ) < $count ) {
		$response = wp_remote_get( "{$base_url}/query-plugins/?browse={$section}&page={$page}" );
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
 * Import a single plugin using the raw API context.
 */
function import_plugin( $base_url, $slug, $existing_post = null ) {
	$response = wp_remote_get( "{$base_url}/plugin/{$slug}?context=raw" );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['post']['post_name'] ) ) {
		return null;
	}

	$raw_post = $data['post'];
	$raw_meta = $data['meta'] ?? [];
	$raw_tax  = $data['taxonomies'] ?? [];

	// Ensure the plugin author user exists.
	$author_id = 0;
	if ( ! empty( $raw_post['post_author']['user_nicename'] ) ) {
		$author_slug = $raw_post['post_author']['user_nicename'];
		ensure_user( $author_slug, $raw_post['post_author']['display_name'] ?? '' );
		$author_user = get_user_by( 'slug', $author_slug );
		if ( $author_user ) {
			$author_id = $author_user->ID;
		}
	}

	// Build the post args directly from raw data.
	$post_args = array(
		'post_title'   => $raw_post['post_title'],
		'post_name'    => $raw_post['post_name'],
		'post_status'  => 'publish',
		'post_content' => $raw_post['post_content'],
		'post_excerpt' => $raw_post['post_excerpt'],
		'post_date'    => $raw_post['post_date'],
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

	// Store all meta values directly — no conversion needed.
	foreach ( $raw_meta as $key => $value ) {
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $post->ID, $key, wp_slash( $value ) );
		}
	}

	// Taxonomies.
	foreach ( $raw_tax as $taxonomy => $terms ) {
		if ( 'plugin_contributors' === $taxonomy ) {
			// Create users and grant committer access.
			$slugs = array();
			foreach ( $terms as $term ) {
				$slugs[] = $term['slug'];
				ensure_user( $term['slug'], $term['display_name'] ?? '' );
			}
			wp_set_object_terms( $post->ID, $slugs, $taxonomy );

			foreach ( $slugs as $contributor_slug ) {
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
