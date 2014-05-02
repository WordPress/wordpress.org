<?php namespace DevHub;
/**
 * The template for displaying Search Results pages.
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="content-area">

		<header class="page-header">
			<h1 class="page-title"><?php printf( __( 'Search Results for: %s', 'wporg' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
		</header><!-- .page-header -->
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) : ?>

			<?php /* Start the Loop */ ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'content', 'reference' ); ?>

			<?php endwhile; ?>

			<?php wporg_developer_paging_nav(); ?>

		<?php else : ?>

			<?php get_template_part( 'content', 'none' ); ?>

		<?php endif; ?>

		</main><!-- #main -->
		<?php //get_sidebar(); ?>
	</div><!-- #primary -->
<?php get_footer(); ?>
