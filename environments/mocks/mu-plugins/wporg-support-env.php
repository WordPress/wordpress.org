<?php
/**
 * Plugin Name: Support Forums Environment
 * Description: Behaviour specific to the support forums network: marks one
 *              sub-site as a Rosetta network, and keeps the forums'
 *              notification mail local. A no-op in every other environment.
 *
 * This lives in the shared mocks directory rather than being mapped in over it.
 * wp-content/mu-plugins is bind-mounted to this directory, so mapping a single
 * file into it makes Docker create the mount point here, leaving a stray empty
 * file behind in the repository.
 *
 * @package wporg-env
 */

declare( strict_types = 1 );

if ( ! defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
	return;
}

// mu-plugins load after ms-settings.php, so the current blog is already known.
if ( ! defined( 'IS_ROSETTA_NETWORK' )
	&& defined( 'WPORG_LOCAL_ROSETTA_BLOGID' )
	&& is_multisite()
	&& (int) WPORG_LOCAL_ROSETTA_BLOGID === get_current_blog_id()
) {
	define( 'IS_ROSETTA_NETWORK', true );
}

// Without a transport in the container every send is a slow failure.
add_filter( 'pre_wp_mail', '__return_true' );
