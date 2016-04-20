<?php

// Various Template tags

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
	$filename = sprintf( "%s.%s.zip", get_post()->post_name, wporg_plugins_the_version() );
	return esc_url( "https://downloads.wordpress.org/plugin/{$filename}" );
}

function worg_plugins_template_active_installs( $full = true ) {
	$count = WordPressdotorg\Plugin_Directory\Template::get_active_installs_count();

	if ( $count <= 10 ) {
		$text = __( 'Less than 10', 'wporg-plugins' );
	} elseif ( $count >= 1000000 ) {
		$text = __( '1+ million', 'wporg-plugins' );
	} else {
		$text = number_format_i18n( $count ) . '+';
	}
	return $full ? sprintf( __( '%s active installs', 'wporg-plugins' ), $text ) : $text;
}

function wporg_plugins_template_authors() {
	$authors = get_post_meta( get_the_id(), 'contributors', true );

	$author_links = array();
	$and_more = false;
	foreach ( $authors as $author ) {
		$user = get_user_by( 'login', $author );
		if ( ! $user ) {
			continue;
		}
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


