<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/search-field',
		array(
			'title'   => __( 'Search Field', 'wporg-forums' ),
			'content' => sprintf(
				'<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
				<div id="wporg-search" class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--20)">

					<!-- wp:search {"label":"%1$s","showLabel":false,"placeholder":"%2$s","width":232,"widthUnit":"px","buttonText":"%1$s","buttonPosition":"button-inside","buttonUseIcon":true} /-->

				</div>
				<!-- /wp:group -->',
				esc_attr__( 'Search', 'wporg-forums' ),
				esc_attr__( 'Search forums', 'wporg-forums' ),
			),
		)
	);
}

?>
