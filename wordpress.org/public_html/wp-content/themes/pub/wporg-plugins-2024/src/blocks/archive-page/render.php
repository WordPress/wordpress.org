<?php
namespace WordPressdotorg\Theme\Plugins_2024\ArchivePage;

global $wp_query;

// If we don't have any posts to display for the archive, then send a 404 status. See #meta4151
if ( ! $wp_query->have_posts() ) {
	status_header( 404 );
	nocache_headers();
}	

// TODO: There's no block for this.
$archive_description = get_the_archive_description();

echo do_blocks( <<<BLOCKS
	<!-- wp:navigation {"menuSlug":"section-bar","className":"is-style-button-list","fontSize":"small"} /-->

	<!-- wp:query-title {"type":"archive","fontFamily":"inter"} /-->
	{$archive_description}

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
