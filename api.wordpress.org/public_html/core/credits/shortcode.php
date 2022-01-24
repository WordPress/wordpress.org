<?php

defined( 'WPINC' ) or exit;

add_shortcode( 'wpcredits', 'wporg_wordpress_credits_shortcode' );

function wporg_wordpress_credits_shortcode( $attrs, $content = null ) {
	if ( ! isset( $attrs[0] ) ) {
		return '';
	}

	require_once __DIR__ . '/wp-credits.php';

	$version = preg_replace( '/^([.0-9]+).*/', '$1', $attrs[0] );
	$class = WP_Credits::factory( $version, false );
	$results = $class->get_results();

	$props = $results['groups']['props']['data'];
	unset( $results['groups']['libraries'], $results['groups']['props'] );

	foreach ( $results['groups'] as $section ) {
		foreach ( $section['data'] as $person ) {
			$props[ strtolower( $person[2] ) ] = $person[0];
		}
	}

	if ( isset( $attrs['exclude'] ) ) {
		$exclude = explode( ',', strtolower( $attrs['exclude'] ) );
		$props = array_diff_key( $props, array_flip( $exclude ) );
	}

	asort( $props, SORT_FLAG_CASE | SORT_STRING );
	$output = array();
	foreach ( $props as $username => $name ) {
		$output[] = '<a href="' . sprintf( $results['data']['profiles'], $username ) . '/">' . $name . '</a>';
	}

	return '<p>' . wp_sprintf( '%l.', $output ) . '</p>';
}
