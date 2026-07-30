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

function run_phpcs( $file, $bin_dir ) {
	exec( "$bin_dir/phpcs $file -snq", $output, $exec_exit_status );
	echo implode( "\n", $output );
	return $exec_exit_status;
}

/*
 * Note: This differs from the typical usage of phpcs-changed, which suggests piping in the file contents.
 * Here, we create temporary files instead, because piping in the contents causes phpcs-changed to misbehave
 * when the file contains special characters (like escape sequences such as '\\').
 *
 * The temporary file is fed to phpcs on stdin with --stdin-path, so that sniffs which act on the file's
 * path (like WordPress.Files.FileName and ruleset exclude-patterns) see the real path, not the temp file.
 */
function run_phpcs_changed( $file, $git, $base_branch, $bin_dir ) {
	$name = basename( $file );
	exec( "$git diff $base_branch $file > $name.diff" );

	exec( "$git show $base_branch:$file > $name.test.php" );
	exec( "$bin_dir/phpcs --stdin-path=$file --standard=./phpcs.xml.dist --report=json -snq < $name.test.php > $name.orig.phpcs" );

	exec( "cat $file > $name.test.php" );
	exec( "$bin_dir/phpcs --stdin-path=$file --standard=./phpcs.xml.dist --report=json -snq < $name.test.php > $name.phpcs" );

	$cmd = "$bin_dir/phpcs-changed --diff $name.diff --phpcs-orig $name.orig.phpcs --phpcs-new $name.phpcs";
	exec( $cmd, $output, $exec_exit_status );
	echo implode( "\n", $output );
	echo "\n";

	exec( "rm $name.diff $name.test.php $name.orig.phpcs $name.phpcs" );
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

		foreach ( $affected_files as $record ) {
			if ( ! $record ) {
				continue;
			}

			list( $change, $file ) = explode( "\t", trim( $record ) );
			$cmd_status = 0;

			switch ( $change ) {
				case 'M':
					$cmd_status = run_phpcs_changed( $file, $git, $base_branch, $bin_dir );
					break;

				case 'A':
					$cmd_status = run_phpcs( $file, $bin_dir );
					break;
			}

			// If any cmd exits with 1, we want to exit with 1.
			$status |= $cmd_status;
		}

	} catch ( \Exception $exception ) {
		echo "\nAborting because of error: {$exception->getMessage()} \n";
		$status = 1;

	}

	exit( $status );
}

main();
