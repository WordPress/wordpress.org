<?php get_header(); ?>
	<div class="outer" id="mid-wrapper">
		<div class="wrapper">
			<div class="section blog">
				<h3><?php _e('Blog', 'rosetta');?></h3>
			</div>
		</div>
	</div>
	<?php if (have_posts()) : ?>
		<div class="wrapper">
			<div class="section">
				<h2 class="fancy"><?php printf(__('Archives for %s', 'rosetta'), get_the_time(__('F, Y', 'rosetta')));?></h2>
				<a href="/#blog"><?php _e('&laquo; Back to blog', 'rosetta');?></a>
			</div>
		</div>
		<?php while (have_posts()) : the_post(); ?>
		<div class="wrapper">
		<div class="section blog">
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

				<p><small><em><?php the_time(__('F j, Y', 'rosetta')); ?>, <?php the_author_link(); ?></em></small></p>

				<?php the_content(__('Read the rest of this entry &raquo;', 'rosetta')); ?>

				<?php comments_popup_link(); ?>
			<div class="navigation">
				<div class="nav-previous"><?php previous_posts_link(__('&larr; Older Posts', 'rosetta')) ?></div>
				<div class="nav-next"><?php next_posts_link(__('Newer Posts &rarr;', 'rosetta')) ?></div>
			</div>

		</div>
		</div>

		<?php endwhile; ?>
	<?php endif; ?>
<?php get_footer(); ?>
