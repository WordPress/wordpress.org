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

const TEST_USERNAME = 'dufresnesteven';
const TEST_USERID = '17657928';

/** @var array $args */
main( $args[0] );

function main( string $case ) : void {

	require_once dirname( __DIR__ ) . '/wporg-5ftf-activity-handler.php';

	call_user_func( __NAMESPACE__ . "\\test_$case", WPOrg_5ftf_Activity_Handler::get_instance() );


	//echo "\nThere should be new activity on https://profiles.wordpress.org/$user->user_nicename/ \n";
}

function expect( $fail, $result ) {
	if( $fail) {
		echo 'Test Error: ' . PHP_EOL . ' Received:' . PHP_EOL . $result  . PHP_EOL;
	}
}

function expectTrue( $result ) {
	expect( ( $result !== true || substr($result, 0, 1) !== '1' ), $result);
}

function expectFalse( $result ) {
	expect(substr($result, 0, 2) !== '-1', $result);
}

/**
 * Tests the handling of GitHub activities
 */

function test_github_filter( WPOrg_5ftf_Activity_Handler $handler ) : void {
	test_github_filter_success( $handler );
	test_github_filter_no_user_id( $handler );
	test_github_filter_unsupported_category( $handler );
}

function test_github_filter_success( WPOrg_5ftf_Activity_Handler $handler ) : void {
	expectTrue( $handler->handle_github_activity( [
		"category" => "pr_merged",
		"repo" => "wordpress.org",
		"user_id" => TEST_USERID
	] ) );
}

function test_github_filter_no_user_id( WPOrg_5ftf_Activity_Handler $handler ) : void {
	expectFalse( $handler->handle_github_activity( [
		"category" => "pr_merged",
		"repo" => "wordpress.org",
	] ) );
}

function test_github_filter_unsupported_category( WPOrg_5ftf_Activity_Handler $handler ) : void {
	expectFalse( $handler->handle_github_activity( [
		"category" => "not_supported",
		"repo" => "wordpress.org",
		"user_id" => TEST_USERID
	] ) );
}
