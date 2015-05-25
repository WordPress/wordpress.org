<?php get_header(); ?>

<div id="pagebody">
	<div class="wrapper">
		<div class="col-9">
			<?php
			if ( have_posts()) :
				while (have_posts() ) : the_post(); ?>
					<h2 class="fancy"><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h2>

					<div class="meta">
						<?php rosetta_entry_meta(); ?>
					</div>

					<div class="storycontent">
						<?php the_content( __( 'Read on for more &raquo;', 'rosetta' ) ); ?>
					</div>

					<div class="feedback">
						<?php comments_popup_link(); ?>
					</div>

					<?php comments_template(); ?>
				<?php endwhile;
			else: ?>
				<p><?php _e( 'Sorry, no posts matched your criteria.', 'rosetta' ); ?></p>
			<?php endif; ?>

			<nav class="posts-navigation">
				<?php posts_nav_link( ' &#8212; ', __( '&laquo; Newer Posts', 'rosetta' ), __( 'Older Posts &raquo;', 'rosetta' ) ); ?>
			</nav>
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
