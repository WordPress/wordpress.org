<?php
namespace WordPressdotorg\Theme\Plugins_2024\FilterBar;

global $wp_query;

if ( isset( $wp_query ) && ( ! empty( $wp_query->query_vars['browse'] ) && 'favorites' === $wp_query->query_vars['browse'] ) ) {
	return '';
}

$filter_blocks = <<<FILTERS
	<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"className":"wporg-query-filters","layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group wporg-query-filters">
		<!-- wp:wporg/query-filter {"key":"business_model","multiple":false} /-->
		<!-- wp:wporg/query-filter {"key":"sort","multiple":false} /-->
	</div>
	<!-- /wp:group -->
	FILTERS;

// Temporarily disable the filters.
$filter_blocks = '';

$search_placeholder = esc_attr__( 'Search plugins', 'wporg-plugins' );
$search_button      = esc_attr__( 'Search plugins', 'wporg-plugins' );

echo do_blocks( <<<BLOCKS
	<!-- wp:group {"align":"wide","className":"wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count">
		<!-- wp:group {"className":"wporg-plugins__filters__search","layout":{"type":"flex","flexWrap":"wrap"}} -->
		<div class="wp-block-group wporg-plugins__filters__search">
			<!-- wp:search {"showLabel":false,"placeholder":"{$search_placeholder}","width":100,"widthUnit":"%","buttonText":"{$search_button}","buttonPosition":"button-inside","buttonUseIcon":true,"className":"is-style-secondary-search-control"} /-->
			<!-- wp:query {"inherit":true} -->
				<!-- wp:wporg/query-total /-->
			<!-- /wp:query -->
		</div>
		<!-- /wp:group -->
		{$filter_blocks}
	</div>
	<!-- /wp:group -->
	BLOCKS
);