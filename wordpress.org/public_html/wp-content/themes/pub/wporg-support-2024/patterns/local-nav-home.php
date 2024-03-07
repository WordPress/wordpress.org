<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/local-nav-home',
		array(
			'title'   => __( 'Local Nav - Home', 'wporg-forums' ),
			'content' => '<!-- wp:wporg/local-navigation-bar {"backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

					<!-- wp:html -->
					<div style="height:calc(var(--wp--custom--body--small--typography--line-height) * var(--wp--preset--font-size--small));" aria-hidden="true"></div>
					<!-- /wp:html -->

					<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"forums"} /-->

				<!-- /wp:wporg/local-navigation-bar -->'
		),
	);
}

?>
