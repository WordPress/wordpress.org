<?php
if ( function_exists( 'register_block_pattern' ) ) {
    register_block_pattern(
        'wporg-support/search-field',
        array(
            'title'       => __( 'Search Field', 'wporg-support' ),
            'content'     => sprintf(
				'<!-- wp:group {"style":{"spacing":{"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
				<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space)">

					<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
					<div id="wporg-search" class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--20)">

						<!-- wp:search {"label":"%1$s","showLabel":false,"placeholder":"%2$s","width":232,"widthUnit":"px","buttonText":"%1$s","buttonPosition":"button-inside","buttonUseIcon":true} /-->

					</div>
					<!-- /wp:group -->

				</div>
				<!-- /wp:group -->',
				esc_attr__( 'Search', 'wporg' ),
				esc_attr__( 'Search forums', 'wporg' ),
			),
		)
	);
}

?>
