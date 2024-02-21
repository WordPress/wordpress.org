<?php
if ( function_exists( 'register_block_pattern' ) ) {
    register_block_pattern(
        'wporg-support/forums-homepage-header',
        array(
            'title'       => __( 'forums-homepage-header', 'wporg-support' ),
            'content'     => sprintf(
				'<!-- wp:wporg/local-navigation-bar {"backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

					<!-- wp:html -->
					<div style="height:calc(var(--wp--custom--body--small--typography--line-height) * var(--wp--preset--font-size--small));" aria-hidden="true"></div>
					<!-- /wp:html -->

					<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"forums"} /-->

				<!-- /wp:wporg/local-navigation-bar -->

				<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space"}}},"backgroundColor":"charcoal-2","className":"has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color","layout":{"type":"constrained"}} -->
				<div class="wp-block-group alignfull has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="padding-right:var(--wp--preset--spacing--edge-space);padding-left:var(--wp--preset--spacing--edge-space)">

					<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
					<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">

						<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"50px","fontStyle":"normal","fontWeight":"400"}},"fontFamily":"eb-garamond"} -->
						<h1 class="wp-block-heading has-eb-garamond-font-family" style="font-size:50px;font-style:normal;font-weight:400">%s</h1>
						<!-- /wp:heading -->

						<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.3"}},"textColor":"white"} -->
						<p class="has-white-color has-text-color" style="line-height:2.3">%s</p>
						<!-- /wp:paragraph -->

					</div>
					<!-- /wp:group -->

				</div>
				<!-- /wp:group -->',
				esc_html__( 'Forums', 'wporg-support' ),
				esc_html__( 'Learn how to help, or get help you need.', 'wporg-support' )
			),
		)
	);
}

?>
