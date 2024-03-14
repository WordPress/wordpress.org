<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/forums-views',
		array(
			'title'   => __( 'Forums Views', 'wporg-forums' ),
			'content' => sprintf(
				'<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%%"},"fontSize":"small"} -->
				<div class="wp-block-group is-style-cards-grid has-small-font-size">%s</div>
				<!-- /wp:group -->',
				wporg_support_get_views(),
			),
		)
	);
}

?>
