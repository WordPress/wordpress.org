<?php

namespace WordCamp\Gutenberg_Tweaks;

defined( 'WPINC' ) || die();

add_filter( 'classic_editor_network_default_settings', __NAMESPACE__ . '\classic_editor_default_settings' );


/**
 * Configure the default settings for the Classic Editor
 */
function classic_editor_default_settings( $defaults ) {
	$defaults['editor']      = 'block';
	$defaults['allow-users'] = true;

	return $defaults;
}

/**
 * Disable block editor for WordCamp post types, since its not fully compatible yet.
 * In block editor flow, when an organizer who do not have permission to change status, but are author of the post, tries to edit a WordCamp post, "Publish" button gets replaced with "Submit for Review" button.
 * Since we do not support review flow for WordCamp Post Type, this has unintended consequences which includes post status being set to "Needs Vetting", there by accidentally changing WordCamp Status.
 *
 * @param string $status
 * @param string $post_type
 *
 * @return bool
 */
function disable_block_editor_for_wordcamp( $use_block_editor, $post_type ) {
	if ( ! defined( 'WCPT_POST_TYPE_ID' ) ) {
		return $use_block_editor;
	}
	return $use_block_editor && WCPT_POST_TYPE_ID !== $post_type;
}

add_filter( 'use_block_editor_for_post_type', __NAMESPACE__ . '\disable_block_editor_for_wordcamp', 10, 2 );
