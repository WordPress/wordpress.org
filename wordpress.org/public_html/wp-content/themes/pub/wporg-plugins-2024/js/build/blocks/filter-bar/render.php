<?php

if ( in_array( get_query_var( 'browse' ), [ 'beta', 'featured' ] ) ) {
	return;
}

echo do_blocks(
	'<!-- wp:group {"align":"wide","className":"wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count">
		<!-- wp:group {"className":"wporg-plugins__filters__search","layout":{"type":"flex","flexWrap":"wrap"}} -->
		<div class="wp-block-group wporg-plugins__filters__search">
			<!-- wp:search {"showLabel":false,"placeholder":"' . esc_attr__( 'Search plugins', 'wporg-plugins' ) . '","width":100,"widthUnit":"%","buttonText":"' . esc_attr__( 'Search plugins', 'wporg-plugins' ) . '","buttonPosition":"button-inside","buttonUseIcon":true,"className":"is-style-secondary-search-control"} /-->
			<!-- wp:query {"inherit":true} -->
				<!-- wp:wporg/query-total /-->
			<!-- /wp:query -->
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"className":"wporg-query-filters","layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group wporg-query-filters">
			<!-- wp:wporg/query-filter {"key":"plugin_category"} /-->
			<!-- wp:wporg/query-filter {"key":"business_model","multiple":false} /-->
			<!-- wp:wporg/query-filter {"key":"sort","multiple":false} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->'
);