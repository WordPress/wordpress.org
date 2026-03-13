<?php
/**
 * Import plugins from WordPress.org for local development.
 *
 * Fetches plugin slugs from featured, popular, and beta browse views,
 * imports full data for each using the /wp/v2/plugin REST API endpoint,
 * and tags them with the appropriate section terms.
 *
 * Plugin data is fetched in batches to reduce HTTP requests.
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

$per_section     = 15;
$batch_size      = $per_section;
$base_url        = 'https://wordpress.org/plugins/wp-json';
$browse_sections = array( 'featured', 'popular', 'beta', 'blocks', 'new', 'updated' );

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
 * Fetch plugin data for multiple slugs in a single REST API call.
 *
 * @param string $base_url REST API base URL.
 * @param array  $slugs    Plugin slugs to fetch.
 * @return array Keyed by slug, values are REST API response arrays.
 */
function fetch_plugins_batch( $base_url, $slugs ) {
	$query = http_build_query( array(
		'slug'     => $slugs,
		'_embed'   => 1,
		'per_page' => count( $slugs ),
	) );

	$response = wp_remote_get( "{$base_url}/wp/v2/plugin?{$query}", array( 'timeout' => 60 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return array();
	}

	$results = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $results ) ) {
		return array();
	}

	$plugins = array();
	foreach ( $results as $data ) {
		if ( ! empty( $data['slug'] ) ) {
			$plugins[ $data['slug'] ] = $data;
		}
	}

	return $plugins;
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
 * Import a single plugin from pre-fetched REST API data.
 */
function save_plugin( $data, $existing_post = null ) {
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
	// Use last_updated for post_modified to match production behavior.
	$last_updated  = $meta['last_updated'] ?? '';
	$post_args = array(
		'post_title'        => $meta['header_name'] ?? $data['slug'],
		'post_name'         => $data['slug'],
		'post_status'       => 'publish',
		'post_content'      => $data['raw_content'] ?? '',
		'post_excerpt'      => $data['raw_excerpt'] ?? '',
		'post_date'         => $data['date'] ?? '',
		'post_date_gmt'     => $data['date_gmt'] ?? '',
		'post_modified'     => $last_updated ?: ( $data['date'] ?? '' ),
		'post_modified_gmt' => $last_updated ? get_gmt_from_date( $last_updated ) : ( $data['date_gmt'] ?? '' ),
	);

	if ( $author_id ) {
		$post_args['post_author'] = $author_id;
	}

	// Preserve our custom dates through wp_insert_post/wp_update_post.
	$preserve_dates = function( $data ) use ( $post_args ) {
		foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $key ) {
			if ( ! empty( $post_args[ $key ] ) ) {
				$data[ $key ] = $post_args[ $key ];
			}
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $preserve_dates );

	if ( $existing_post ) {
		$post_args['ID'] = $existing_post->ID;
		wp_update_post( $post_args );
		$post = get_post( $existing_post->ID );
	} else {
		$post = Plugin_Directory::create_plugin_post( $post_args );
	}

	remove_filter( 'wp_insert_post_data', $preserve_dates );

	if ( is_wp_error( $post ) || ! $post ) {
		return null;
	}

	// Store meta values from the standard REST meta object.
	foreach ( $meta as $key => $value ) {
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $post->ID, $key, wp_slash( $value ) );
		}
	}

	// Store underscore-prefixed meta used internally for sorting/queries.
	if ( ! empty( $meta['active_installs'] ) ) {
		update_post_meta( $post->ID, '_active_installs', (int) $meta['active_installs'] );
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

	// Fetch plugin data in batches.
	$all_plugin_data = array();
	foreach ( array_chunk( $slugs, $batch_size ) as $batch ) {
		$batch_data      = fetch_plugins_batch( $base_url, $batch );
		$all_plugin_data = array_merge( $all_plugin_data, $batch_data );

		$fetched = array_intersect_key( array_flip( $batch ), $batch_data );
		$missing = array_diff( $batch, array_keys( $batch_data ) );
		if ( $missing ) {
			echo "  Skipped (not found): " . implode( ', ', $missing ) . "\n";
		}
	}

	echo "  Fetched " . count( $all_plugin_data ) . " plugins, importing...\n";

	$imported = 0;
	foreach ( $slugs as $slug ) {
		if ( ! isset( $all_plugin_data[ $slug ] ) ) {
			continue;
		}

		echo "    {$slug}...";

		$existing = get_posts( array(
			'post_type'   => 'plugin',
			'name'        => $slug,
			'post_status' => 'any',
			'numberposts' => 1,
		) );

		$post = save_plugin( $all_plugin_data[ $slug ], $existing[0] ?? null );
		if ( ! $post ) {
			echo " failed.\n";
			continue;
		}

		wp_set_object_terms( $post->ID, $section, 'plugin_section', true );
		$action = $existing ? 'updated' : 'done';
		echo " {$post->post_title} ({$action})\n";
		$imported++;
	}

	echo "  {$section}: {$imported} plugins imported/updated.\n\n";
}

// Flush rewrite rules to generate .htaccess.
flush_rewrite_rules();

echo "Done!\n";
