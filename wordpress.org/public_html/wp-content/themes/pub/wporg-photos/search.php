<?php
/**
 * The search template file.
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
						esc_html__( 'Showing results for: %s', 'wporg-photos' ),
						'<strong>' . sanitize_text_field( get_search_query( false ) ) . '</strong>'
					);
					?>
				</h2>
			</header><!-- .page-header -->

		<?php
			get_template_part( 'template-parts/content-partial-search', 'user' );

			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/photo', 'grid' );
			endwhile; // End of the loop.

			the_posts_pagination();
		
		else :
		?>
			<section class="no-results not-found">

			<header class="page-header">
				<h2 class="page-title">
					<?php _e( 'Nothing Found', 'wporg-photos' ); ?>
				</h2>
			</header>

			<p><?php _e( 'Sorry, but nothing matched your search terms.', 'wporg-photos' ); ?></p>

			<?php get_template_part( 'template-parts/content-partial-search', 'user' ); ?>

			<p><?php _e( 'Please try again with some different keywords.', 'wporg-photos' ); ?></p>

			<p><?php printf(
				/* translators: 1: link to categories archive, 2: link to colors archive, 3: link to orientations archive. */
				__( 'Or try browing by %1$s, %2$s, or %3$s.', 'wporg-photots' ),
				sprintf( '<a href="%s">%s</a>', home_url( '/c/' ), __( 'categories', 'wporg-photos' ) ),
				sprintf( '<a href="%s">%s</a>', home_url( '/color/' ), __( 'colors', 'wporg-photos' ) ),
				sprintf( '<a href="%s">%s</a>', home_url( '/orientation/' ), __( 'orientations', 'wporg-photos' ) )
			); ?></p>

			<?php
				get_search_form();
				//get_template_part( 'template-parts/content', 'none' );
			?>

			</section>

		<?php
		endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
