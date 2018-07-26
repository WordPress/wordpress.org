<?php

namespace WordCamp\Jetpack_Tweaks;

defined( 'WPINC' ) or die();

add_filter( 'jetpack_get_default_modules',                     __NAMESPACE__ . '\default_jetpack_modules'       );
add_filter( 'pre_update_site_option_jetpack-network-settings', __NAMESPACE__ . '\auto_connect_new_sites', 10, 2 );
add_action( 'wpmu_new_blog',                                   __NAMESPACE__ . '\schedule_connect_new_site'     );
add_action( 'wcorg_connect_new_site',                          __NAMESPACE__ . '\wcorg_connect_new_site', 10, 2 );

/*
 * Determine which Jetpack modules should be automatically activated when new sites are created
 */
function default_jetpack_modules( $modules ) {
	$modules = array_diff( $modules, array( 'widget-visibility' ) );
	array_push( $modules, 'contact-form', 'shortcodes', 'custom-css', 'subscriptions' );

	return $modules;
}

/**
 * Never automatically connect new sites to WordPress.com.
 *
 * Sites don't have SSL certificates when they're first created, so any attempt to connect to WordPress.com would
 * fail. Instead, connecting is attempted after the SSL has been installed. See wcorg_connect_new_site_email().
 *
 * @param array $new_value
 * @param array $old_value
 *
 * @return array
 */
function auto_connect_new_sites( $new_value, $old_value ) {
	$new_value['auto-connect'] = 0;

	return $new_value;
}

/**
 * Schedule an email asking to connect Jetpack to WordPress.com
 *
 * @param int $blog_id The blog id.
 */
function schedule_connect_new_site( $blog_id ) {
	wp_schedule_single_event(
		time() + 12 * HOUR_IN_SECONDS + 600, // After the the SSL certificate has been installed
		'wcorg_connect_new_site_email',
		array( $blog_id, get_current_user_id() )
	);
}

/**
 * Send a mail asking for connecting Jetpack to WordPress.com
 *
 * Runs during wp-cron.php.
 *
 * @param int $blog_id The blog_id to connect.
 * @param int $user_id The user ID who created the new site.
 */
function wcorg_connect_new_site_email( $blog_id, $user_id ) {

	$original_blog_id = get_current_blog_id();

	switch_to_blog( $blog_id );

	// Bail if Jetpack is already active
	if ( \Jetpack::is_active() ) {
		restore_current_blog();
		return;
	}
	restore_current_blog();

	$domain = get_site_url( $blog_id );

	$subject = 'Connect ' . $domain . ' with Jetpack';

	$email_content = get_wcorg_jetpack_email( $blog_id );
	wp_mail(
		'support@wordcamp.org',
		$subject,
		$email_content
	);
}

/**
 * Generate email content which contains the one click Jetpack - WordCamp connection link.
 *
 * @param $blog_id
 *
 * @return string
 */
function get_wcorg_jetpack_email( $blog_id ) {

	$domain = get_site_url( $blog_id );
	$jetpack_net_admin = \Jetpack_Network::init();
	$jetpack_link = $jetpack_net_admin->get_url( array(
		'name' => 'subsiteregister',
		'site_id' => $blog_id,
	) );
	$email_content = <<<TEXT
Hi there,

WordCamp site $domain can now be connected to Jetpack. Please click on the link below to activate the Jetpack connection on this site.

$jetpack_link

Please note that this link can only be used by people having access to Jetpack admin on wordcamp.org. If you do not have access, please assign this ticket to any Global Community Support team member. 

Thanks.

TEXT;

	return $email_content;
}