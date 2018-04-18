<?php

/*
Plugin Name: WP15 - Miscellaneous
Description: Miscellaneous functionality for WP15
Version:     0.1
Author:      WordPress Meta Team
Author URI:  https://make.wordpress.org/meta
*/

namespace WP15\Miscellaneous;
use DateTime;
use TGGRSourceFlickr, TGGRSourceGoogle, TGGRSourceInstagram, TGGRSourceTwitter;

defined( 'WPINC' ) || die();

add_filter( 'map_meta_cap',           __NAMESPACE__ . '\allow_css_editing',         10, 2 );
add_filter( 'tggr_end_date',          __NAMESPACE__ . '\set_tagregator_cutoff_date'       );
add_filter( 'wp_insert_post_data',    __NAMESPACE__ . '\moderate_tagregator_posts'        );
add_action( 'wp_enqueue_scripts',     __NAMESPACE__ . '\register_assets',            1    );
add_action( 'admin_enqueue_scripts',  __NAMESPACE__ . '\register_assets',            1    );
add_filter( 'mime_types',             __NAMESPACE__ . '\mime_types'                       );

add_filter( 'tggr_show_log', '__return_true' );


/**
 * Allow admins to use Additional CSS, despite `DISALLOW_UNFILTERED_HTML`.
 *
 * The admins on this site are trusted, so `DISALLOW_UNFILTERED_HTML` is mostly in place to enforce best practices,
 * -- like placing JavaScript in a plugin instead of `post_content` -- rather than to prevent malicious code. CSS
 * is an exception to that rule, though; it's perfectly acceptable to store minor tweaks in Additional CSS, that's
 * what it's for.
 *
 * @param array  $required_capabilities The primitive capabilities that are required to perform the requested meta
 *                                      capability.
 * @param string $requested_capability  The requested meta capability.
 *
 * @return array
 */
function allow_css_editing( $required_capabilities, $requested_capability ) {
	if ( 'edit_css' === $requested_capability ) {
		$required_capabilities = array( 'edit_theme_options' );
	}

	return $required_capabilities;
}

/**
 * Tell Tagregator when to stop fetching new items.
 *
 * The #wp15 hashtag will collect spam, etc, after the event is over, and we want to
 * avoid publishing those.
 *
 * @param DateTime|null $date
 *
 * @return DateTime
 */
function set_tagregator_cutoff_date( $date ) {
	// A few weeks after the event ends, so that wrap-up posts, etc are included.
	return new DateTime( 'June 15, 2018' );
}

/**
 * Set new Tagregator posts to `pending`, so they can be manually approved before being displayed.
 *
 * The `#WP15` hashtag is shared with other, non-WordPress meanings, and sometimes has content that would be
 * inappropriate for an official WP site. So, we need to manually approve the posts before they're published.
 *
 * @param array $post_data
 *
 * @return array
 */
function moderate_tagregator_posts( $post_data ) {
	$tagregator_post_types = array();
	$moderator_actions     = array( 'edit', 'editpost', 'inline-save' );
	$modules               = array( 'TGGRSourceFlickr', 'TGGRSourceGoogle', 'TGGRSourceInstagram', 'TGGRSourceTwitter' );

	foreach ( $modules as $module ) {
		if ( defined( "$module::POST_TYPE_SLUG" ) ) {
			array_push( $tagregator_post_types, $module::POST_TYPE_SLUG );
		}
	}

	if ( 'publish' !== $post_data['post_status'] || ! in_array( $post_data['post_type'], $tagregator_post_types, true ) ) {
		return $post_data;
	}

	if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], $moderator_actions, true ) ) {
		return $post_data;
	}

	$post_data['post_status'] = 'pending';

	return $post_data;
}

/**
 * Register style and script assets for later enqueueing.
 */
function register_assets() {
	// Select2 styles.
	wp_register_style(
		'select2',
		WP_CONTENT_URL . '/mu-plugins/assets/select2/css/select2.min.css',
		array(),
		'4.0.5'
	);

	// Select2 script.
	wp_register_script(
		'select2',
		WP_CONTENT_URL . '/mu-plugins/assets/select2/js/select2.js',
		array(),
		'4.0.5',
		true
	);
}

/**
 * Add supported mime types.
 *
 * @param array $mime_types
 */
function mime_types( $mime_types ) {
	$mime_types['ai'] = 'application/postscript';

	return $mime_types;
}
