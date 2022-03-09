<?php
/**
 * Template part for displaying grid of photos.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

//use WordPressdotorg\Photo_Directory\Template;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
	</header><!-- .entry-header -->

	<div class="entry-content">
		<a href="<?php echo get_post_permalink(); ?>">
			<?php printf(
				'<img class="grid-photo" src="%s" srcset="%s" height="200" width="300" alt="%s" loading="lazy" decoding="async">',
				esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ),
				esc_attr(
					get_the_post_thumbnail_url( get_the_ID(), 'medium' )
					. ', ' . get_the_post_thumbnail_url( get_the_ID(), 'medium_large' ) . ' 2x'
				),
				esc_attr( get_the_content() )
			); ?>
		</a>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
	</footer><!-- .entry-footer -->

</article><!-- #post-## -->
