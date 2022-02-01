<?php
/**
 * This API endpoint is for internal WordPress.org usage.
 * 
 * This endpoint returns the active branches in develop.svn.wordpress.org / core.svn.wordpress.org.
 * 
 * Active branches are those which may have a stable release made from it, or form the basis of a future stable release.
 */

require dirname( dirname( __DIR__ ) ) . '/wp-init.php';

$branches = [
	'trunk',
	'branches/' . WP_CORE_STABLE_BRANCH,
];

// Between NextStable being branched, and becoming Stable, the Dev branch will point towards it.
// Eg, the above may read [ 'trunk' (6.0), 'branches/5.8' ], but it also needs 'branches/5.9' provided by WP_CORE_DEV_BRANCH.
if ( 'trunk' !== WP_CORE_DEV_BRANCH ) {
	$branches[] = 'branches/' . WP_CORE_DEV_BRANCH;
}

header( 'Content-Type: application/json' );

echo wp_json_encode( $branches );