<?php
/**
 * The template for displaying archive pages.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

// If we don't have any posts to display for the archive, then send a 404 status. See #meta4151
if ( ! have_posts() ) {
	status_header( 404 );
	nocache_headers();
}

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<header class="page-header">
			<?php
			the_archive_title( '<h1 class="page-title">', '</h1>' );
			the_archive_description( '<div class="taxonomy-description">', '</div>' );
			?>
		</header><!-- .page-header -->

		<?php
		/* Start the Loop */
		while ( have_posts() ) :
			the_post();

			/*
				* Include the Post-Format-specific template for the content.
				* If you want to override this in a child theme, then include a file
				* called content-___.php (where ___ is the Post Format name) and that will be used instead.
				*/
			get_template_part( 'template-parts/plugin' );

		endwhile;
		if ( ! have_posts() ) {
			get_template_part( 'template-parts/no-results' );
		}

		the_posts_pagination();

		?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
