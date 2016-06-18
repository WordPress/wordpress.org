<?php
/**
 * The template for displaying search results pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

get_header();
?>

	<main id="main" class="site-main" role="main">

		<?php
			if ( have_posts() ) : ?>

				<header class="page-header">
					<h1 class="page-title"><?php printf( esc_html__( 'Showing results for: %s', 'wporg-plugins' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
				</header><!-- .page-header -->

				<?php
				/* Start the Loop */
				while ( have_posts() ) :
					the_post();

					get_template_part( 'template-parts/plugin', 'index' );
				endwhile;

				the_posts_pagination();

			else :
				get_template_part( 'template-parts/content', 'none' );
			endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
