<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/forums-homepage-header',
		array(
			'title'   => __( 'Forums Homepage Header', 'wporg-support' ),
			'content' => sprintf(
				'<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space"}}},"backgroundColor":"charcoal-2","className":"has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color","layout":{"type":"constrained"}} -->
				<div class="wp-block-group alignfull has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="padding-right:var(--wp--preset--spacing--edge-space);padding-left:var(--wp--preset--spacing--edge-space)">

					<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
					<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">

						<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"50px","fontStyle":"normal","fontWeight":"400"}},"fontFamily":"eb-garamond"} -->
						<h1 class="wp-block-heading has-eb-garamond-font-family" style="font-size:50px;font-style:normal;font-weight:400">%s</h1>
						<!-- /wp:heading -->

						<!-- wp:paragraph {"style":{"typography":{"lineHeight":"1.8"}},"textColor":"white"} -->
						<p class="has-white-color has-text-color" style="line-height:1.8">%s</p>
						<!-- /wp:paragraph -->

					</div>
					<!-- /wp:group -->

				</div>
				<!-- /wp:group -->

				<div id="lang-guess-wrap"></div>

				<!-- wp:group {"layout":{"type":"constrained","justifyContent":"center"},"style":{"border":{"bottom":{"color":"var:preset|color|light-grey-1","style":"solid","width":"1px"}},"spacing":{"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
				<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space);border-bottom:1px solid var(--wp--preset--color--light-grey-1)">

					<!-- wp:pattern {"slug":"wporg-support/search-field"} /-->

				</div>
				<!-- /wp:group -->',
				esc_html__( 'Forums', 'wporg-support' ),
				esc_html__( 'Learn how to help, or get help you need.', 'wporg-support' )
			),
		)
	);
}

?>
