<?php
/**
 * Render the release menu options block.
 *
 * @package wporg-plugins
 */

use function WordPressdotorg\Plugin_Directory\Theme\{get_releases, get_previous_version, get_trac_changeset_link, get_blueprint_url, get_download_link};

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );
if ( ! $release_post ) {
	return;
}

$current_version = get_post_meta( $release_post->ID, 'release_version', true );
$download_link = get_download_link( $release_post->post_parent, $current_version );
$download_link_html = sprintf(
	'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom"} /-->',
	__( 'Download', 'wporg-plugins' ),
	esc_url( $download_link )
);

$blueprint_link_html = sprintf(
	'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom","opensInNewTab":true} /-->',
	__( 'Load in Playground', 'wporg-plugins' ),
	esc_url( get_blueprint_url( get_download_link( $current_version ) ) )
);

$changes_link_html     = '';
$releases         = get_releases();
$previous_version = get_previous_version( $release_post, $releases );

if ( null !== $previous_version ) {
	$changes_link_html = sprintf(
		'<!-- wp:navigation-link {"label":"%1$s","url":"%2$s","kind":"custom","opensInNewTab":true} /-->',
		__( 'View changes', 'wporg-plugins' ),
		esc_url( get_trac_changeset_link( $previous_version, $current_version ) )
	);
}

$navigation = sprintf(
	'<!-- wp:navigation {"overlayMenu":"never","openSubmenusOnClick":true,"ariaLabel":"%1$s","className":"wporg-release-menu-options"} -->%2$s<!-- /wp:navigation -->',
	__( 'Release options', 'wporg-plugins' ),
	sprintf(
		'<!-- wp:navigation-submenu {"label":"%1$s"} -->%2$s%3$s%4$s<!-- /wp:navigation-submenu -->',
		__( 'Release options', 'wporg-plugins' ),
		$download_link_html,
		$blueprint_link_html,
		$changes_link_html
	)
);

echo do_blocks( $navigation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
