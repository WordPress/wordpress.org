<?php
namespace WordPressdotorg\Theme\Plugins_2024\SearchPage;

echo do_blocks( <<<BLOCKS
	<!-- wp:wporg/filter-bar /-->
	<!-- wp:wporg/category-navigation /-->
	<!-- wp:query-title {"type":"search","fontFamily":"inter","className":"section-heading"} /-->
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