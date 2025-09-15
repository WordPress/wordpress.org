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

		<header class="page-header">
			<h2 class="page-title">
				<?php
				printf(
					/* translators: Search query. */
					esc_html__( 'Photos contributed by: %s', 'wporg-photos' ),
					sprintf(
						'<a href="%s" class="photo-author-link">%s</a>',
						esc_url( 'https://profiles.wordpress.org/' . get_the_author_meta( 'nicename' ) . '/' ),
						get_avatar( get_the_author_meta( 'ID' ), 32 ) . get_the_author_meta( 'display_name' )
					)
				);
				?>
			</h2>
		</header><!-- .page-header -->

		<?php
			if ( have_posts() ) :

				while ( have_posts() ) :
					the_post();

					get_template_part( 'template-parts/photo', 'grid' );
				endwhile; // End of the loop.

				the_posts_pagination();

			else :
		?>

			<article class="user-no-photos">
				<div class="entry-content no-photos">
					<?php
						if ( get_current_user_id() === get_the_author_meta( 'ID' ) ) {
							esc_html_e( "You haven't contributed with photos yet.", 'wporg-photos' );
						} else {
							esc_html_e( "This user hasn't contributed with photos yet.", 'wporg-photos' );
						}
					?>
				</div>
			</article>

		<?php endif; ?>

	</main><!-- #main -->

<?php
get_footer();