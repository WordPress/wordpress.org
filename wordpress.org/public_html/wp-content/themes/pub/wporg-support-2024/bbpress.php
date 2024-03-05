<?php
/**
 * bbPress main template file.
 *
 * @package WPBBP
 */

get_header(); ?>

<main id="main" class="site-main" role="main">
	
	<div class="entry-content">
		<?php while ( have_posts() ) : the_post(); ?>

			<?php the_content(); ?>

		<?php endwhile; ?>
	</div>

	<?php get_sidebar(); ?>
</main>

<?php get_footer();
