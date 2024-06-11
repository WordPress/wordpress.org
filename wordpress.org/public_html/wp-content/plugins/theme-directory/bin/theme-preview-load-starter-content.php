<?php
namespace WordPressdotorg\Theme_Previews\Starter_Content;
/**
 * This is a custom PHP script run during playground instances via the preview blueprint.
 *
 * The purpose is to install/activate starter content as the basis for the preview.
 *
 * THIS IS USED BY rest-api/class-theme-preview.php
 */

/*
 * Run this file as an admin user, and trigger the customizer to be included, by adding a filter on plugins_loaded().
 *
 * This is somewhat hacky, but is the only real way to trigger the customizer to load as a logged in user.
 */
$GLOBALS['wp_filter'] = array(
	'plugins_loaded' => array(
		0 => array(
			array(
				'accepted_args' => 0,
				'function'      => __NAMESPACE__ . '\plugins_loaded',
			),
		),
	),
);

// This virtually ensures that this is never run directly.
if ( 'cli' != php_sapi_name() ) {
	exit;
}
require '/wordpress/wp-load.php';

// Force the site to be fresh, although it should already be.
add_filter( 'pre_option_fresh_site', '__return_true' );

/**
 * Ensure that the customizer loads as an admin user.
 */
function plugins_loaded() {
	// Set as the admin user, this ensures we can customize the site.
	wp_set_current_user( 1 );

	// Simulate this request as the customizer loading with the current theme in preview mode.
	$_REQUEST['wp_customize']        = 'on';
	$_REQUEST['customize_theme']     = get_stylesheet();
	$_REQUEST['customize_autosaved'] = 'on';
	$_GET                            = $_REQUEST;
}

global $wp_customize;

// Don't waste time running the import if there's no starter content.
if ( ! get_theme_starter_content() ) {
	return;
}

// Import the Starter Content.
$wp_customize->import_theme_starter_content();

/*
 * Remove the previewer filters that prevent saving options.
 *
 * This is because the customizer expects that the publish action occurs on a pageload that is not a preview pageload.
 */
foreach ( $wp_customize->settings() as $setting ) {
	if ( 'option' !== $setting->type ) {
		continue;
	}
	$option_name = $setting->id_data()['base'];
	remove_filter( "pre_option_{$option_name}",     array( $setting, '_preview_filter' ) );
	remove_filter( "option_{$option_name}",         array( $setting, '_multidimensional_preview_filter' ) );
	remove_filter( "default_option_{$option_name}", array( $setting, '_multidimensional_preview_filter' ) );
}

// Publish the changeset, which publishes the starter content.
wp_publish_post( $wp_customize->changeset_post_id() );
