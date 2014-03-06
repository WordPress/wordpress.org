<?php get_header(); ?>
	<div class="outer" id="mid-wrapper">
		<div class="wrapper">
			<div class="section blog">
				<h3><?php _e('Blog', 'rosetta');?></h3>
			</div>
		</div>
	</div>
	<?php if (have_posts()) : ?>
		<?php while (have_posts()) : the_post(); ?>
		<div class="wrapper">
		<div class="section blog">
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

				<p><small><em><?php the_time(__('F j, Y', 'rosetta')); ?>, <?php the_author_link(); ?></em></small></p>

				<?php the_content(__('Read the rest of this entry &raquo;', 'rosetta')); ?>

				<?php comments_template(); ?>
				<div class="navigation">
						<div class="nav-previous"><?php previous_post_link('&larr; %link') ?></div>
						<div class="nav-next"><?php next_post_link('%link &rarr;') ?></div>
				</div>

		</div>
		</div>

		<?php endwhile; ?>

	<?php endif; ?>
<?php get_footer(); ?>
