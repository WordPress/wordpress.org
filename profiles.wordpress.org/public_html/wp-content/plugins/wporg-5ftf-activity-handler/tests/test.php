<?php

/**
 * ⚠️️ These tests run against the production database and object cache on profiles.w.org.
 * Make sure that any modifications are hardcoded to only affect test sites and test user accounts.
 *
 * usage: wp eval-file test.php test_name
 */

namespace WordPressdotorg\WPORG_5ftf_Activity_Notifier\Tests;

use WPOrg_5ftf_Activity_Handler;

ini_set( 'display_errors', 'On' ); // won't do anything if fatal errors

if ( 'staging' !== wp_get_environment_type() || 'cli' !== php_sapi_name() ) {
	die( 'Error: Wrong environment.' );
}

const TEST_USERNAME = 'metatestaccount';

/** @var array $args */
main( $args[0] );


// Test Helper functions
function main( string $case ) : void {

	require_once dirname( __DIR__ ) . '/wporg-5ftf-activity-handler.php';

	$user = get_user_by( 'slug', TEST_USERNAME );

	call_user_func( __NAMESPACE__ . "\\test_$case", WPOrg_5ftf_Activity_Handler::get_instance(), $user );
}

function expect( $fail, $result ) {
	if ( $fail ) {
		echo 'Test Error: ' . PHP_EOL . ' Received:' . PHP_EOL . $result . PHP_EOL;
	}
}

function expectTrue( $result ) {
	expect( ( $result !== true || substr( $result, 0, 1 ) !== '1' ), $result );
}

function expectFalse( $result ) {
	expect( substr( $result, 0, 2 ) !== '-1', $result );
}

/**
 * Tests the handling of `bp_activity_add` activities
 *
 * Source: https://github.com/WordPress/wordpress.org/blob/trunk/profiles.wordpress.org/public_html/wp-content/plugins/wporg-profiles-activity-handler/wporg-profiles-activity-handler.php
 */
function test_bp_filter( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	test_bp_filter_success( $handler, $user );
	test_bp_filter_missing_user_id( $handler, $user );
	test_bp_filter_missing_type( $handler, $user );
	test_bp_filter_not_supported_type( $handler, $user );
}

/**
 * Should update database.
 */
function test_bp_filter_success( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	expectTrue(
		$handler->handle_activity(
			array(
				'type'    => 'blog_post_create',
				'user_id' => $user->ID,
			)
		)
	);
}

/**
 * Should return error message due to missing user id.
 */
function test_bp_filter_missing_user_id( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	expectFalse(
		$handler->handle_activity(
			array(
				'type' => 'blog_post_create',
			)
		)
	);
}

/**
 * Should return error message due to missing type.
 */
function test_bp_filter_missing_type( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	expectFalse(
		$handler->handle_activity(
			array(
				'user_id' => $user->ID,
			)
		)
	);
}

/**
 * Should return error message due to type not being a contribution.
 */
function test_bp_filter_not_supported_type( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	expectFalse(
		$handler->handle_activity(
			array(
				'type'    => 'not_supported',
				'user_id' => $user->ID,
			)
		)
	);
}

/**
 * Tests the handling of GitHub activities
 *
 * Source: https://github.com/WordPress/wordpress.org/blob/trunk/api.wordpress.org/public_html/dotorg/github/activity.php
 */
function test_github_filter( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	test_github_filter_success( $handler, $user );
	test_github_filter_no_user_id( $handler );
	test_github_filter_unsupported_category( $handler, $user );
}

/**
 * Should update database.
 */
function test_github_filter_success( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	expectTrue(
		$handler->handle_github_activity(
			array(
				'category' => 'pr_merged',
				'repo'     => 'wordpress.org',
				'user_id'  => $user->ID,
			)
		)
	);
}

/**
 * Should return error message due to missing user id.
 */
function test_github_filter_no_user_id( WPOrg_5ftf_Activity_Handler $handler ) : void {
	expectFalse(
		$handler->handle_github_activity(
			array(
				'category' => 'pr_merged',
				'repo'     => 'wordpress.org',
			)
		)
	);
}

/**
 * Should return error message due to unsupported category.
 */
function test_github_filter_unsupported_category( WPOrg_5ftf_Activity_Handler $handler, $user ) : void {
	expectFalse(
		$handler->handle_github_activity(
			array(
				'category' => 'not_supported',
				'repo'     => 'wordpress.org',
				'user_id'  => $user->ID,
			)
		)
	);
}
