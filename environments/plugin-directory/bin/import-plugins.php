<?php
/**
 * Import plugins from WordPress.org for local development.
 *
 * Fetches plugin slugs from featured, popular, and beta browse views,
 * imports full data for each, and tags them with the appropriate section terms.
 */

namespace WordPressdotorg\Plugin_Directory\Env;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

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

// Ensure browse section terms exist (ProperCase).
foreach ( array( 'featured', 'popular', 'beta', 'blocks', 'new', 'favorites' ) as $section ) {
	if ( ! term_exists( $section, 'plugin_section' ) ) {
		wp_insert_term( ucwords( $section ), 'plugin_section', array( 'slug' => $section ) );
	}
}

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
 * @param string        $nicename User nicename/slug.
 * @param object|string $data     Contributor data from the API.
 */
function ensure_user( $nicename, $data ) {
	if ( get_user_by( 'slug', $nicename ) ) {
		return;
	}

	$display_name = is_object( $data ) && ! empty( $data->display_name )
		? $data->display_name
		: $nicename;

	wp_insert_user( array(
		'user_login'    => $nicename,
		'user_nicename' => $nicename,
		'display_name'  => $display_name,
		'user_pass'     => wp_generate_password(),
		'role'          => 'subscriber',
	) );
}

/**
 * Convert API icon URLs to the internal assets_icons format.
 */
function convert_icons( $api_icons ) {
	$resolution_map = array(
		'1x' => '128x128',
		'2x' => '256x256',
		'svg' => false,
	);

	return convert_assets( (array) $api_icons, $resolution_map );
}

/**
 * Convert API banner URLs to the internal assets_banners format.
 */
function convert_banners( $api_banners ) {
	$resolution_map = array(
		'low'  => '772x250',
		'high' => '1544x500',
	);

	return convert_assets( (array) $api_banners, $resolution_map );
}

/**
 * Convert API asset URLs to the internal asset array format.
 *
 * @param array $api_assets     Key => URL pairs from the API.
 * @param array $resolution_map Key => resolution string map.
 * @return array Internal asset entries.
 */
function convert_assets( $api_assets, $resolution_map ) {
	$assets = array();

	foreach ( $api_assets as $key => $url ) {
		if ( 'default' === $key || empty( $url ) ) {
			continue;
		}

		$parsed   = wp_parse_url( $url );
		$filename = basename( $parsed['path'] ?? '' );
		$rev      = '';

		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query_args );
			$rev = $query_args['rev'] ?? '';
		}

		$resolution = $resolution_map[ $key ] ?? '';
		if ( ! $resolution && preg_match( '/(\d+x\d+)/', $filename, $m ) ) {
			$resolution = $m[1];
		}

		$assets[] = array(
			'filename'   => $filename,
			'resolution' => $resolution ?: '',
			'locale'     => '',
			'revision'   => $rev,
			'location'   => '',
		);
	}

	return $assets;
}

/**
 * Import a single plugin and return the post, or null if skipped/failed.
 */
function import_plugin( $base_url, $slug ) {
	$response = wp_remote_get( "{$base_url}/plugin/{$slug}" );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$plugin = json_decode( wp_remote_retrieve_body( $response ) );
	if ( empty( $plugin->slug ) ) {
		return null;
	}

	// Ensure the plugin author user exists.
	$author_id = 0;
	if ( ! empty( $plugin->author_profile ) ) {
		$author_slug = trim( wp_parse_url( $plugin->author_profile, PHP_URL_PATH ), '/' );
		if ( $author_slug ) {
			ensure_user( $author_slug, (object) array( 'display_name' => wp_strip_all_tags( $plugin->author ?? $author_slug ) ) );
			$author_user = get_user_by( 'slug', $author_slug );
			if ( $author_user ) {
				$author_id = $author_user->ID;
			}
		}
	}

	// Build post_content from sections.
	$description = '';
	if ( ! empty( $plugin->sections ) ) {
		foreach ( $plugin->sections as $section_name => $section_content ) {
			$description .= "<!--section={$section_name}-->\n{$section_content}\n<!--/section-->\n\n";
		}
	}

	$post_args = array(
		'post_title'   => $plugin->name,
		'post_name'    => $plugin->slug,
		'post_status'  => 'publish',
		'post_content' => $description,
		'post_excerpt' => $plugin->short_description ?? '',
		'post_date'    => ! empty( $plugin->added ) ? $plugin->added . ' 00:00:00' : null,
	);

	if ( $author_id ) {
		$post_args['post_author'] = $author_id;
	}

	$post = Plugin_Directory::create_plugin_post( $post_args );

	if ( is_wp_error( $post ) || ! $post ) {
		return null;
	}

	// Post meta.
	$meta = array(
		'version'                  => $plugin->version ?? '',
		'stable_tag'               => $plugin->stable_tag ?? $plugin->version ?? 'trunk',
		'tested'                   => $plugin->tested ?? '',
		'requires'                 => $plugin->requires ?? '',
		'requires_php'             => $plugin->requires_php ?? '',
		'active_installs'          => $plugin->active_installs ?? 0,
		'_active_installs'         => $plugin->active_installs ?? 0,
		'downloads'                => $plugin->downloaded ?? 0,
		'rating'                   => isset( $plugin->rating ) ? ( $plugin->rating / 20 ) : 0,
		'num_ratings'              => $plugin->num_ratings ?? 0,
		'support_threads'          => $plugin->support_threads ?? 0,
		'support_threads_resolved' => $plugin->support_threads_resolved ?? 0,
		'donate_link'              => $plugin->donate_link ?? '',
		'header_author'            => wp_strip_all_tags( $plugin->author ?? '' ),
		'header_plugin_uri'        => $plugin->homepage ?? '',
	);

	foreach ( $meta as $key => $value ) {
		if ( $value !== '' && $value !== null ) {
			update_post_meta( $post->ID, $key, $value );
		}
	}

	if ( ! empty( $plugin->ratings ) ) {
		update_post_meta( $post->ID, 'ratings', (array) $plugin->ratings );
	}

	if ( ! empty( $plugin->icons ) ) {
		update_post_meta( $post->ID, 'assets_icons', convert_icons( $plugin->icons ) );
	}

	if ( ! empty( $plugin->banners ) ) {
		update_post_meta( $post->ID, 'assets_banners', convert_banners( $plugin->banners ) );
	}

	if ( ! empty( $plugin->screenshots ) ) {
		update_post_meta( $post->ID, 'screenshots', (array) $plugin->screenshots );
	}

	if ( ! empty( $plugin->requires_plugins ) ) {
		update_post_meta( $post->ID, 'requires_plugins', $plugin->requires_plugins );
	}

	if ( ! empty( $plugin->blocks ) ) {
		update_post_meta( $post->ID, 'all_blocks', (array) $plugin->blocks );
	}

	// Tags (ProperCase).
	if ( ! empty( $plugin->tags ) ) {
		$tags = array_map( 'ucwords', array_values( (array) $plugin->tags ) );
		wp_set_object_terms( $post->ID, $tags, 'plugin_tags' );
	}

	// Contributors — create users and set terms.
	if ( ! empty( $plugin->contributors ) ) {
		$contributor_slugs = array();
		foreach ( (array) $plugin->contributors as $nicename => $data ) {
			$contributor_slugs[] = $nicename;
			ensure_user( $nicename, $data );
		}
		wp_set_object_terms( $post->ID, $contributor_slugs, 'plugin_contributors' );
	}

	// Business model (ProperCase).
	if ( ! empty( $plugin->business_model ) ) {
		wp_set_object_terms( $post->ID, ucwords( $plugin->business_model ), 'plugin_business_model' );
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

		if ( $existing ) {
			wp_set_object_terms( $existing[0]->ID, $section, 'plugin_section', true );
			continue;
		}

		$post = import_plugin( $base_url, $slug );
		if ( ! $post ) {
			continue;
		}

		wp_set_object_terms( $post->ID, $section, 'plugin_section', true );
		echo "    Imported: {$post->post_title} ({$slug})\n";
		$imported++;
	}

	echo "  {$section}: {$imported} new plugins imported.\n\n";
}

// Flush rewrite rules to generate .htaccess.
flush_rewrite_rules();

echo "Done!\n";
