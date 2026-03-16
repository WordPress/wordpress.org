<?php
/**
 * Import themes from WordPress.org for local development.
 *
 * Fetches theme data from the Themes API for popular, new, and updated
 * browse views, and creates repopackage posts with the appropriate metadata.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Skip if already imported.
if ( get_option( 'wporg_themes_env_imported' ) ) {
	echo "Already imported, skipping.\n";
	return;
}

update_option( 'wporg_themes_env_imported', time() );

$per_section     = 15;
$browse_sections = array( 'popular', 'new', 'updated' );
$api_url         = 'https://api.wordpress.org/themes/info/1.2/';

update_option( 'blogname', 'Theme Directory' );
update_option( 'blogdescription', 'Free WordPress Themes' );

/**
 * Fetch themes for a given browse section from the Themes API.
 */
function fetch_themes( $api_url, $section, $count ) {
	$url = add_query_arg(
		array(
			'action'                         => 'query_themes',
			'request[browse]'                => $section,
			'request[per_page]'              => $count,
			'request[fields][description]'   => 1,
			'request[fields][tags]'          => 1,
			'request[fields][versions]'      => 1,
			'request[fields][active_installs]' => 1,
			'request[fields][downloaded]'    => 1,
			'request[fields][extended_author]' => 1,
			'request[fields][requires]'      => 1,
			'request[fields][requires_php]'  => 1,
		),
		$api_url
	);

	$response = wp_remote_get( $url, array( 'timeout' => 60 ) );
	if ( is_wp_error( $response ) ) {
		return array();
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['themes'] ) ) {
		return array();
	}

	return $data['themes'];
}

/**
 * Ensure a WordPress user exists.
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
 * Import a single theme from API data.
 */
function save_theme( $theme ) {
	// Ensure author exists.
	$author_id = 0;
	if ( ! empty( $theme['author']['user_nicename'] ) ) {
		$nicename = $theme['author']['user_nicename'];
		ensure_user( $nicename, $theme['author']['display_name'] ?? '' );
		$author_user = get_user_by( 'slug', $nicename );
		if ( $author_user ) {
			$author_id = $author_user->ID;
		}
	}

	// Check if already exists.
	$existing = get_posts( array(
		'post_type'   => 'repopackage',
		'name'        => $theme['slug'],
		'post_status' => 'any',
		'numberposts' => 1,
	) );

	$post_args = array(
		'post_type'    => 'repopackage',
		'post_title'   => $theme['name'],
		'post_name'    => $theme['slug'],
		'post_status'  => 'publish',
		'post_content' => $theme['description'] ?? '',
	);

	if ( $author_id ) {
		$post_args['post_author'] = $author_id;
	}

	if ( $existing ) {
		$post_args['ID'] = $existing[0]->ID;
		wp_update_post( $post_args );
		$post_id = $existing[0]->ID;
	} else {
		$post_id = wp_insert_post( $post_args );
	}

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return null;
	}

	// Build the _status meta (version => status map) from the versions list.
	$status = array();
	if ( ! empty( $theme['versions'] ) ) {
		foreach ( $theme['versions'] as $version => $url ) {
			$status[ $version ] = 'live';
		}
	} elseif ( ! empty( $theme['version'] ) ) {
		$status[ $theme['version'] ] = 'live';
	}
	update_post_meta( $post_id, '_status', $status );

	// Set the current live version.
	if ( ! empty( $theme['version'] ) ) {
		update_post_meta( $post_id, '_live_version', $theme['version'] );
	}

	// Store author meta per version.
	if ( ! empty( $theme['version'] ) ) {
		$version = $theme['version'];
		if ( ! empty( $theme['author']['author'] ) ) {
			update_post_meta( $post_id, "_author_{$version}", $theme['author']['author'] );
		}
		if ( ! empty( $theme['author']['author_url'] ) ) {
			update_post_meta( $post_id, "_author_url_{$version}", $theme['author']['author_url'] );
		}
	}

	// Screenshot — stored as a version-keyed array mapping version => filename.
	if ( ! empty( $theme['version'] ) ) {
		$screenshots = get_post_meta( $post_id, '_screenshot', true ) ?: array();
		if ( ! is_array( $screenshots ) ) {
			$screenshots = array();
		}
		$screenshots[ $theme['version'] ] = 'screenshot.png';
		update_post_meta( $post_id, '_screenshot', $screenshots );
	}

	// Tags.
	if ( ! empty( $theme['tags'] ) && is_array( $theme['tags'] ) ) {
		wp_set_object_terms( $post_id, array_keys( $theme['tags'] ), 'post_tag' );
	}

	// Business model.
	if ( ! empty( $theme['is_commercial'] ) ) {
		wp_set_object_terms( $post_id, 'commercial', 'theme_business_model' );
	} elseif ( ! empty( $theme['is_community'] ) ) {
		wp_set_object_terms( $post_id, 'community', 'theme_business_model' );
	}

	// Active installs.
	if ( isset( $theme['active_installs'] ) ) {
		update_post_meta( $post_id, '_active_installs', (int) $theme['active_installs'] );
	}

	// Download count.
	if ( isset( $theme['downloaded'] ) ) {
		update_post_meta( $post_id, '_downloaded', (int) $theme['downloaded'] );
	}

	// Requirements.
	if ( ! empty( $theme['requires'] ) ) {
		update_post_meta( $post_id, '_requires', $theme['requires'] );
	}
	if ( ! empty( $theme['requires_php'] ) ) {
		update_post_meta( $post_id, '_requires_php', $theme['requires_php'] );
	}

	return get_post( $post_id );
}

// Main loop.
$imported_slugs = array();

foreach ( $browse_sections as $section ) {
	echo "Fetching themes in '{$section}' section...\n";

	$themes = fetch_themes( $api_url, $section, $per_section );
	echo "  Found " . count( $themes ) . " themes.\n";

	$imported = 0;
	$tagged   = 0;

	foreach ( $themes as $theme_data ) {
		$slug = $theme_data['slug'];

		if ( in_array( $slug, $imported_slugs, true ) ) {
			// Already imported in a previous section, just tag it.
			$existing = get_posts( array(
				'post_type'   => 'repopackage',
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
			) );
			if ( $existing ) {
				echo "    {$slug}... {$existing[0]->post_title} (tagged)\n";
				$tagged++;
			}
			continue;
		}

		echo "    {$slug}...";

		$post = save_theme( $theme_data );
		if ( ! $post ) {
			echo " failed.\n";
			continue;
		}

		$imported_slugs[] = $slug;
		echo " {$post->post_title} (done)\n";
		$imported++;
	}

	echo "  {$section}: {$imported} new, {$tagged} tagged.\n\n";
}

// Flush rewrite rules.
flush_rewrite_rules();

echo "Done!\n";
