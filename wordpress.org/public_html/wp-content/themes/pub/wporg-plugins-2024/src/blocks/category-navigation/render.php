<?php
namespace WordPressdotorg\Theme\Plugins_2024\CategoryNavigation;

global $wp_query;

if ( isset( $wp_query ) && ( ! empty( $wp_query->query_vars['browse'] ) && 'favorites' == $wp_query->query_vars['browse'] ) ) {
	return '';
}

$menu = '<!-- wp:navigation {"menuSlug":"section-bar","ariaLabel":"'. esc_attr( 'Category menu', 'wporg-plugins' ) .'","overlayMenu":"never","layout":{"type":"flex","orientation":"horizontal","justifyContent":"left","flexWrap":"nowrap"},"fontSize":"small","className":"is-style-button-list"} /-->';

echo do_blocks( <<<BLOCKS
	<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--40)">
	$menu
	</div><!-- /wp:group -->
	BLOCKS
);