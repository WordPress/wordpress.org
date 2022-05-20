<?php
/**
 * Template file for the Review Stats page.
 *
 * @package wporg-themes
 */

// Use the translated post title.
add_filter(
	'single_post_title',
	function( $title ) {
		if ( 'Review Stats' === $title ) {
			/* translators: Stats about theme reviews.  */
			$title = __( 'Theme Review Stats', 'wporg-themes' );
		}

		return $title;
	},
	1
);

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'wrap' ); ?>>
		<?php the_content(); ?>
	</article>
		<?php
	endwhile;
endif;

get_footer();
