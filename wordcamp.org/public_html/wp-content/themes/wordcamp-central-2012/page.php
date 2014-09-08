<?php
/**
 * Template for displaying pages.
 */
get_header(); ?>

	<div id="container">
		<div id="content" role="main">

			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>

							<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
								<h1 class="entry-title"><?php the_title(); ?></h1>
								<div class="entry-content">
									<?php the_content(); ?>
									<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) ); ?>
								</div><!-- .entry-content -->
							</div><!-- #post-## -->

				<?php endwhile; // have_posts ?>
			<?php endif; ?>
			
		</div><!-- #content -->
	</div><!-- #container -->

	<?php get_sidebar( 'page' ); ?>

<?php get_footer(); ?>
