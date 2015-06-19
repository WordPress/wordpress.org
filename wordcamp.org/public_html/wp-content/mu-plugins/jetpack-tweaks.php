<?php

namespace WordCamp\Jetpack_Tweaks;

/*
 * Open Graph Default Image.
 *
 * Provides a default image for sharing WordCamp home/pages to Facebook/Twitter/Google other than the Jetpack "blank" image.
 */
function wc_default_og_image() {
	return 'https://s.w.org/images/backgrounds/wordpress-bg-medblue.png';
}
add_filter( 'jetpack_open_graph_image_default', 'wc_default_og_image' );

/*
 * Add Twitter Card type.
 *
 * Added the twitter:card = summary OG tag for the home page and other ! is_singular() pages, which is not added by default by Jetpack.
 */
function wc_add_og_twitter_summary( $og_tags ) {
	if ( is_home() || is_front_page() ) {
		$og_tags['twitter:card'] = 'summary';
	}

	return $og_tags;
}
add_filter( 'jetpack_open_graph_tags', 'wc_add_og_twitter_summary' );

/*
 * User @WordCamp as the default Twitter account.
 *
 * Add default Twitter account as @WordCamp for when individual WCs do not set their Settings->Sharing option for Twitter cards only.
 * Sets the "via" tag to blank to avoid slamming @WordCamp moderators with a ton of shared posts.
 */
function wc_twitter_sitetag( $site_tag ) {
	if ( 'jetpack' == $site_tag ) {
		$site_tag = 'WordCamp';
		add_filter( 'jetpack_sharing_twitter_via', '__return_empty_string' );
	}

	return $site_tag;
}
add_filter( 'jetpack_twitter_cards_site_tag', 'wc_twitter_sitetag' );

/*
 * Determine which Jetpack modules should be automatically activated when new sites are created
 */
function wcorg_default_jetpack_modules( $modules ) {
	$modules = array_diff( $modules, array( 'widget-visibility' ) );
	array_push( $modules, 'contact-form', 'shortcodes', 'custom-css', 'subscriptions' );

	return $modules;
}
add_filter( 'jetpack_get_default_modules', 'wcorg_default_jetpack_modules' );

/*
 * Enable Photon support for HTTPS URLs
 */
add_filter( 'jetpack_photon_reject_https', '__return_false' );

/**
 * Always automatically connect new sites to WordPress.com
 *
 * The UI for the auto-connect option is currently commented out in Jetpack. You can enable the setting manually,
 * but it will get overridden if you save the settings from the UI, because the form field is missing.
 *
 * @todo Remove this when the UI for the setting is launched.
 *
 * @param array $new_value
 * @param array $old_value
 *
 * @return array
 */
function auto_connect_new_sites( $new_value, $old_value ) {
	$new_value['auto-connect'] = 1;

	return $new_value;
}
add_filter( 'pre_update_site_option_jetpack-network-settings', __NAMESPACE__ . '\auto_connect_new_sites', 10, 2 );
