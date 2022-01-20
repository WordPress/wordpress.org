<?php
/**
 * The front page template file.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

$widget_args = array(
    'before_title' => '<h2 class="widgettitle">',
    'after_title'  => '</h2>',
);

get_header();
?>

	<main id="main" class="site-main wrap" role="main">


	<?php
	$placeholder_count = 0;
	$query_total = $GLOBALS['wp_query']->post_count;
	$min_grid_items = 12;
	// Show placeholders to ensure front page has a minimum number of grid items.
	if ( $query_total < $min_grid_items ) {
		$placeholder_count = $min_grid_items - $query_total;
	}

	while ( have_posts() ) :
		the_post();

		get_template_part( 'template-parts/photo', 'grid' );
	endwhile; // End of the loop.

	if ( $placeholder_count ) :
		for ( ; $placeholder_count > 0; $placeholder_count-- ) :
	?>
		<article class="photo-placeholder">
			<div class="entry-content"></div>
		</article>
	<?php
		endfor;
	endif;
	?>

	<?php if ( $query_total === $GLOBALS['wp_query']->query_vars['posts_per_page'] ) : ?>
	<div class="photos-all-links navigation"><a href="<?php echo esc_url( get_post_type_archive_link( get_photo_post_type() ) ); ?>"><?php _e( 'See more photos&rarr;', 'wporg-photos' ); ?></a></div>
	<?php endif; ?>

	</main><!-- #main -->

	<aside id="secondary" class="widget-area wrap" role="complementary">
		<?php
		the_widget( 'WP_Widget_Text', array(
			'title' => __( 'Add Your Photo', 'wporg-photos' ),
			'text'  => sprintf(
				/* translators: URL to submit page. */
				__( 'The WordPress Photo Directory is the perfect place to release your photos into the public domain for the benefit of all. <a href="%s">Submit your photo</a>.', 'wporg-photos' ),
				esc_url( home_url( 'submit' ) )
			),
		), $widget_args );

		the_widget( 'WP_Widget_Text', array(
			'title' => __( 'License', 'wporg-photos' ),
			'text'  => sprintf(
				/* translators: URL to CC0 license. */
				__( 'All photos are <a href="%s">CC0 licensed</a>. No rights are reserved, so you are free to use the photos anywhere, for any purpose, without the need for attribution.' , 'wporg-photos' ),
				esc_url( 'https://creativecommons.org/share-your-work/public-domain/cc0/' )
			),
		), $widget_args );

		the_widget( 'WP_Widget_Text', array(
			'title' => __( 'FAQ', 'wporg-plugins' ),
			'text'  => sprintf(
				/* translators: URL to make/plugins site. */
				__( 'Learn more about licensing, usage, and adding your photos to the WordPress Photo Directory via <a href="%s">Frequently Asked Questions</a>.', 'wporg-photos' ),
				esc_url( home_url( 'faq' ) )
			),
		), $widget_args );
		?>
	</aside><!-- #secondary -->
<?php
get_footer();