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
 * New files are scanned in a single invocation, so that the parallel processing
 * configured in phpcs.xml.dist can scan them concurrently.
 */
function run_phpcs( $files, $bin_dir ) {
	exec( "$bin_dir/phpcs " . implode( ' ', array_map( 'escapeshellarg', $files ) ) . ' -snq', $output, $exec_exit_status );
	echo implode( "\n", $output );
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
	echo implode( "\n", $output );
	echo "\n";

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

		foreach ( $affected_files as $record ) {
			if ( ! $record ) {
				continue;
			}

			list( $change, $file ) = explode( "\t", trim( $record ) );

			switch ( $change ) {
				case 'M':
					// If any cmd exits with 1, we want to exit with 1.
					$status |= run_phpcs_changed( $file, $git, $base_branch, $bin_dir );
					break;

				case 'A':
					$new_files[] = $file;
					break;
			}
		}

		if ( $new_files ) {
			$status |= run_phpcs( $new_files, $bin_dir );
		}

		exec( 'rm -rf .phpcs-branch' );

	} catch ( \Exception $exception ) {
		echo "\nAborting because of error: {$exception->getMessage()} \n";
		$status = 1;

	}

	exit( $status );
}

main();
