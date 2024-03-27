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

		<header class="page-header">
			<h1 class="page-title">
				<?php
				printf(
					/* translators: Search query. */
					esc_html__( 'Showing results for: %s', 'wporg-plugins' ),
					'<strong>' . get_search_query() . '</strong>'
				);
				?>
			</h1>
			<?php
			if ( get_query_var( 'block_search' ) ) {
				printf(
					/* translators: %s: Search URL */
					'<p>' . __( 'Searching the block directory. <a href="%s">Search all plugins</a>.', 'wporg-plugins' ) . '</p>',
					esc_url( remove_query_arg( 'block_search' ) )
				);
			}
			?>
		</header><!-- .page-header -->

		<?php
		/* Start the Loop */
		while ( have_posts() ) :
			the_post();

			get_template_part( 'template-parts/plugin', 'index' );
		endwhile;

		if ( ! have_posts() ) {
			get_template_part( 'template-parts/no-results' );
		}

		the_posts_pagination();

		?>

	</main><!-- #main -->

<?php
get_footer();
