<?php
/**
 * Import themes from WordPress.org for local development.
 *
 * Fetches themes from the popular, featured, and new browse views of the
 * themes API and creates a repopackage post for each, seeding the version
 * meta, rating/download data, and marking the latest version live.
 *
 * @package theme-directory-env
 */

namespace WordPressdotorg\Theme_Directory\Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Skip if already imported.
if ( get_option( 'wporg_themes_env_imported' ) ) {
	\WP_CLI::log( 'Already imported, skipping.' );
	return;
}

update_option( 'wporg_themes_env_imported', time() );
update_option( 'blogname', 'Theme Directory' );
update_option( 'blogdescription', 'Free WordPress Themes' );

$per_section = 15;
$api_url     = 'https://api.wordpress.org/themes/info/1.2/';
$sections    = array( 'popular', 'featured', 'new' );

/**
 * Fetch a browse section of themes with full fields.
 *
 * @param string $api_url Themes API endpoint.
 * @param string $browse  Browse slug (popular|featured|new).
 * @param int    $count   Number of themes to fetch.
 * @return array Array of theme objects.
 */
function fetch_themes( $api_url, $browse, $count ) {
	$args = array(
		'action'  => 'query_themes',
		'request' => array(
			'browse'   => $browse,
			'per_page' => $count,
			'fields'   => array(
				'description'     => true,
				'sections'        => true,
				'ratings'         => true,
				'active_installs' => true,
				'downloaded'      => true,
				'theme_url'       => true,
				'template'        => true,
				'tags'            => true,
				'extended_author' => true,
			),
		),
	);

	$response = wp_remote_get( add_query_arg( $args, $api_url ), array( 'timeout' => 60 ) );
	if ( is_wp_error( $response ) ) {
		\WP_CLI::warning( "Request for '{$browse}' failed: " . $response->get_error_message() );
		return array();
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		\WP_CLI::warning( "Themes API returned HTTP {$code} for '{$browse}'; the network may block api.wordpress.org." );
		return array();
	}

	$data = json_decode( wp_remote_retrieve_body( $response ) );

	return $data->themes ?? array();
}

/**
 * Ensure a WordPress user exists for a theme author, returning its ID.
 *
 * @param string $nicename     User nicename/slug.
 * @param string $display_name Optional display name.
 * @return int User ID, or 0 when no nicename was given.
 */
function ensure_user( $nicename, $display_name = '' ) {
	if ( ! $nicename ) {
		return 0;
	}

	$user = get_user_by( 'slug', $nicename );
	if ( ! $user ) {
		wp_insert_user( array(
			'user_login'    => $nicename,
			'user_nicename' => $nicename,
			'user_email'    => $nicename . '@example.invalid',
			'display_name'  => $display_name ?: $nicename,
			'user_pass'     => wp_generate_password(),
			'role'          => 'subscriber',
		) );
		$user = get_user_by( 'slug', $nicename );
	}

	return $user ? $user->ID : 0;
}

/**
 * Create or update a repopackage post from a themes-API theme object.
 *
 * @param object $theme Theme object from the themes API.
 * @return int|null Post ID, or null on failure.
 */
function save_theme( $theme ) {
	$existing      = get_posts( array(
		'post_type'   => 'repopackage',
		'post_status' => 'any',
		'name'        => $theme->slug,
		'numberposts' => 1,
	) );
	$theme_post_id = $existing ? $existing[0]->ID : 0;

	// Create a real author account from the API's author data.
	$author_id = ensure_user(
		$theme->author->user_nicename ?? '',
		$theme->author->display_name ?? ''
	);

	$description = '';
	if ( isset( $theme->sections->description ) ) {
		$description = $theme->sections->description;
	} elseif ( isset( $theme->description ) ) {
		$description = $theme->description;
	}

	// Backdate a few weeks to a year so themes clear the "popular" view's
	// two-week minimum age filter (see query-modifications.php) while still
	// giving the "new"/"updated" views a natural spread.
	$date = gmdate( 'Y-m-d H:i:s', time() - wp_rand( 21, 365 ) * DAY_IN_SECONDS );

	$theme_post_id = wp_insert_post( array(
		'ID'                => $theme_post_id,
		'post_title'        => $theme->name,
		'post_name'         => $theme->slug,
		'post_content'      => $description,
		'post_author'       => $author_id ?: 1,
		'post_status'       => 'publish',
		'post_date'         => $date,
		'post_date_gmt'     => $date,
		'post_modified'     => $date,
		'post_modified_gmt' => $date,
		'post_type'         => 'repopackage',
		'comment_status'    => 'closed',
		'ping_status'       => 'closed',
		'tags_input'        => array_keys( (array) ( $theme->tags ?? array() ) ),
	) );

	if ( ! $theme_post_id || is_wp_error( $theme_post_id ) ) {
		return null;
	}

	// Versioned meta, keyed by theme version (matches production storage).
	$version            = $theme->version ?? '1.0';
	$prefixed_post_meta = array(
		'_theme_url'    => $theme->theme_url ?? '',
		'_author'       => $theme->author->author ?? ( $theme->author->display_name ?? '' ),
		'_author_url'   => $theme->author->author_url ?? ( $theme->author->profile ?? '' ),
		'_requires'     => $theme->requires ?? '',
		'_requires_php' => $theme->requires_php ?? '',
		'_screenshot'   => basename( (string) wp_parse_url( $theme->screenshot_url ?? '', PHP_URL_PATH ) ),
	);
	foreach ( $prefixed_post_meta as $key => $value ) {
		$meta             = array_filter( (array) get_post_meta( $theme_post_id, $key, true ) );
		$meta[ $version ] = $value;
		update_post_meta( $theme_post_id, $key, $meta );
	}

	// Rating meta consumed by the WPORG_Ratings stub
	// (environments/mocks/mu-plugins/wporg-themes-ratings.php).
	update_post_meta( $theme_post_id, '_active_installs', (int) ( $theme->active_installs ?? 0 ) );
	update_post_meta( $theme_post_id, 'rating', (float) ( $theme->rating ?? 0 ) / 20 );
	update_post_meta( $theme_post_id, 'num_ratings', (int) ( $theme->num_ratings ?? 0 ) );
	update_post_meta( $theme_post_id, 'ratings', (array) ( $theme->ratings ?? array() ) );

	// The "popular" browse view (the directory default) orders by and requires a
	// _popularity meta key; use active installs as a stand-in for the production
	// popularity score so imported themes appear and sort sensibly.
	update_post_meta( $theme_post_id, '_popularity', (float) ( $theme->active_installs ?? 0 ) );

	// Business model taxonomy powers the commercial/community browse views.
	if ( ! empty( $theme->is_commercial ) ) {
		wp_set_object_terms( $theme_post_id, 'commercial', 'theme_business_model' );
	} elseif ( ! empty( $theme->is_community ) ) {
		wp_set_object_terms( $theme_post_id, 'community', 'theme_business_model' );
	}

	// Seed the download count into the bb_themes_stats table the directory reads
	// from (SELECT SUM(downloads) ... in class-themes-api.php).
	global $wpdb;
	$wpdb->replace(
		'bb_themes_stats',
		array(
			'slug'      => $theme->slug,
			'date'      => gmdate( 'Y-m-d' ),
			'downloads' => (int) ( $theme->downloaded ?? 0 ),
		),
		array( '%s', '%s', '%d' )
	);

	/*
	 * Mark this version live by writing the _status meta directly. Calling
	 * wporg_themes_update_version_status() would trigger the approval workflow,
	 * which downloads the theme's style.css from themes.svn.wordpress.org and
	 * pings wp-themes.com to derive the parent theme, description, and tags. We
	 * already have that data from the API, so the round-trip is unnecessary — and
	 * it hard-fails on networks that cannot reach those hosts.
	 */
	update_post_meta( $theme_post_id, '_status', array( $version => 'live' ) );

	return $theme_post_id;
}

// Main loop.
$imported = array();

foreach ( $sections as $browse ) {
	\WP_CLI::log( "Fetching '{$browse}' themes..." );
	$themes = fetch_themes( $api_url, $browse, $per_section );
	\WP_CLI::log( '  Found ' . count( $themes ) . ' themes.' );

	foreach ( $themes as $theme ) {
		if ( empty( $theme->slug ) ) {
			continue;
		}

		if ( ! isset( $imported[ $theme->slug ] ) ) {
			$theme_post_id = save_theme( $theme );
			if ( ! $theme_post_id ) {
				\WP_CLI::log( "    {$theme->slug}... failed." );
				continue;
			}

			$imported[ $theme->slug ] = $theme_post_id;
			\WP_CLI::log( "    {$theme->slug}... {$theme->name} (done)" );
		}

		// Tag the featured browse into the featured category.
		if ( 'featured' === $browse && isset( $imported[ $theme->slug ] ) ) {
			wp_set_object_terms( $imported[ $theme->slug ], 'featured', 'category', true );
		}
	}
}

// Flush rewrite rules to generate .htaccess.
flush_rewrite_rules();

\WP_CLI::log( 'Done! Imported ' . count( $imported ) . ' themes.' );
