<?php namespace DevHub;

/**
 * The template for displaying Archive pages.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="content-area">

		<?php breadcrumb_trail(); ?>

		<main id="main" class="site-main" role="main">

			<?php if ( have_posts() ) : ?>


				<?php /* Start the Loop */ ?>
				<?php while ( have_posts() ) : the_post(); ?>

					<?php get_template_part( 'content', ( is_parsed_post_type() ? 'reference-archive' : '' ) ); ?>

				<?php endwhile; ?>

				<?php //wporg_developer_paging_nav(); ?>

			<?php else : ?>

				<?php get_template_part( 'content', 'none' ); ?>

			<?php endif; ?>
			<?php loop_pagination(); ?>
		</main>
		<!-- /wrapper -->
	</div><!-- /pagebody -->

<?php get_footer(); ?>