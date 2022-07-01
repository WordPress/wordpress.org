<?php

/**
 * ⚠️ These tests run against the production database and object cache on w.org and profiles.w.org.
 * Make sure that any modifications are hardcoded to only affect test sites and test user accounts.
 *
 * usage: wp eval-file e2e.php test_name
 */

namespace WordPressdotorg\GlotPress\Profiles\Tests;

use WordPressdotorg\GlotPress\Profiles as GlotPress_Profiles;
use Exception, WP_User, GP_Translation;

ini_set( 'display_errors', 'On' ); // won't do anything if fatal errors

if ( 'staging' !== wp_get_environment_type() || 'cli' !== php_sapi_name() ) {
	die( 'Error: Wrong environment.' );
}

if ( 700 !== get_current_blog_id() ) {
	die( 'Must be run on translate-test.wordpress.org.' );
}

const TEST_USERNAME = 'iandunn-test';

/** @var array $args */
main( $args[0] );

function main( $case ) {
	$user = get_user_by( 'slug', TEST_USERNAME );

	try {
		call_user_func( __NAMESPACE__ . "\\test_$case", $user );

	} catch ( Exception $exception ) {
		echo $exception->getMessage();

	}
}

function test_add( WP_User $user ) {
	$translation = new GP_Translation( array(
		'user_id' => $user->ID,
		'status'  => 'waiting',
	) );
	GlotPress_Profiles\add_translation_activity( $translation );

	$translation = new GP_Translation( array(
		'user_id' => $user->ID,
		'status'  => 'current',
	) );
	GlotPress_Profiles\add_translation_activity( $translation );

	echo "\nThe daily digest count should have been bumped on https://profiles.wordpress.org/$user->user_nicename/ \n";
}
