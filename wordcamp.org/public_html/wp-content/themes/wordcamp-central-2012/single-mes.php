<?php
/**
 * Single Multi-Event Sponsor post
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

							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail(); ?>
							<?php endif; ?>

						</div><!-- #post-## -->

					<?php endwhile; // have_posts ?>
				<?php endif; // have_posts ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>
