<?php get_header(); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<h2 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h2>
<?php
	the_content( esc_html__( 'Read more &raquo;', 'bborg' ) );
	wp_link_pages( array(
		'before' => '<p>' . esc_html__( 'Pages:', 'bborg' ),
		'after'  => "</p>\n",
		'next_or_number' => 'number',
	) );
	edit_post_link( esc_html__( 'Edit', 'bborg' ), '<p>', '</p>' );
?>
<?php endwhile;  endif;?>
				<hr class="hidden" />
<?php get_sidebar(); get_footer(); ?>
