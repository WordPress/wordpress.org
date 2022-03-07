<?php
/**
 * The author template file.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

get_header();
?>

	<main id="main" class="site-main wrap" role="main">

	 	<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h2 class="page-title">
					<?php
					printf(
						/* translators: Search query. */
						esc_html__( 'Photos contributed by: %s', 'wporg-photos' ),
						sprintf(
							'<a href="%s" class="photo-author">%s</a>',
							esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
							get_avatar( get_the_author_meta( 'ID' ), 32 ) . get_the_author_meta( 'display_name' )
										)
					);
					?>
				</h2>
			</header><!-- .page-header -->

		<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/photo', 'grid' );
			endwhile; // End of the loop.

			the_posts_pagination();
		
		else :
			//get_template_part( 'template-parts/content', 'none' );
			echo '<div class="no-photos">' . __( 'No photos available yet!', 'wporg-photos' ) . "</div>\n";

		endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
