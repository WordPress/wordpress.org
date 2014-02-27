<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
			<h3 id="post-<?php the_ID(); ?>"><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h3>
			<cite><?php printf( __( 'Published on %s by %s', 'bbporg' ), get_the_time( 'F jS, Y' ), get_the_author_link() ); ?></cite>
			<div class="single-post" id="post-<?php the_ID(); ?>"><?php the_excerpt(); ?></div>
			<?php $post_authors[get_the_author_meta( 'ID' )] += 1; ?>
<?php endwhile;  ?>
	
			<?php
				asort( $post_authors, SORT_NUMERIC );
				global $codex_contributors;
				$codex_contributors = array_reverse( $post_authors, true );
			?>

<?php else : ?>
			<p><em><?php _e( 'Sorry, no posts matched your criteria.' ); ?></em></p>
<?php endif; ?>
			<?php posts_nav_link(' &#8212; ', __('Newer &rarr;'), __('&larr; Older') ); ?>
			<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>