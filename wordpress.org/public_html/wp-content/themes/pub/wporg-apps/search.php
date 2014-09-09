<?php
/**
 * The template for displaying Search Results pages.
 *
 * @package wpmobileapps
 */

get_header(); ?>

	<section id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<div class="page-header-container">
					<h1 class="page-title"><?php printf( __( 'Search Results for: %s', 'wpmobileapps' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
				</div><!-- .page-header-container -->
			</header><!-- .page-header -->

			<?php /* Start the Loop */ ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'content', 'search' ); ?>

			<?php endwhile; ?>

			<?php wpmobileapps_paging_nav(); ?>

		<?php else : ?>

			<?php get_template_part( 'content', 'none' ); ?>

		<?php endif; ?>

		</main><!-- #main -->
	</section><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
