<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
			<h2 id="post-<?php the_ID(); ?>"><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h2>
			<cite><?php
				printf(
					/* translators: 1: Publication date, 2: Author link. */
					esc_html__( 'Published on %1$s by %2$s', 'bporg' ),
					esc_html( get_the_time( 'F jS, Y' ) ),
					wp_kses_post( get_the_author_link() )
				);
			?></cite>
			<div class="single-post" id="post-<?php the_ID(); ?>"><?php the_excerpt(); ?></div>
<?php endwhile;  ?>

<?php else : ?>
			<p><em><?php esc_html_e( 'Sorry, no posts matched your criteria.' ); ?></em></p>
<?php endif; ?>
			<?php posts_nav_link(' &#8212; ', __('Newer &rarr;'), __('&larr; Older') ); ?>
			<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>
