<?php get_header(); ?>

<div id="pagebody">
	<div class="wrapper">
		<div class="col-9">
			<?php
			if ( have_posts()) :
				while (have_posts() ) : the_post(); ?>
					<h2 class="fancy"><?php the_title(); ?></h2>

					<div class="meta">
						<?php rosetta_entry_meta(); ?>
					</div>

					<div class="storycontent">
						<?php the_content(); ?>
					</div>

					<?php
					// Previous/next post navigation.
					the_post_navigation( array(
						'next_text' => '<span class="meta-nav" aria-hidden="true">' . __( 'Next', 'rosetta' ) . '</span> ' .
							'<span class="screen-reader-text">' . __( 'Next post:', 'rosetta' ) . '</span> ' .
							'<span class="post-title">%title</span>',
						'prev_text' => '<span class="meta-nav" aria-hidden="true">' . __( 'Previous', 'rosetta' ) . '</span> ' .
							'<span class="screen-reader-text">' . __( 'Previous post:', 'rosetta' ) . '</span> ' .
							'<span class="post-title">%title</span>',
					) );

					// If comments are open or we have at least one comment, load up the comment template.
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}

				endwhile;
			else: ?>
				<p><?php _e( 'Sorry, no posts matched your criteria.', 'rosetta' ); ?></p>
			<?php endif; ?>

			<?php posts_nav_link(' &#8212; ', __( '&laquo; Newer Posts', 'rosetta' ), __( 'Older Posts &raquo;', 'rosetta' ) ); ?>
		</div>
		<div class="col-3">
			<div class="blog-categories">
				<h4><?php _e( 'Categories', 'rosetta' ); ?></h4>
				<ul>
					<?php wp_list_categories( 'title_li=&show_count=1&orderby=count&order=DESC&number=10' ); ?>
				</ul>
			</div>
		</div>
	</div>
</div>

<?php get_footer();
