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
						<?php the_content( 'Read on for more &raquo;', 'rosetta' ); ?>
					</div>

					<?php comments_template(); ?>
				<?php endwhile;
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
