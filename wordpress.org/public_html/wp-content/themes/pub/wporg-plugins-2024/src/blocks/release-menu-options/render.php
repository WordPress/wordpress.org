<?php

use WordPressdotorg\Plugin_Directory\Template;

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
	return;
}

$args = array(
	'post_type'      => 'plugin_release',
	'posts_per_page' => -1,
	'post_parent'    => $post->post_parent,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$releases = get_posts( $args );

$current_version = get_post_meta( $post->ID, 'release_version', true );
$previous_version = null;

foreach ( $releases as $key => $release ) {
	if ( $release->ID === $post->ID ) {
		if ( isset( $releases[ $key + 1 ] ) ) {
			$previous_version = get_post_meta( $releases[ $key + 1 ]->ID, 'release_version', true );
		}
		break;
	}
}


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
				'url' => Template::download_link( $post->post_parent, $current_version )
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

if ( null !== $previous_version ) {
	$plugin = get_post( $post->post_parent );

	$changes_link = sprintf(
		'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom","opensInNewTab":true} /-->',
		esc_html( 'View changes', 'wporg-plugins' ),
		esc_url( 
			sprintf( 
				'https://plugins.trac.wordpress.org/changeset?old_path=/%1$s/tags/%2$s&new_path=/%1$s/tags/%3$s',
				$plugin->post_name,
				$current_version,
				$previous_version 
			)
		)
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
