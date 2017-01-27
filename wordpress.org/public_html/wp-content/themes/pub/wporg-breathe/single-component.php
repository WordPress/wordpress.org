<?php
/**
 * Template for component pages, for make/core.
 */
?>
<?php get_header(); ?>

<div id="primary" class="content-area">
	<div class="site-content">
	<div role="main">
		<h2><?php the_title(); ?> component</h2>

		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<div class="entry-content">
						<?php the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'p2-breathe' ) ); ?>
						<?php wp_link_pages( array( 'before' => '<div class="page-links">' . __( 'Pages:', 'p2-breathe' ), 'after' => '</div>' ) ); ?>
					</div>
				</article>
			<?php endwhile; ?>

		<?php endif; ?>

		</div>
		</div><!-- #content -->

	</div><!-- #primary -->
	<div id="primary-modal"></div>

	<!-- A fake o2 content area -->
	<div style="display: none;"><div id="content"></div></div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
