<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Template;

// Returns an absolute url to the current url, no matter what that actually is.
function wporg_plugins_self_link() {
	$site_path = preg_replace( '!^' . preg_quote( parse_url( home_url(), PHP_URL_PATH ), '!' ) . '!', '', $_SERVER['REQUEST_URI'] );
	return home_url( $site_path );
}

function wporg_plugins_template_last_updated() {
	return '<span title="' . get_the_time('Y-m-d') . '">' . sprintf( _x( '%s ago', 'wporg-plugins' ), human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ) . '</span>';
}

function wporg_plugins_template_compatible_up_to() {
	$tested = get_post_meta( get_the_id(), 'tested', true ) ;
	if ( ! $tested ) {
		$tested = _x( 'unknown', 'unknown version', 'wporg-plugins' );
	}
	return esc_html( $tested );
}

function wporg_plugins_template_requires() {
	return esc_html( get_post_meta( get_the_id(), 'requires', true ) );
}

function wporg_plugins_the_version() {
	return esc_html( get_post_meta( get_the_id(), 'version', true ) );
}

function wporg_plugins_download_link() {
	return esc_url( Template::download_link( get_the_id() ) );
}

function wporg_plugins_template_authors() {
	$contributors = get_post_meta( get_the_id(), 'contributors', true );

	$authors = array();
	foreach ( $contributors as $contributor ) {
		$user = get_user_by( 'login', $contributor );
		if ( $user ) {
			$authors[] = $user;
		}
	}

	if ( ! $authors ) {
		$authors[] = new \WP_User( get_post()->post_author );
	}

	$author_links = array();
	$and_more = false;
	foreach ( $authors as $user ) {
		$author_links[] = sprintf( '<a href="%s">%s</a>', 'https://profiles.wordpress.org/' . $user->user_nicename . '/', $user->display_name );
		if ( count( $author_links ) > 5 ) {
			$and_more = true;
			break;
		}
	}

	if ( $and_more ) {
		return sprintf( '<cite> By: %s, and others.</cite>', implode(', ', $author_links ) );
	} else {
		return sprintf( '<cite> By: %s</cite>', implode(', ', $author_links ) );
	}
}
