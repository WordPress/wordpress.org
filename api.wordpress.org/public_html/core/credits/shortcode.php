<?php

defined( 'WPINC' ) or exit;

add_shortcode( 'wpcredits', 'wporg_wordpress_credits_shortcode' );

/**
 * Shortcode callback to render a list of props names, given a WP version.
 *
 * Example: [wpcredits 5.9 separator="bullet"]
 *
 * @param array $attrs
 *     @type string            Required. Nameless parameter specifying the WP version. Must be the first parameter.
 *     @type string $class     Optional. Space-separated list of class names to add to the container element.
 *     @type string $exclude   Optional. Comma-separated list of props names to exclude.
 *     @type string $separator Optional. Style of separator to use between props names. 'bullet' or 'comma'. Default 'bullet'.
 *
 * @return string
 */
function wporg_wordpress_credits_shortcode( $attrs ) {
	$attrs = wp_parse_args(
		$attrs,
		array(
			'class'     => 'is-style-wporg-props-long alignfull',
			'exclude'   => '',
			'separator' => 'bullet',
		)
	);

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

	if ( ! empty( $attrs['exclude'] ) ) {
		$exclude = explode( ',', strtolower( $attrs['exclude'] ) );
		$props = array_diff_key( $props, array_flip( $exclude ) );
	}

	asort( $props, SORT_FLAG_CASE | SORT_STRING );
	$output = array();
	foreach ( $props as $username => $name ) {
		$output[] = '<a href="' . sprintf( $results['data']['profiles'], $username ) . '/">' . $name . '</a>';
	}

	$container_atts = '';
	if ( ! empty( $attrs['class'] ) ) {
		$container_atts .= ' class="' . esc_attr( $attrs['class'] ) . '"';
	}

	$content = '';
	switch ( $attrs['separator'] ) {
		case 'comma':
		default:
			$content .= wp_sprintf( '%l.', $output );
			break;

		case 'bullet':
			$content .= implode( ' · ', $output );
			break;
	}

	return "<p{$container_atts}>" . $content . '</p>';
}
