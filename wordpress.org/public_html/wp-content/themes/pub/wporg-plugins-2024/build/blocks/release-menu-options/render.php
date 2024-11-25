<?php

use WordPressdotorg\Plugin_Directory\Template;

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
	return;
}

$download_link = Template::download_link( $post->post_parent, get_post_meta( $post->ID, 'release_version', true ) );

$blueprint = <<<BLUEPRINT
{
	"login":true,
	"steps": [
		{
			"step": "installPlugin",
			"pluginData": {
				"resource": "url",
				"url": "$download_link"
			}
		}
	]
}
BLUEPRINT;

/**
 * Blueprint is base64 encoded to be passed as a URL parameter.
 *
 * @see https://wordpress.github.io/wordpress-playground/blueprints/tutorial/how-to-load-run-blueprints#base64-encoded-blueprints
 */
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

$subnav = sprintf(
	'<!-- wp:navigation-submenu {"label":"%1$s"} -->%2$s %3$s<!-- /wp:navigation-submenu -->',
	esc_html( 'Release options', 'wporg-plugins' ),
	$download_link,
	$blueprint_link
);

$navigation = sprintf(
	'<!-- wp:navigation {"overlayMenu":"never","openSubmenusOnClick":true,"ariaLabel":"%1$s","className":"wporg-release-menu-options"} -->%2$s<!-- /wp:navigation -->',
	esc_html( 'Release options', 'release options label', 'wporg-plugins' ),
	$subnav
);

echo do_blocks( $navigation );
