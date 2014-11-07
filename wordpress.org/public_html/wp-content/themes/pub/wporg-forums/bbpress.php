<?php
/**
 * bbPress wrapper template.
 *
 * @package WPBBP
 */

get_header(); ?>

<div id="pagebody">
	<div class="wrapper">
		<div class="content">
			<div id="lang-guess-wrap" style="margin-bottom: 1em;"></div>

			<?php while ( have_posts() ) : the_post(); ?>

				<?php the_content(); ?>

			<?php endwhile; ?>

		</div>

		<?php get_sidebar(); ?>

	</div>
</div>

<?php get_footer();
