<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package HelpHub_Contributors
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
/**
 * Delete all plugin data on plugin uninstall
 */
delete_post_meta_by_key( 'helphub_contributors' );
