<?php
get_header();
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>
			<h2 id="post-<?php the_ID(); ?>"><a href="<?php the_permalink() ?>" rel="bookmark"><?php the_title(); ?></a></h2>
			<cite>
				<?php
				printf(
					/* translators: 1: Publication date, 2: Author link. */
					esc_html__( 'Published on %1$s by %2$s', 'bborg' ),
					esc_html( get_the_time( 'F jS, Y' ) ),
					wp_kses_post( get_the_author_link() )
				);
				?>
			</cite>
			<div class="single-post archive" id="post-<?php the_ID(); ?>"><?php the_excerpt(); ?></div>
		<?php
	endwhile;

	the_posts_pagination( array(
		'mid_size'           => 2,
		'prev_text'          => '<span class="screen-reader-text">' . __( 'Previous page', 'bborg' ) . '</span> &larr;',
		'next_text'          => '<span class="screen-reader-text">' . __( 'Next page', 'bborg' ) . '</span> &rarr;',
		'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'bborg' ) . ' </span>',
	) );
else : ?>
	<p><em><?php esc_html_e( 'Sorry, no posts matched your criteria.', 'bborg' ); ?></em></p>
<?php endif; ?>
	<hr class="hidden" />
<?php
get_sidebar();
get_footer();
