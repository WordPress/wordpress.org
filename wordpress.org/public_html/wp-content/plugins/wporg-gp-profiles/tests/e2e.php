<?php

/**
 * ⚠️ These tests run against the production database and object cache on w.org and profiles.w.org.
 * Make sure that any modifications are hardcoded to only affect test sites and test user accounts.
 *
 * usage: wp eval-file e2e.php test_name
 */

namespace WordPressdotorg\GlotPress\Profiles\Tests;

use WordPressdotorg\GlotPress\Profiles as GlotPress_Profiles;
use Exception, WP_User, GP_Translation, GP_Translation_Set, GP_Project, GP_Locale;

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
	GlotPress_Profiles\add_single_translation_activity( $translation );

	$previous_translation = new GP_Translation( array(
		'user_id' => $user->ID,
		'status'  => 'waiting',
	) );
	$translation = new GP_Translation( array(
		'user_id' => $user->ID,
		'status'  => 'current',
	) );
	GlotPress_Profiles\add_single_translation_activity( $translation, $previous_translation );

	echo "\nThe daily digest count should have been bumped on https://profiles.wordpress.org/$user->user_nicename/ \n";
}

function test_bulk_approve( WP_User $user ) {
	$bulk = array(
		'action'  => 'approve',
		'row-ids' => array( '541-33', '542-34' ),
	);
	GlotPress_Profiles\add_bulk_translation_activity( new GP_Project(), new GP_Locale(), new GP_Translation_Set(), $bulk );

	echo "\nThe daily digest count should have been bumped on https://profiles.wordpress.org/$user->user_nicename/ \n";
}
