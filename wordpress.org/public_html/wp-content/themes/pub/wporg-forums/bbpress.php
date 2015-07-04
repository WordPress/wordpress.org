<?php
/**
 * bbPress wrapper template.
 *
 * @package WPBBP
 */

get_header(); ?>

<div id="pagebody">
	<div class="wrapper">
		<div class="col-12">
			<div class="content">
				<?php while ( have_posts() ) : the_post(); ?>

					<?php the_content(); ?>

				<?php endwhile; ?>
			</div>

			<?php get_sidebar(); ?>
		</div>

	</div>
</div>

<?php get_footer();
