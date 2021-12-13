<?php
namespace WordPressdotorg\Trac\Watcher\SVN;
use function WordPressdotorg\Trac\Watcher\Props\from_log as props_from_log;
use function WordPressdotorg\Trac\Watcher\Props\find_user_id;

const MAX_REVISIONS = 250;

add_action( 'import_revisions_from_svn', function() {
	foreach ( get_svns() as $svn ) {
		import_revisions( $svn );
	}
} );

function get_svns() {
	return [
		'core' => [
			'slug'        => 'core',
			'name'        => 'Core',
			'url'         => 'https://develop.svn.wordpress.org',
			'trac'        => 'https://core.trac.wordpress.org',
			'rev_table'   => 'trac_core_revisions',
			'props_table' => 'trac_core_props',
		],
		'meta' => [
			'slug'        => 'meta',
			'name'        => 'Meta',
			'url'         => 'https://meta.svn.wordpress.org',
			'trac'        => 'https://meta.trac.wordpress.org',
			'rev_table'   => 'trac_meta_revisions',
			'props_table' => 'trac_meta_props',
		]
	];
}

function import_revisions( $svn ) {
	global $wpdb;

	$svn_url     = $svn['url'];
	$slug        = $svn['slug'];
	$db_table    = $svn['rev_table'];
	$props_table = $svn['props_table'] ?? false;

	$last_revision = $wpdb->get_var( "SELECT max(id) FROM {$db_table}" );
	if ( ! is_numeric( $last_revision ) ) {
		trigger_error( "Can't find max row for {$db_table} to import {$svn_url} revisions.", E_USER_WARNING );
		return false;
	}

	$command = sprintf(
		'svn log %s -r %d:HEAD --limit %d --xml -v 2>/dev/null',
		esc_url( $svn_url ),
		(int) $last_revision,
		(int) MAX_REVISIONS
	);

	$xml_internal_errors = libxml_use_internal_errors( true );
	$xml                 = simplexml_load_string( shell_exec( $command ) );
	libxml_use_internal_errors( $xml_internal_errors );

	if ( ! $xml ) {
		// Malformed XML, happens when SVN hits an error prior to finishing output (or the revision range is invalid)
		return false;
	}

	$processed = 0;
	foreach ( $xml as $change ) {
		$id     = (int) $change->attributes()['revision'];
		$author = trim( (string) $change->author );
		$msg    = trim( (string) $change->msg );
		$date   = gmdate( 'Y-m-d H:i:s', strtotime( $change->date ) );

		// No need to re-process the last revision.
		if ( $id <= $last_revision ) {
			continue;
		}

		echo "Importing {$id} \n";

		$paths  = (array) $change->paths->path;
		$paths  = array_filter( $paths, 'is_string' ); // hacky, to remove attributes array, leaving just paths.

		$branch = get_branch_from_paths( $paths );

		// Short summary - First line, first sentence, max 32 words.
		$summary = explode( "\n", $msg )[0];
		if ( $pos = strpos( $summary, '. ' ) ) {
			$summary = substr( $summary, 0, $pos + 1 );
		}
		$summary = wp_trim_words( $summary, 32 );

		$data = [
			'id'      => $id,
			'author'  => $author,
			'summary' => $summary,
			'message' => $msg,
			'date'    => $date,
			'branch'  => $branch,
		];

		// Fetch the version for core...
		if ( 'core' === $slug ) {
			$data['version'] = get_wp_version( $svn_url, $branch, $id );
		}

		$wpdb->insert( $db_table, $data );

		if ( $props_table ) {
			// Look for the props in the commit.
			$props = props_from_log( $msg );

			foreach ( $props as $prop ) {

				$data = [
					'revision'  => $id,
					'prop_name' => $prop,
				];

				$user_id = find_user_id( $prop );
				if ( $user_id ) {
					$data['user_id'] = $user_id;
				}

				$wpdb->insert( $props_table, $data );
			}
		}

		$processed++;
	}

	return $processed;
}

/**
 * Return the first branch related to the file paths given.
 */
function get_branch_from_paths( array $files ) {
	foreach ( $files as $file ) {
		if ( '/trunk' === substr( $file, 0, 6 ) ) {
			return 'trunk';
		}

		if ( '/branches/' === substr( $file, 0, 10 ) ) {
			$pos = max( 0, strpos( $file, '/', 12 ) - 1 ) ?: strlen( $file );
			return substr( $file, 1, $pos );
		}

		if ( '/tags/' === substr( $file, 0, 6 ) ) {
			$pos = max( 0, strpos( $file, '/', 7 ) - 1 ) ?: strlen( $file );
			return substr( $file, 1, $pos );
		}
	}

	return false;
}

function get_wp_version( $svn_url, $branch, $revision = 'HEAD' ) {
	$files = [
		'src/wp-includes/version.php',
		'wp-includes/version.php',
		'wp-includes/vars.php',
		'b2-include/b2vars.php',
	];

	foreach ( $files as $f ) {
		$url = "{$svn_url}/{$branch}/{$f}";
		$output = shell_exec( sprintf(
			'svn cat %s@%d 2>/dev/null',
			esc_url( $url ),
			(int) $revision
		) );

		// Use regex, because it's simpler than 32 lines of PHP Tokeniser.
		if ( $output && preg_match( '!\$(wp|b2)_version\s*=\s*([\'"])(?P<version>.*?)\\2!i', $output, $m ) ) {
			return $m['version'];
		}

	}

	return false;
}
