<?php
ob_start();
do_action('wporg_breathe_before_name', 'front');
$team_icon = ob_get_clean();

if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-breathe/local-nav',
		array(
			'title'   => __( 'Local Nav', 'wporg-breathe' ),
			'content' => sprintf(
				'<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

					<!-- wp:paragraph {"fontSize":"small"} -->
					<p class="wp-block-site-title has-small-font-size"><a href="%1$s">%2$s %3$s</a></p>
					<!-- /wp:paragraph -->

					<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"breathe"} /-->

				<!-- /wp:wporg/local-navigation-bar -->',
				esc_url( home_url() ),
				$team_icon,
				esc_html( get_bloginfo('name') )
			),
		)
	);
}
