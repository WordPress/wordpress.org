<?php

global $wp_query;

// If we don't have any posts to display for the archive, then send a 404 status. See #meta4151
if ( ! $wp_query->have_posts() ) {
	status_header( 404 );
	nocache_headers();
}

echo do_blocks( <<<BLOCKS
	<!-- wp:navigation {"menuSlug":"section-bar","className":"is-style-button-list","fontSize":"small"} /-->
	<!-- wp:wporg/filter-bar /-->
	<!-- wp:query {"tagName":"div","className":"plugin-cards"} -->
	<div class="wp-block-query plugin-cards">
			<!-- wp:post-template {"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"48%"}} -->
				<!-- wp:wporg/plugin-card /-->
			<!-- /wp:post-template -->
		</div>
	<!-- /wp:query -->
BLOCKS
);

if ( ! have_posts() ) {
	get_template_part( 'template-parts/no-results' );
}

the_posts_pagination();

?>