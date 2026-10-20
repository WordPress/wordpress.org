#!/usr/bin/php
<?php
/**
 * Report on phpcs violations that are introduced in the current branch (not trunk).
 * Runs `phpcs` as normal on new files, and `phpcs-changed` on modified files. `phpcs-changed` will only report
 * on changed lines in each modified file.
 *
 * How to use: BASE_REF=trunk php .github/bin/phpcs-branch.php
 */

// phpcs:ignoreFile
namespace WordPressOrg\Bin\PHPCS_Changed;

/*
 * Restore the ANSI colors that phpcs-changed's reporter drops, matching the
 * colored report phpcs itself produces for new files.
 */
function colorize_report( $lines ) {
	return array_map(
		function ( $line ) {
			return preg_replace(
				array( '/^(\s*\d+ \| )(ERROR)( \| )/', '/^(\s*\d+ \| )(WARNING)( \| )/', '/^(FILE: .+|FOUND .+)$/' ),
				array( "$1\033[31m$2\033[0m$3", "$1\033[33m$2\033[0m$3", "\033[1m$1\033[0m" ),
				$line
			);
		},
		$lines
	);
}

/*
 * Escape data for GitHub workflow commands; property values additionally
 * escape the property separators.
 */
function annotation_escape( $value, $is_property = false ) {
	$value = str_replace( array( '%', "\r", "\n" ), array( '%25', '%0D', '%0A' ), $value );
	if ( $is_property ) {
		$value = str_replace( array( ':', ',' ), array( '%3A', '%2C' ), $value );
	}
	return $value;
}

/*
 * Surface each violation of a phpcs/phpcs-changed JSON report as an inline
 * annotation on the PR diff. No-op outside of GitHub Actions.
 */
function emit_annotations( $report, $file_override = null ) {
	if ( ! getenv( 'GITHUB_ACTIONS' ) || empty( $report['files'] ) ) {
		return;
	}

	foreach ( $report['files'] as $path => $details ) {
		foreach ( $details['messages'] ?? array() as $message ) {
			$type       = ( 'ERROR' === $message['type'] ) ? 'error' : 'warning';
			$properties = 'file=' . annotation_escape( $file_override ?? $path, true ) . ',line=' . (int) $message['line'];
			if ( ! empty( $message['column'] ) ) {
				$properties .= ',col=' . (int) $message['column'];
			}
			$text = $message['message'] . ( empty( $message['source'] ) ? '' : " ({$message['source']})" );
			echo "\n::{$type} {$properties}::" . annotation_escape( $text );
		}
	}
	echo "\n";
}

/*
 * New files are scanned in a single invocation, so that the parallel processing
 * configured in phpcs.xml.dist can scan them concurrently.
 */
function run_phpcs( $files, $bin_dir ) {
	$args = implode( ' ', array_map( 'escapeshellarg', $files ) ) . ' -snq';

	// Only produce the JSON report when there are annotations to feed.
	if ( getenv( 'GITHUB_ACTIONS' ) ) {
		$json_report = '.phpcs-branch.json';
		$args       .= ' --report-full --report-json=' . escapeshellarg( $json_report );
	}

	exec( "$bin_dir/phpcs $args", $output, $exec_exit_status );
	echo implode( "\n", $output );

	if ( ! empty( $json_report ) && file_exists( $json_report ) ) {
		emit_annotations( (array) json_decode( file_get_contents( $json_report ), true ) );
		unlink( $json_report );
	}

	return $exec_exit_status;
}

/*
 * Note: This differs from the typical usage of phpcs-changed, which suggests piping in the file contents.
 * Here, we create temporary files instead, because piping in the contents causes phpcs-changed to misbehave
 * when the file contains special characters (like escape sequences such as '\\').
 */
function run_phpcs_changed( $file, $git, $base_branch, $bin_dir ) {
	$name       = basename( $file );
	$file_arg   = escapeshellarg( $file );
	$branch_arg = escapeshellarg( $base_branch );
	$diff       = escapeshellarg( "$name.diff" );
	$orig_json  = escapeshellarg( "$name.orig.phpcs" );
	$new_json   = escapeshellarg( "$name.phpcs" );

	/*
	 * Scan the copies at a path mirroring the original file, so that the file name and
	 * the path-based exclude patterns in phpcs.xml.dist apply as they would for the real file.
	 */
	$test_file = escapeshellarg( ".phpcs-branch/$file" );
	exec( 'mkdir -p ' . escapeshellarg( dirname( ".phpcs-branch/$file" ) ) );

	exec( "$git diff $branch_arg $file_arg > $diff" );

	exec( "$git show " . escapeshellarg( "$base_branch:$file" ) . " > $test_file" );
	exec( "$bin_dir/phpcs $test_file --standard=./phpcs.xml.dist --report=json -snq > $orig_json" );

	exec( "cat $file_arg > $test_file" );
	exec( "$bin_dir/phpcs $test_file --standard=./phpcs.xml.dist --report=json -snq > $new_json" );

	$cmd = "$bin_dir/phpcs-changed -s --diff $diff --phpcs-orig $orig_json --phpcs-new $new_json";
	exec( $cmd, $output, $exec_exit_status );
	echo implode( "\n", colorize_report( $output ) );
	echo "\n";

	// The report is keyed by the temporary path, so annotate the real file instead.
	if ( getenv( 'GITHUB_ACTIONS' ) ) {
		exec( "$cmd --report json", $json_output );
		emit_annotations( (array) json_decode( implode( '', $json_output ), true ), $file );
	}

	exec( "rm $diff $test_file $orig_json $new_json" );
	return $exec_exit_status;
}

function main() {
	$base_branch = 'remotes/origin/' . getenv( 'BASE_REF' );
	$git_dir     = dirname( dirname( __DIR__ ) );
	$bin_dir     = dirname( dirname( __DIR__ ) ) . '/vendor/bin';
	$git         = "git -C $git_dir";

	try {
		echo "\nScanning changed files...\n";
		$status = 0;

		$affected_files = shell_exec( "$git diff $base_branch --name-status --diff-filter=AM 2>&1 | grep .php$" );
		$affected_files = explode( "\n", trim( $affected_files ) );
		$new_files      = array();
		$modified_count = 0;

		foreach ( $affected_files as $record ) {
			if ( ! $record ) {
				continue;
			}

			list( $change, $file ) = explode( "\t", trim( $record ) );

			switch ( $change ) {
				case 'M':
					$modified_count++;
					echo "Checking changed lines in $file:\n";
					// If any cmd exits with 1, we want to exit with 1.
					$status |= run_phpcs_changed( $file, $git, $base_branch, $bin_dir );
					break;

				case 'A':
					$new_files[] = $file;
					break;
			}
		}

		if ( $new_files ) {
			echo 'Checking ' . count( $new_files ) . " new file(s):\n\t" . implode( "\n\t", $new_files ) . "\n";
			$status |= run_phpcs( $new_files, $bin_dir );
		}

		exec( 'rm -rf .phpcs-branch' );

		printf( "\nDone. Checked %d modified and %d new file(s).\n", $modified_count, count( $new_files ) );

	} catch ( \Exception $exception ) {
		echo "\nAborting because of error: {$exception->getMessage()} \n";
		$status = 1;

	}

	exit( $status );
}

main();
