<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

get_header();
?>

	<main id="main" class="site-main wrap" role="main">

		<?php
		while ( have_posts() ) :
			the_post();

			$pages_with_specialized_content = [ 'c', 'color', 'orientation', 'submit', 't' ];
			$page_name = get_post()->post_name;
			$part = in_array( $page_name, $pages_with_specialized_content ) ? "page-{$page_name}" : 'page';
			get_template_part( 'template-parts/content', $part );

			endwhile; // End of the loop.
		?>

	</main><!-- #main -->

<?php
get_footer();
