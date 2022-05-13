<?php

/**
 * ⚠️️ These tests run against the production database and object cache on learn.w.org and profiles.w.org.
 * Make sure that any modifications are hardcoded to only affect test sites and test user accounts.
 *
 * usage: wp eval-file e2e.php test_name
 */

namespace WordPressdotorg\Activity_Notifier\Tests;
use WPOrg_WP_Activity_Notifier;

ini_set( 'display_errors', 'On' ); // won't do anything if fatal errors

if ( 'staging' !== wp_get_environment_type() || 'cli' !== php_sapi_name() ) {
	die( 'Error: Wrong environment.' );
}

const TEST_USERNAME = 'iandunn-test';

/** @var array $args */
main( $args[0] );

function main( string $case ) : void {
	switch_to_blog( 11 ); // make.w.org/test-site
	require_once dirname( __DIR__ ) . '/wporg-profiles-wp-activity-notifier.php';

	// Disable the subscribers plugin in order to pass `is_post_notifiable()`.
	add_filter( 'option_active_plugins', __NAMESPACE__ . '\disable_subscribers_plugin' );

	$user = get_user_by( 'slug', TEST_USERNAME );
	call_user_func( __NAMESPACE__ . "\\test_$case", WPOrg_WP_Activity_Notifier::get_instance(), $user );

	restore_current_blog();

	echo "\nThere should be new activity on https://profiles.wordpress.org/$user->user_nicename/ \n";
}

function disable_subscribers_plugin( array $plugins ) : array {
	foreach( $plugins as $key => $plugin ) {
		if ( 'subscribers-only.php' === $plugin ) {
			unset ( $plugins[ $key ] );
		}
	}

	return $plugins;
}

function test_post( WPOrg_WP_Activity_Notifier $notifier ) : void {
	$notifier->maybe_notify_new_published_post( 'publish', 'draft', get_post( 1802 ) ); // post
	sleep( 1 ); // buddypress don't show activity that happens at the exact same time
	$notifier->maybe_notify_new_published_post( 'publish', 'draft', get_post( 1826 ) ); // handbook
	sleep( 1 );
	$notifier->maybe_notify_new_published_post( 'publish', 'draft', get_post( 1832 ) ); // course
}

function test_comment( WPOrg_WP_Activity_Notifier $notifier ) : void {
	$notifier->insert_comment( 224, get_comment( 224 ) );
}
