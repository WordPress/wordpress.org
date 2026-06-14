<?php
/**
 * Import WordPress core (wp/dev + sub-projects) originals + translations from
 * translate.wordpress.org's export-translations endpoint, then refresh the
 * wporg-gp-custom-stats counters for the imported tree.
 *
 * Production layout (mirrored here):
 *   wp/dev                        (id 2, created in after-start.sh) — main strings
 *   wp/dev/admin                  — admin strings
 *   wp/dev/admin/network          — network admin strings
 *   wp/dev/cc                     — continents & cities
 *
 * Heavy import (~several minutes — core POs are large). Opt-in: NOT wired into
 * after-start.sh so wp-env start stays fast. Idempotent — safe to re-run.
 *
 * Usage:
 *   wp eval-file wp-content/env-bin/import-wp-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'GP' ) ) {
	fwrite( STDERR, "GlotPress is not loaded.\n" );
	exit( 1 );
}

/** @var wpdb $wpdb */
global $wpdb;

$dev = GP::$project->by_path( 'wp/dev' );
if ( ! $dev ) {
	fwrite( STDERR, "wp/dev project missing — run afterStart first.\n" );
	exit( 1 );
}

$seed_locales = $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT locale FROM {$wpdb->gp_translation_sets} WHERE project_id = %d",
	$dev->id
) );
if ( ! $seed_locales ) {
	fwrite( STDERR, "No locales seeded on wp/dev. Run afterStart first.\n" );
	exit( 1 );
}

$prod_base = 'https://translate.wordpress.org/projects';
$po_format = gp_array_get( GP::$formats, 'po' );

// Detach every incremental listener on the translation/originals hooks. They
// are all designed for one-off web requests (cache purgers, language-pack build
// triggers, stats trackers, Slack notifications, sync queues, …) and either
// crash or pile up wasted work during a bulk seed.
remove_all_actions( 'gp_originals_imported' );
remove_all_actions( 'gp_translation_created' );
remove_all_actions( 'gp_translation_saved' );
remove_all_actions( 'gp_translation_deleted' );

$ensure_project = function ( $name, $slug, $parent_id ) {
	$parent_obj = GP::$project->get( $parent_id );
	$path       = "{$parent_obj->path}/{$slug}";
	$existing   = GP::$project->by_path( $path );
	if ( $existing ) {
		return $existing;
	}
	$created = GP::$project->create( array(
		'name'              => $name,
		'slug'              => $slug,
		'parent_project_id' => $parent_id,
		'active'            => 1,
	) );
	if ( ! $created ) {
		fwrite( STDERR, "Failed to create project {$path}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "Created project: {$path}\n" );
	return $created;
};

$fetch_po = function ( $project_path, $locale_slug ) use ( $prod_base ) {
	$url  = "{$prod_base}/{$project_path}/{$locale_slug}/default/export-translations/?format=po";
	$resp = wp_remote_get( $url, array( 'timeout' => 120 ) );
	if ( is_wp_error( $resp ) ) {
		return null;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $resp ) ) {
		return false;
	}
	$tmp = wp_tempnam( str_replace( '/', '-', $project_path ) . "-{$locale_slug}.po" );
	file_put_contents( $tmp, wp_remote_retrieve_body( $resp ) );
	return $tmp;
};

$import_branch = function ( $project, $prod_path ) use ( $seed_locales, $fetch_po, $po_format ) {
	fwrite( STDOUT, "  branch {$project->path} (prod: {$prod_path}):\n" );

	$originals_imported = false;
	$ts_summary         = array();
	$any_ok             = false;

	foreach ( $seed_locales as $locale_slug ) {
		$po_path = $fetch_po( $prod_path, $locale_slug );
		if ( ! is_string( $po_path ) ) {
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
		$ts_summary[ $locale_slug ] = (int) $set->import( $translations );
	}

	if ( ! $any_ok ) {
		fwrite( STDOUT, "    not present on translate.wordpress.org\n" );
		return;
	}
	if ( $ts_summary ) {
		$pairs = array();
		foreach ( $ts_summary as $loc => $n ) {
			$pairs[] = "{$loc}={$n}";
		}
		fwrite( STDOUT, '    translations: ' . implode( ', ', $pairs ) . "\n" );
	}
};

// Build wp/dev sub-project tree.
fwrite( STDOUT, "Ensuring wp/dev sub-project tree...\n" );
$admin   = $ensure_project( 'Administration',      'admin',   $dev->id );
$network = $ensure_project( 'Network Admin',       'network', $admin->id );
$cc      = $ensure_project( 'Continents & Cities', 'cc',      $dev->id );

$tree = array(
	array( $dev, 'wp/dev' ),
	array( $admin, 'wp/dev/admin' ),
	array( $network, 'wp/dev/admin/network' ),
	array( $cc, 'wp/dev/cc' ),
);

fwrite( STDOUT, "Importing originals + translations (this can take several minutes)...\n" );
foreach ( $tree as list( $project, $prod_path ) ) {
	$import_branch( $project, $prod_path );
}

// Refresh wporg-gp-custom-stats for just the wp/dev tree. Mirrors the rebuild
// in after-start.sh, but scoped so the heavy import script is self-contained
// (running it should leave the homepage with real %-values, not 0).
fwrite( STDOUT, "Refreshing wporg-gp-custom-stats for wp/dev tree...\n" );
$wporg_gp_custom_stats = $GLOBALS['wporg_gp_custom_stats'] ?? null;
if ( ! isset( $wporg_gp_custom_stats ) ) {
	fwrite( STDERR, "wporg-gp-custom-stats not loaded; skipping stats refresh.\n" );
} else {
	$project_ids  = array_map( static fn( $entry ) => (int) $entry[0]->id, $tree );
	$placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a static "%d, %d, …" list built from a known-int count.
	$sets = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT project_id, locale, slug FROM {$wpdb->gp_translation_sets} WHERE project_id IN ({$placeholders})",
			...$project_ids
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$proj = $wporg_gp_custom_stats->project;
	foreach ( $sets as $set_row ) {
		// get_project_translation_counts() recurses into sub-projects, so wp/dev's
		// row ends up holding the totals across admin/network/cc as well — same
		// shape production produces via WPorg_GP_Project_Stats::shutdown().
		$counts = $proj->get_project_translation_counts( $set_row->project_id, $set_row->locale, $set_row->slug );
		if ( 0 === (int) $counts['all'] ) {
			continue;
		}
		// has_pending mirrors production (denormalized "waiting > 0 OR fuzzy > 0").
		// wporg-gp-routes/class-locale "needs attention" queries scan on it.
		$has_pending = ( $counts['waiting'] > 0 || $counts['fuzzy'] > 0 ) ? 1 : 0;
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}gp_project_translation_status
				 (project_id, locale, locale_slug, `all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated`, has_pending, date_added, date_modified)
				 VALUES (%d, %s, %s, %d, %d, %d, %d, %d, %d, %d, NOW(), NOW())
				 ON DUPLICATE KEY UPDATE
				 `all`=VALUES(`all`), `current`=VALUES(`current`), `waiting`=VALUES(`waiting`),
				 `fuzzy`=VALUES(`fuzzy`), `warnings`=VALUES(`warnings`), `untranslated`=VALUES(`untranslated`),
				 has_pending=VALUES(has_pending), date_modified=NOW()",
				$set_row->project_id,
				$set_row->locale,
				$set_row->slug,
				(int) $counts['all'],
				(int) $counts['current'],
				(int) $counts['waiting'],
				(int) $counts['fuzzy'],
				(int) $counts['warnings'],
				(int) $counts['untranslated'],
				$has_pending
			)
		);
	}
}

// The homepage caches translation_status for 15 min — bust it so the new
// percentages show immediately on the next request.
wp_cache_delete( 'translation-status', 'wporg-translate' );

fwrite( STDOUT, 'Done. WordPress core: ' . home_url( '/projects/wp/dev/' ) . "\n" );
