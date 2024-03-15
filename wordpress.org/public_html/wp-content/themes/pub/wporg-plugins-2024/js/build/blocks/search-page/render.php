
<main id="main" class="site-main alignwide" role="main">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			printf(
				/* translators: Search query. */
				esc_html__( 'Showing results for: %s', 'wporg-plugins' ),
				'<strong>' . get_search_query() . '</strong>'
			);
			?>
		</h1>
		<?php
		if ( get_query_var( 'block_search' ) ) {
			printf(
				/* translators: %s: Search URL */
				'<p>' . __( 'Searching the block directory. <a href="%s">Search all plugins</a>.', 'wporg-plugins' ) . '</p>',
				remove_query_arg( 'block_search' )
			);
		}
		?>
	</header><!-- .page-header -->

	<?php
	echo do_blocks( <<<BLOCKS
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
</main>