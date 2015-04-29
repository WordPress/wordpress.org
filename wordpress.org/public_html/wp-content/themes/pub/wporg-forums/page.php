<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package _s
 */

get_header(); ?>

<div id="pagebody">
	<div class="wrapper">
		<div id="lang-guess-wrap" style="margin-bottom: 1em;"></div>

			<?php while ( have_posts() ) : the_post(); ?>

				<?php the_content(); ?>

			<?php endwhile; // end of the loop. ?>

	</div>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>