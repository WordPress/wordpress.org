<?php

namespace WordCamp\Gutenberg_Tweaks;

defined( 'WPINC' ) || die();

add_filter( 'gutenberg_can_edit_post_type', __NAMESPACE__ . '\disable_gutenberg_on_cpts',           10, 2 );


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
