<?php
namespace WordPressdotorg\Theme\Plugins_2024\CategoryNavigation;

global $wp_query;

if ( isset( $wp_query ) && ( ! empty( $wp_query->query_vars['browse'] ) && 'favorites' == $wp_query->query_vars['browse'] ) ) {
	return '';
}

if ( ! is_search() && isset( $_GET['show_filters'] ) ) {
	$filter_blocks = <<<FILTERS
	<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"className":"wporg-query-filters","layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group wporg-query-filters">
		<!-- wp:wporg/query-filter {"key":"business_model","multiple":false} /-->
		<!-- wp:wporg/query-filter {"key":"plugin_category"} /-->
		<!-- wp:wporg/query-filter {"key":"sort","multiple":false} /-->
	</div>
	<!-- /wp:group -->
	FILTERS;

	echo do_blocks( $filter_blocks );

	return;
}

echo do_blocks( '<!-- wp:navigation {"menuSlug":"section-bar","ariaLabel":"'. esc_attr( 'Category menu', 'wporg-plugins' ) .'","overlayMenu":"never","layout":{"type":"flex","orientation":"horizontal","justifyContent":"left","flexWrap":"nowrap"},"fontSize":"small","className":"is-style-button-list"} /-->' );
