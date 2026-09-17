<?php
/**
 * Plugin Name: Support Forums Environment
 * Description: Environment-specific behaviour for the support forums network:
 *              marks one sub-site as a Rosetta network, and keeps the forums'
 *              notification mail local.
 *
 * Mapped in as wp-content/mu-plugins/wporg-support-env.php. It is deliberately
 * a file of its own rather than an override of anything in mocks/mu-plugins,
 * so nothing in the shared directory is shadowed.
 *
 * @package support-forums-env
 */

declare( strict_types = 1 );

/*
 * On production each locale forum is its own network with IS_ROSETTA_NETWORK
 * defined, which one wp-config.php cannot express. header.php and
 * Audit_Log::get_moderator_profile_url() gate on the constant, so define it for
 * the blog WPORG_LOCAL_ROSETTA_BLOGID names. mu-plugins load after
 * ms-settings.php, so the current blog is already known here.
 */
if ( ! defined( 'IS_ROSETTA_NETWORK' )
	&& defined( 'WPORG_LOCAL_ROSETTA_BLOGID' )
	&& is_multisite()
	&& (int) WPORG_LOCAL_ROSETTA_BLOGID === get_current_blog_id()
) {
	define( 'IS_ROSETTA_NETWORK', true );
}

/*
 * The forums mail on subscriptions, moderation actions and reports. There is no
 * transport in the container, so every send is a slow failure; short-circuit it.
 */
add_filter( 'pre_wp_mail', '__return_true' );
