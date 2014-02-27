<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
			<h3 id="post-<?php the_ID(); ?>"><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h3>
			<cite><?php printf( __( 'Published on %s by %s', 'bbporg' ), get_the_time( 'F jS, Y' ), get_the_author_link() ); ?></cite>
			<div class="single-post" id="post-<?php the_ID(); ?>"><?php the_content( __( 'Read more &rarr;' ) ); ?></div>
<?php endwhile; if ( get_next_posts_link() ) : ?>
			<div class="nav-previous alignleft"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts' ) ); ?></div>
<?php endif; if ( get_previous_posts_link() ) : ?>
			<div class="nav-next alignright"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>' ) ); ?></div>
<?php endif; else : ?>
			<p><em><?php _e( 'Sorry, no posts matched your criteria.' ); ?></em></p>
<?php endif; ?>
			<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>