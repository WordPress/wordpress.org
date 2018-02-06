<?php
/**
 * Loops through child themes and creates new builds.
 */

chdir( dirname( __FILE__, 3 ) );

$pub = getcwd();
foreach ( glob( 'wporg-*' ) as $theme ) {
	chdir( $pub . DIRECTORY_SEPARATOR . $theme );

	if ( ! file_exists( 'Gruntfile.js' ) ) {
		continue;
	}

	echo "Building $theme...";

	$grunt = shell_exec( 'grunt build' );

	if ( false === stristr( $grunt, 'Done.' ) ) {
		echo " failed.\n\n";
	} else {
		echo " successful.\n\n";
	}
}
