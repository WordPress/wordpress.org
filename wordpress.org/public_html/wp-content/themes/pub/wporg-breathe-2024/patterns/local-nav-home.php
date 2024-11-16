<?php
ob_start();
do_action('wporg_breathe_before_name', 'front');
$before_name = ob_get_clean();

if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-breathe/local-nav-home',
		array(
			'title'   => __( 'Local Nav Home', 'wporg-breathe' ),
			'content' => sprintf(
				'<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

					<!-- wp:site-title {"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"fontSize":"small","fontFamily":"inter"} /-->

					<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"breathe"} /-->

				<!-- /wp:wporg/local-navigation-bar -->',
				esc_url( home_url() ),
				$before_name,
				esc_html( get_bloginfo('name') )
			),
		)
	);
}
