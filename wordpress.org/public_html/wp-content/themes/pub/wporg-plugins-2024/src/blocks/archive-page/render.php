<?php
namespace WordPressdotorg\Theme\Plugins_2024\ArchivePage;

global $wp_query;

// If we don't have any posts to display for the archive, then send a 404 status. See #meta4151
if ( ! $wp_query->have_posts() ) {
	status_header( 404 );
	nocache_headers();
}

$container_top_padding = '40';

if ( 'favorites' == get_query_var( 'browse' ) ) {
	echo do_blocks(  <<<BLOCKS
	<!-- wp:group {"align":"full","layout":{"type":"default"}} -->
		<div class="wp-block-group alignfull">
		<!-- wp:pattern {"slug":"wporg-plugins-2024/full-width-search"} /-->
		</div>
	<!-- /wp:group -->
	BLOCKS);

	$container_top_padding = '30';
}

// TODO: There's no block for this.
$archive_description = get_the_archive_description();

echo do_blocks( <<<BLOCKS
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|$container_top_padding"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--$container_top_padding)">
	<!-- wp:template-part {"slug":"grid-controls"} /-->
	<!-- wp:query-title {"type":"archive","fontFamily":"inter","style":{"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"bottom":"var:preset|spacing|10"}}},"fontSize":"heading-5"} /-->
	{$archive_description}
	<!-- wp:query {"tagName":"div","className":"plugin-cards"} -->
	<div class="wp-block-query plugin-cards">
			<!-- wp:post-template {"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"48%"}} -->
				<!-- wp:wporg/plugin-card /-->
			<!-- /wp:post-template -->
		</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
BLOCKS
);

if ( ! have_posts() ) {
	get_template_part( 'template-parts/no-results' );
}

the_posts_pagination();
