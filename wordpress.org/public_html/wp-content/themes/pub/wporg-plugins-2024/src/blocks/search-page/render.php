<?php
namespace WordPressdotorg\Theme\Plugins_2024\SearchPage;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() renders the block markup defined here; escaping it would print the markup.
echo do_blocks( <<<BLOCKS
	<!-- wp:template-part {"slug":"grid-controls"} /-->
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