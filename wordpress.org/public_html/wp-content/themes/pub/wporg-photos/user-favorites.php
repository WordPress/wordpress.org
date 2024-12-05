<?php
/**
 * The user favorites template file.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

// Note: The user whose favorites 

get_header();
?>

	<main id="main" class="site-main wrap" role="main">

		<header class="page-header">
			<h2 class="page-title">
				<?php
				printf(
					/* translators: Search query. */
					esc_html__( 'Photos favorited by %s', 'wporg-photos' ),
					sprintf(
						'<a href="%s" class="photo-favoriter" title="%s">%s</a>',
						esc_url( 'https://profiles.wordpress.org/' . get_the_author_meta( 'nicename' ) . '/' ),
						esc_attr__( 'View their profile', 'wporg-photos' ),
						get_avatar( get_the_author_meta( 'ID' ), 32 ) . get_the_author_meta( 'display_name' )
					)
				);
				?>
			</h2>
		</header><!-- .page-header -->

		<?php
			$has_favorites = have_posts();
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/photo', 'grid' );
			endwhile; // End of the loop.

			the_posts_pagination();
		?>

		<?php if ( ! $has_favorites ) : ?>

			<article class="user-no-favorites">
				<div class="entry-content no-photos">
					<?php
					if ( get_current_user_id() === get_the_author_meta( 'ID' ) ) {
						_e( 'You have not chosen any favorite photos yet.', 'wporg-photos' );
					} else {
						_e( 'This user has not chosen any favorite photos yet.', 'wporg-photos' );
					}
					?>
				</div>
			</article>

			<?php endif; ?>

	</main><!-- #main -->

<?php
get_footer();