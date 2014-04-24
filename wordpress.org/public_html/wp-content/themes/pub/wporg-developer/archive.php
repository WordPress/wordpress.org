<?php namespace DevHub;

/**
 * The template for displaying Archive pages.
 *
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
 *
 * @package wporg-developer
 */

get_header(); ?>

<div class="<?php body_class( 'pagebody' ) ?>">
	<div class="wrapper">
		<header class="page-header">
			<?php breadcrumb_trail(); ?>
		</header><!-- .page-header -->

		<?php if ( have_posts() ) : ?>


			<?php /* Start the Loop */ ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php
					/* Include the Post-Format-specific template for the content.
					 * If you want to override this in a child theme, then include a file
					 * called content-___.php (where ___ is the Post Format name) and that will be used instead.
					 */
					get_template_part( 'content', get_template_part_name() );
				?>

			<?php endwhile; ?>

			<?php //wporg_developer_paging_nav(); ?>

		<?php else : ?>

			<?php get_template_part( 'content', 'none' ); ?>

		<?php endif; ?>
		<?php loop_pagination(); ?>

	</div>
	<!-- /wrapper -->
</div><!-- /pagebody -->

<?php get_footer(); ?>