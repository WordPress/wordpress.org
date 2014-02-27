<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<h3 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h3>
<?php the_content( __( 'Read more &raquo;' ) ); wp_link_pages( "before=<p>Pages:&after=</p>\n&next_or_number=number" ); edit_post_link( 'Edit', '<p>', '</p>' ); ?>
<?php endwhile;  endif;?>
				<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>
