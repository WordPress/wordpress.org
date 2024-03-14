<?php
/**
 * bbPress main template file.
 *
 * @package WPBBP
 */

get_header(); ?>

<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">

		<div class="entry-content">
			<?php while ( have_posts() ) : the_post(); ?>

				<?php the_content(); ?>

			<?php endwhile; ?>
		</div>

		<?php get_sidebar(); ?>

	</div>

</main>

<?php get_footer();
