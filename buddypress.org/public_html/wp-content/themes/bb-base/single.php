<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	<h3 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h3>
	<cite><?php
		/* translators: 1: post date, 2: post author */
		printf( __( 'Published on %1$s by %2$s', 'bborg' ),
			get_the_time( 'F jS, Y' ),
			get_the_author_link()
		);
	?></cite>
	<div class="single-post" id="post-<?php the_ID(); ?>"><?php the_content( __( 'Read more &rarr;', 'bborg' ) ); ?></div>

	<hr class="hidden" />

	<?php comments_template(); ?>

<?php endwhile; else : ?>

	<p><em><?php _e( 'Sorry, no posts matched your criteria.', 'bborg' ); ?></em></p>

<?php endif; ?>
<?php get_sidebar(); get_footer(); ?>
