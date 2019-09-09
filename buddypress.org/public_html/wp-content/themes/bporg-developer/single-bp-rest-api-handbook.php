<?php namespace DevHub;
/**
 * The Template for displaying all single posts.
 *
 * @package bporg-developer
 * @since 1.0.0
 */

get_header(); ?>

<?php get_sidebar( 'handbook' ); ?>

		<main id="primary" <?php post_class( 'site-main' ); ?> role="main">

		<?php breadcrumb_trail(); ?>

		<?php while ( have_posts() ) : the_post(); ?>

			<?php get_template_part( 'content', 'handbook' ); ?>

			<?php \WPorg_Handbook_Navigation::show_nav_links( 'BP REST API Table of Contents' ); ?>

		<?php endwhile; // end of the loop. ?>

		</main><!-- #main -->

<?php get_footer(); ?>
