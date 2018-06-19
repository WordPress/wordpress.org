<?php

namespace WordCamp\Gutenberg_Tweaks;

defined( 'WPINC' ) || die();

/*
 * Customize Gutenberg for WordCamp.org
 *
 *   WARNING: This is tied to Gutenberg's hooks, which are likely to evolve quickly. This needs to
 *   be tested and updated before pushing Gutenberg updates to production.
 *
 * Most of this deals with preventing Gutenberg from being the default editor for now.
 *
 * We want the beta plugin to be available for all organizers to use on an opt-in basis, but don't
 * want to aggressively push them to use it if they don't want to.
 *
 * @see https://make.wordpress.org/community/2017/09/25/gutenberg-on-wordcamp-sites/#comment-24596
 *
 * @todo: Most of this can be removed once Gutenberg is refined enough to become the default editor
 *        for organizers.
 */
function load() {
	if ( is_plugin_active( 'gutenberg/gutenberg.php' ) ) {
		add_filter( 'gutenberg_can_edit_post_type', __NAMESPACE__ . '\disable_gutenberg_on_cpts',           10, 2 );
		add_filter( 'get_edit_post_link',           __NAMESPACE__ . '\add_classic_param_to_edit_links'            );
		add_filter( 'page_row_actions',             __NAMESPACE__ . '\add_gutenberg_edit_link',             10, 2 );
		add_filter( 'post_row_actions',             __NAMESPACE__ . '\add_gutenberg_edit_link',             10, 2 );
		add_action( 'admin_print_scripts-edit.php', __NAMESPACE__ . '\add_classic_param_to_add_new_button', 11    );
		add_action( 'admin_print_scripts',          __NAMESPACE__ . '\add_classic_param_to_admin_menus',     1    );
	}
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );

/**
 * Limit which post types are editable by Gutenberg
 *
 * Many of WordCamp.org's CPTs make extensive use of meta boxes. Since these are not currently supported in the
 * Gutenberg editor, this limits the Gutenberg content editing links to posts and pages.
 *
 * @todo: Revisit this when Gutenberg supports "advanced" meta boxes.
 */
function disable_gutenberg_on_cpts( $bool, $post_type ) {
	return in_array( $post_type, array( 'post', 'page' ), true );
}

// Add the `classic-editor` trigger to all edit post URLs
function add_classic_param_to_edit_links( $url ) {
	return add_query_arg( 'classic-editor', '', $url );
}

// Add a "Gutenberg Editor" link to the post hover actions on the "All {Posts|Pages}" screen.
function add_gutenberg_edit_link( $actions, $post ) {
	if ( ! gutenberg_can_edit_post( $post ) ) {
		return $actions;
	}

	remove_filter( 'get_edit_post_link', __NAMESPACE__ . '\add_classic_param_to_edit_links', 10 );
	$edit_url = get_edit_post_link( $post->ID, 'raw' );
	add_filter( 'get_edit_post_link', __NAMESPACE__ . '\add_classic_param_to_edit_links', 10 );

	$title = _draft_or_post_title( $post->ID );
	$edit_action = array(
		'gutenberg' => sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $edit_url ),
			esc_attr( sprintf( 'Edit &#8220;%s&#8221; in the Gutenberg editor', $title ) ),
			'Gutenberg Editor'
		),
	);

	// Insert the Gutenberg Edit action after the Edit action.
	$edit_offset = array_search( 'edit', array_keys( $actions ), true );
	$actions     = array_merge(
		array_slice( $actions, 0, $edit_offset + 1 ),
		$edit_action,
		array_slice( $actions, $edit_offset + 1 )
	);

	return $actions;
}

// Add the `classic-editor` trigger to the modified Add New button on the "All {Posts|Pages" screen.
function add_classic_param_to_add_new_button() {
	?>

	<script type="text/javascript">
		document.addEventListener( 'DOMContentLoaded', function() {
			jQuery( '.split-page-title-action' ).children( 'a' ).each( function( index, element ) {
				var href = jQuery( element ).attr( 'href' );
				if ( ! href.match(/\?/) ) {
					jQuery( element ).attr( 'href', href + '?classic-editor' );
				} else {
					jQuery( element ).attr( 'href', href + '&classic-editor' );
				}
			} );
		} );
	</script>

	<?php
}

// Add the `classic-editor` trigger to post/page links in the admin menus
// This is an ugly hack, but I'm not seeing a better way to do it, and this'll be thrown out soon anyway.
function add_classic_param_to_admin_menus() {
	?>

	<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			var $ = jQuery.noConflict();
			var adminBarNewContentLink = $( '#wp-admin-bar-new-content' ).children( 'a' ),
				adminBarNewPostLink    = $( '#wp-admin-bar-new-post'    ).children( 'a' ),
				adminBarNewPageLink    = $( '#wp-admin-bar-new-page'    ).children( 'a' ),
				adminMenuNewPostLink   = $( '#menu-posts'    ).children( '.wp-submenu' ).children( 'li' ).children( 'a[href="post-new.php"]' ),
				adminMenuNewPageLink   = $( '#menu-pages'    ).children( '.wp-submenu' ).children( 'li' ).children( 'a[href="post-new.php?post_type=page"]' ),
				editPostAddNewLink     = $( '.page-title-action[href$="post-new.php"]' ),
				editPageAddNewLink     = $( '.page-title-action[href$="post-new.php?post_type=page"]' );

			// Admin Bar
			$( adminBarNewContentLink ).attr( 'href', jQuery( adminBarNewContentLink ).attr( 'href' ) + '?classic-editor' );
			$( adminBarNewPostLink ).attr(    'href', jQuery( adminBarNewPostLink    ).attr( 'href' ) + '?classic-editor' );
			$( adminBarNewPageLink ).attr(    'href', jQuery( adminBarNewPageLink    ).attr( 'href' ) + '&classic-editor' );

			// Admin sidebar menu
			$( adminMenuNewPostLink ).attr( 'href', jQuery( adminMenuNewPostLink ).attr( 'href' ) + '?classic-editor' );
			$( adminMenuNewPageLink ).attr( 'href', jQuery( adminMenuNewPageLink ).attr( 'href' ) + '&classic-editor' );

			// "Add New" links on "Edit {Post|Page}" screens
			$( editPostAddNewLink ).attr( 'href', jQuery( editPostAddNewLink ).attr( 'href' ) + '?classic-editor' );
			$( editPageAddNewLink ).attr( 'href', jQuery( editPageAddNewLink ).attr( 'href' ) + '&classic-editor' );
		} );
	</script>

	<?php
}
