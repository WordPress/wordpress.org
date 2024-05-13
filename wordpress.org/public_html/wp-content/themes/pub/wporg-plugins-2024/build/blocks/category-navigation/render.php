<?php
namespace WordPressdotorg\Theme\Plugins_2024\CategoryNavigation;

global $wp_query;

if ( isset( $wp_query ) && ( ! empty( $wp_query->query_vars['browse'] ) && 'favorites' == $wp_query->query_vars['browse'] ) ) {
	return '';
}

echo do_blocks( '<!-- wp:navigation {"menuSlug":"section-bar","ariaLabel":"'. esc_attr( 'Category menu', 'wporg-plugins' ) .'","overlayMenu":"never","layout":{"type":"flex","orientation":"horizontal","justifyContent":"left","flexWrap":"nowrap"},"fontSize":"small","className":"is-style-button-list"} /-->' );
