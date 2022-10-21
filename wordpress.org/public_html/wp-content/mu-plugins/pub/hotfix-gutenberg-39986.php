<?php
namespace WordPressdotorg\Hotfixes;

/**
 * Display the post author dropdown based on Caps, rather than User Levels / Roles.
 *
 * This can be removed when Gutenberg is updated.
 *
 * @see https://github.com/WordPress/gutenberg/issues/39986
 * @see https://meta.trac.wordpress.org/ticket/6326
 */
add_filter( 'rest_user_query', function( $args ) {
	if (
		isset( $args['who'] ) &&
		'authors' === $args['who'] &&
		 current_user_can( 'list_users' )
	) {
		unset( $args['who'] );
		$args['capability'] = 'edit_posts';
	}

	return $args;
} );
