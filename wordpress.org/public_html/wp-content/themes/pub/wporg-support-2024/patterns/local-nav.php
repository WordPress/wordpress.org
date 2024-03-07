<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/local-nav',
		array(
			'title'   => __( 'Local Nav', 'wporg-forums' ),
			'content' => sprintf(
				'<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

					<!-- wp:paragraph {"fontSize":"small"} -->
					<p class="wp-block-site-title has-small-font-size"><a href="%s">%s</a></p>
					<!-- /wp:paragraph -->

					<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"forums"} /-->

				<!-- /wp:wporg/local-navigation-bar -->',
				esc_url( home_url( '/forums/' ) ),
				esc_html__( 'Forums', 'wporg-forums' ),
			),
		)
	);
}

?>
