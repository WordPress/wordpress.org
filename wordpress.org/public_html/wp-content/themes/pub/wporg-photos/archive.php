<?php
/**
 * The archive template file.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;
use WordPressdotorg\Photo_Directory\Registrations;

get_header();
?>

	<main id="main" class="site-main wrap" role="main">

		<?php if ( ! is_post_type_archive( Registrations::get_post_type() ) ) : ?>
			<header class="page-header">
				<h1 class="page-title"><?php the_archive_title(); ?></h1>
			</header><!-- .page-header -->
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>

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

		<footer class="page-footer">
			<?php
				$is_cat   = is_tax( Registrations::get_taxonomy( 'categories' ) );
				$is_color = is_tax( Registrations::get_taxonomy( 'colors' ) );

				if ( $is_cat || $is_color ) {
					if ( $is_cat ) {
						$terms = get_terms( [ 'taxonomy' => Registrations::get_taxonomy( 'categories' ), 'hide_empty' => false ] );
						_e( 'Other categories:', 'wporg-photos' );
						echo '<ul class="photos-categories">';
						$slug = 'category';
					}
					if ( $is_color ) {
						$terms = get_terms( [ 'taxonomy' => Registrations::get_taxonomy( 'colors' ), 'hide_empty' => false ] );
						_e( 'Other colors:', 'wporg-photos' );
						echo '<ul class="photos-colors">';
						$slug = 'color';
					}
					foreach ( $terms as $term ) {
						printf( '<li class="%s"><a href="%s">%s</a></li>', esc_attr( $slug . '-' . $term->slug ), esc_url( get_term_link( $term->term_id ) ), $term->name );
					}
				echo "</ul>\n";
				}
			?>
		</footer>
	</main><!-- #main -->

<?php
get_footer();