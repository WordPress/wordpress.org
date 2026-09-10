<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	<h2 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h2>
	<cite><?php
		printf(
			/* translators: 1: Publication date, 2: Author link. */
			esc_html__( 'Published on %1$s by %2$s', 'bborg' ),
			esc_html( get_the_time( 'F jS, Y' ) ),
			wp_kses_post( get_the_author_link() )
		);
	?></cite>
	<div class="single-post" id="post-<?php the_ID(); ?>"><?php the_content( __( 'Read more &rarr;', 'bborg' ) ); ?></div>

	<hr class="hidden" />

	<?php comments_template(); ?>

<?php endwhile; else : ?>

	<p><em><?php esc_html_e( 'Sorry, no posts matched your criteria.', 'bborg' ); ?></em></p>

<?php endif; ?>
<?php get_sidebar(); get_footer(); ?>
