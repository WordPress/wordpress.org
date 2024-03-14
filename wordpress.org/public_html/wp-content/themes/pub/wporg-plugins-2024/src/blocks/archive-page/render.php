<?php

global $wp_query;

// If we don't have any posts to display for the archive, then send a 404 status. See #meta4151
if ( ! $wp_query->have_posts() ) {
	status_header( 404 );
	nocache_headers();
}

?>

<main id="main" class="site-main alignwide" role="main">

	<header class="page-header">
		<?php
		the_archive_title( '<h1 class="page-title">', '</h1>' );
		the_archive_description( '<div class="taxonomy-description">', '</div>' );
		?>
	</header>

	<?php

	// NOTE: wp-block-group-is-layout-grid is here as `wp-block-query-is-layout-grid`  is not supported yet by `wporg/link-wrapper`.
	echo do_blocks( <<<BLOCKS
		<!-- wp:query {"tagName":"div","className":"plugin-cards"} -->
		<div class="wp-block-query plugin-cards">
				<!-- wp:post-template {"className":"is-style-cards-grid wp-block-group-is-layout-grid","layout":{"type":"grid","minimumColumnWidth":"48%"}} -->
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
</main><!-- #main -->