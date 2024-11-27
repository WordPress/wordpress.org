<?php

use WordPressdotorg\Plugin_Directory\Template;
use function WordPressdotorg\Plugin_Directory\Theme\{get_releases,get_trac_changeset_link,get_previous_version};

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );
if ( ! $release_post ) {
	return;
}

$current_version = get_post_meta( $release_post->ID, 'release_version', true );

/**
 * Blueprint is base64 encoded to be passed as a URL parameter.
 *
 * @see https://wordpress.github.io/wordpress-playground/blueprints/tutorial/how-to-load-run-blueprints#base64-encoded-blueprints
 */
$blueprint = wp_json_encode( [
	'login' => true,
	'steps' => [
		[
			'step' => 'installPlugin',
			'pluginData' => [
				'resource' => 'url',
				'url' => Template::download_link( $release_post->post_parent, $current_version )
			]
		]
	]
] );

$encoded_blueprint_url = 'https://playground.wordpress.net/#' . base64_encode( $blueprint );

$download_link = sprintf(
	'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom"} /-->',
	esc_html( 'Download', 'wporg-plugins' ),
	esc_url( $download_link )
);

$blueprint_link = sprintf(
	'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom","opensInNewTab":true} /-->',
	esc_html( 'Load in Playground', 'wporg-plugins' ),
	esc_url( $encoded_blueprint_url )
);

$changes_link = '';
$releases = get_releases( $release_post->post_parent );
$previous_version = get_previous_version( $release_post, $releases );

if ( null !== $previous_version ) {
	$changes_link = sprintf(
		'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom","opensInNewTab":true} /-->',
		esc_html( 'View changes', 'wporg-plugins' ),
		esc_url( get_trac_changeset_link( $release_post->post_parent, $previous_version, $current_version ) )
	);
}

$submenu = sprintf(
	'<!-- wp:navigation-submenu {"label":"%1$s"} -->%2$s%3$s%4$s<!-- /wp:navigation-submenu -->',
	esc_html( 'Release options', 'wporg-plugins' ),
	$download_link,
	$blueprint_link,
	$changes_link
);

$navigation = sprintf(
	'<!-- wp:navigation {"overlayMenu":"never","openSubmenusOnClick":true,"ariaLabel":"%1$s","className":"wporg-release-menu-options"} -->%2$s<!-- /wp:navigation -->',
	esc_html( 'Release options', 'release options label', 'wporg-plugins' ),
	$submenu
);

echo do_blocks( $navigation );
