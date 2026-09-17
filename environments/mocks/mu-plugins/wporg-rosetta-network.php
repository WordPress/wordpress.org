<?php
/**
 * Plugin Name: WordPress.org Rosetta Network (local stub)
 * Description: Marks one sub-site of a local multisite network as a Rosetta
 *              network. On production each locale is its own network and
 *              IS_ROSETTA_NETWORK is set per network, which a single wp-env
 *              wp-config.php cannot express. Opt in by setting
 *              WPORG_LOCAL_ROSETTA_BLOGID; a no-op everywhere else.
 *
 * @package support-forums-env
 */

if ( ! defined( 'IS_ROSETTA_NETWORK' )
	&& defined( 'WPORG_LOCAL_ROSETTA_BLOGID' )
	&& is_multisite()
	&& (int) WPORG_LOCAL_ROSETTA_BLOGID === get_current_blog_id()
) {
	define( 'IS_ROSETTA_NETWORK', true );
}
