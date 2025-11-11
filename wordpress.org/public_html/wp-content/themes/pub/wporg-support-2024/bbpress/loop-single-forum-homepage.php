<?php

echo do_blocks(
	sprintf( '
		<!-- wp:wporg/link-wrapper {"className":"wp-block-wporg-link-wrapper is-layout-flow wp-block-wporg-link-wrapper-is-layout-flow %1$s"} -->
		<a href="%2$s" title="%3$s" id="%4$s" class="wp-block-wporg-link-wrapper is-layout-flow wp-block-wporg-link-wrapper-is-layout-flow %1$s">

			<!-- wp:heading {"className":"has-inter-font-family has-normal-font-size"} -->
			<h3 class="wp-block-heading has-inter-font-family has-normal-font-size">%5$s</h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>%6$s</p>
			<!-- /wp:paragraph -->

		</a>
		<!-- /wp:wporg/link-wrapper -->',
		esc_attr( bbp_get_forum_class( bbp_get_forum_id() ) ),
		esc_url( bbp_get_forum_permalink() ),
		esc_attr( bbp_get_forum_title() ),
		esc_attr( 'bbp-forum-' . bbp_get_forum_id() ),
		esc_html( bbp_get_forum_title() ),
		esc_html( bbp_get_forum_content() )
	)
);
