<?php
/**
 * Import a plugin or theme's strings and translations directly from
 * translate.wordpress.org's export-translations endpoint.
 *
 * Production layout (mirrored here):
 *   plugins: wp-plugins/<slug>           (container)
 *            wp-plugins/<slug>/dev       (code originals from trunk)
 *            wp-plugins/<slug>/dev-readme(readme.txt originals from trunk)
 *            wp-plugins/<slug>/stable    (code originals from stable, optional)
 *            wp-plugins/<slug>/stable-readme
 *   themes:  wp-themes/<slug>            (flat)
 *
 * For each (sub-)project we hit translate.wordpress.org for each seeded locale:
 *   https://translate.wordpress.org/projects/<path>/<locale>/default/export-translations/?format=po
 * The .po gives us originals (msgid) and that locale's translations (msgstr).
 *
 * Usage:
 *   wp eval-file wp-content/env-bin/import-from-wporg.php <plugin|theme> <slug>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'GP' ) ) {
	fwrite( STDERR, "GlotPress is not loaded.\n" );
	exit( 1 );
}

$argv         = $args ?? array();
$import_type  = $argv[0] ?? '';
$slug         = sanitize_key( $argv[1] ?? '' );
if ( ! in_array( $import_type, array( 'plugin', 'theme' ), true ) || '' === $slug ) {
	fwrite( STDERR, "Usage: wp eval-file import-from-wporg.php <plugin|theme> <slug>\n" );
	exit( 1 );
}

$prod_base = 'https://translate.wordpress.org/projects';

if ( 'plugin' === $import_type ) {
	$info_url    = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . rawurlencode( $slug );
	$parent_slug = 'wp-plugins';
} else {
	$info_url    = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=' . rawurlencode( $slug );
	$parent_slug = 'wp-themes';
}

fwrite( STDOUT, "Looking up {$import_type} '{$slug}' on .org...\n" );
$info_resp = wp_remote_get( $info_url, array( 'timeout' => 30 ) );
if ( is_wp_error( $info_resp ) || 200 !== wp_remote_retrieve_response_code( $info_resp ) ) {
	fwrite( STDERR, "Could not look up {$import_type} '{$slug}'\n" );
	exit( 1 );
}
$info = json_decode( wp_remote_retrieve_body( $info_resp ), true );
if ( empty( $info['slug'] ) ) {
	fwrite( STDERR, "{$import_type} '{$slug}' not found on .org\n" );
	exit( 1 );
}
$project_name = $info['name'] ?? $slug;
$stable_tag   = $info['stable_tag'] ?? ( $info['version'] ?? '' );
$has_stable   = 'plugin' === $import_type && $stable_tag && 'trunk' !== $stable_tag;

// Helpers --------------------------------------------------------------------

$parent = GP::$project->by_path( $parent_slug );
if ( ! $parent ) {
	fwrite( STDERR, "Parent project '{$parent_slug}' missing — afterStart should have created it.\n" );
	exit( 1 );
}

$ensure_project = function ( $name, $slug, $parent_id ) {
	$parent_obj = $parent_id ? GP::$project->get( $parent_id ) : null;
	$path       = $parent_obj ? "{$parent_obj->path}/{$slug}" : $slug;
	$p          = GP::$project->by_path( $path );
	if ( $p ) {
		return $p;
	}
	$p = GP::$project->create( array(
		'name'              => $name,
		'slug'              => $slug,
		'parent_project_id' => $parent_id,
		'active'            => 1,
	) );
	if ( ! $p ) {
		fwrite( STDERR, "Failed to create project {$path}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "Created project: {$path}\n" );
	return $p;
};

$seed_locales = $GLOBALS['wpdb']->get_col( "SELECT DISTINCT locale FROM {$GLOBALS['wpdb']->gp_translation_sets} WHERE project_id = 2" );
if ( ! $seed_locales ) {
	fwrite( STDERR, "No locales seeded on wp/dev. Run afterStart first.\n" );
	exit( 1 );
}

// Map our local locale slug (matches wp/dev seed) to the slug translate.wordpress.org
// uses on its URLs. They're identical in nearly all cases (locale_slug from GP_Locales).
$po_format = gp_array_get( GP::$formats, 'po' );

$fetch_po = function ( $project_path, $locale_slug ) use ( $prod_base ) {
	$url  = "{$prod_base}/{$project_path}/{$locale_slug}/default/export-translations/?format=po";
	$resp = wp_remote_get( $url, array( 'timeout' => 60 ) );
	if ( is_wp_error( $resp ) ) {
		return null;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) {
		return $code;
	}
	$tmp = wp_tempnam( basename( $project_path ) . "-{$locale_slug}.po" );
	file_put_contents( $tmp, wp_remote_retrieve_body( $resp ) );
	return $tmp;
};

$import_branch = function ( $project, $prod_path ) use ( $seed_locales, $fetch_po, $po_format ) {
	fwrite( STDOUT, "  branch {$project->path} (prod: {$prod_path}):\n" );

	// Originals only need to be imported once. We pick the first locale that
	// returns 200 and treat its msgid list as the canonical originals set.
	$originals_imported = false;
	$ts_summary         = array();
	$any_ok             = false;

	foreach ( $seed_locales as $locale_slug ) {
		$result = $fetch_po( $prod_path, $locale_slug );
		if ( is_string( $result ) ) {
			$po_path = $result;
		} else {
			// Numeric status code (404 etc) or null (network error).
			continue;
		}
		$any_ok = true;

		if ( ! $originals_imported ) {
			$originals = $po_format->read_originals_from_file( $po_path, $project );
			if ( $originals ) {
				list( $added, $existing ) = GP::$original->import_for_project( $project, $originals );
				fwrite( STDOUT, "    originals: +{$added} (existing: {$existing}) — sourced from {$locale_slug}\n" );
				$originals_imported = true;
			}
		}

		$set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, 'default', $locale_slug );
		if ( ! $set ) {
			$set = GP::$translation_set->create( array(
				'name'       => $locale_slug,
				'slug'       => 'default',
				'project_id' => $project->id,
				'locale'     => $locale_slug,
			) );
		}
		if ( ! $set ) {
			@unlink( $po_path );
			continue;
		}

		$translations = $po_format->read_translations_from_file( $po_path, $project );
		@unlink( $po_path );
		if ( ! $translations ) {
			continue;
		}
		$imported                   = $set->import( $translations );
		$ts_summary[ $locale_slug ] = (int) $imported;
	}

	if ( ! $any_ok ) {
		fwrite( STDOUT, "    not present on translate.wordpress.org\n" );
		return false;
	}

	if ( $ts_summary ) {
		$pairs = array();
		foreach ( $ts_summary as $loc => $n ) {
			$pairs[] = "{$loc}={$n}";
		}
		fwrite( STDOUT, '    translations: ' . implode( ', ', $pairs ) . "\n" );
	}
	return true;
};

// Build project tree ---------------------------------------------------------

$top = $ensure_project( $project_name, $slug, $parent->id );

if ( 'plugin' === $import_type ) {
	$dev        = $ensure_project( 'Development (trunk)',          'dev',        $top->id );
	$dev_readme = $ensure_project( 'Development Readme (trunk)',   'dev-readme', $top->id );

	$import_branch( $dev,        "wp-plugins/{$slug}/dev" );
	$import_branch( $dev_readme, "wp-plugins/{$slug}/dev-readme" );

	if ( $has_stable ) {
		$stable        = $ensure_project( 'Stable (latest release)',        'stable',        $top->id );
		$stable_readme = $ensure_project( 'Stable Readme (latest release)', 'stable-readme', $top->id );
		$import_branch( $stable,        "wp-plugins/{$slug}/stable" );
		$import_branch( $stable_readme, "wp-plugins/{$slug}/stable-readme" );
	}
} else {
	$import_branch( $top, "wp-themes/{$slug}" );
}

fwrite( STDOUT, 'Done. Project: ' . home_url( "/projects/{$parent_slug}/{$slug}/" ) . "\n" );
