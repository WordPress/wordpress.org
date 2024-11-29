<?php

\WordPressdotorg\skip_to( '#primary' );

echo do_blocks( '<!-- wp:wporg/global-header {"style":{"border":{"bottom":{"color":"var:preset|color|white-opacity-15","style":"solid","width":"1px"}}}} /-->' );

if ( is_front_page() && is_home() ) {
	echo do_blocks( '<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

		<!-- wp:site-title {"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"fontSize":"small","fontFamily":"inter"} /-->

		<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"breathe"} /-->

	<!-- /wp:wporg/local-navigation-bar -->' );
} else {
	/**
	 * On the project and updates sites, update the text and link for the site title so that
	 * it appears as if pages from these sites belong to the home site, and not separate blogs.
	 */
	$site = get_site();
	$make_home_url = 'https://' . $site->domain;
	$is_updates_or_project = '/updates/' === $site->path || '/project/' === $site->path;

	ob_start();
	do_action('wporg_breathe_before_name', 'nonfront');
	$before_name = ob_get_clean();

	echo do_blocks(
		sprintf(
			'<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="wp-block-site-title has-small-font-size"><a href="%1$s">%2$s%3$s</a></p>
				<!-- /wp:paragraph -->

				<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"breathe"} /-->

			<!-- /wp:wporg/local-navigation-bar -->',
			esc_url( $is_updates_or_project ? $make_home_url : home_url() ),
			$before_name,
			$is_updates_or_project
				? esc_html__( 'Make WordPress', 'wporg-breathe' )
				: esc_html( get_bloginfo('name') )
		)
	);
}

do_action( 'wporg_breathe_after_header' );

?>

<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>

	<div id="main" class="site-main clear">
