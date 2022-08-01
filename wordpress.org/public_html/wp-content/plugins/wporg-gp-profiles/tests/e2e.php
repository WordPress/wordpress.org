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

const TRANSLATOR_ID      = 12824495; // iandunn-test
const REVIEWER_ID        = 20508422; // metatestaccount
const TRANSLATION_SET_ID = 3;

/** @var array $args */
main( $args[0] );

function main( $case ) {
	$translator = get_user_by( 'id', TRANSLATOR_ID );

	try {
		add_action( 'gp_pre_can_user', __NAMESPACE__ . '\grant_editor_capabilities', 10, 2 );
		call_user_func( __NAMESPACE__ . "\\test_$case", $translator );
		echo "\nThe daily digest count should have been bumped on https://profiles.wordpress.org/$translator->user_nicename/, and/or the reviewer. \n";

	} catch ( Exception $exception ) {
		echo $exception->getMessage();
	}
}

function grant_editor_capabilities( $preliminary, $filter_args ) {
	if ( REVIEWER_ID === $filter_args['user_id'] ) {
		return true;
	} else {
		return $preliminary;
	}
}

function test_suggest( WP_User $user ) {
	wp_set_current_user( TRANSLATOR_ID );

	$translation = new GP_Translation( array(
		'user_id'            => $user->ID,
		'status'             => 'waiting',
		'translation_set_id' => TRANSLATION_SET_ID,
	) );
	GlotPress_Profiles\add_single_translation_activity( $translation );
}

function test_approve( WP_User $translator ) {
	wp_set_current_user( REVIEWER_ID );

	$_POST['status'] = 'current';

	$previous_translation = new GP_Translation( array(
		'user_id'            => $translator->ID,
		'status'             => 'waiting',
		'translation_set_id' => TRANSLATION_SET_ID,
	) );
	$translation = new GP_Translation( array(
		'user_id' => $translator->ID,
		'status'  => 'current',
	) );
	GlotPress_Profiles\add_single_translation_activity( $translation, $previous_translation );
}

function test_bulk_approve( WP_User $translator ) {
	wp_set_current_user( REVIEWER_ID );

	$bulk = array(
		'action'  => 'approve',
		'row-ids' => array( '512-43', '514-44' ),
	);
	GlotPress_Profiles\add_bulk_translation_activity( new GP_Project(), new GP_Locale(), new GP_Translation_Set(), $bulk );
}

function test_bulk_reject( WP_User $translator ) {
	wp_set_current_user( REVIEWER_ID );

	$bulk = array(
		'action'  => 'reject',
		'row-ids' => array( '512-43', '514-44' ),
	);
	GlotPress_Profiles\add_bulk_translation_activity( new GP_Project(), new GP_Locale(), new GP_Translation_Set(), $bulk );
}
