<?php
/**
 * The template part for displaying content
 *
 * @package WordPressdotorg\Pattern_Directory\Theme;
 */

namespace WordPressdotorg\Pattern_Directory\Theme;

?>

<div id="post-<?php the_ID(); ?>" class="pattern-grid__pattern">
	<div class="pattern-grid__pattern-frame pattern-skeleton" style="padding:56.25% 0 0;"></div>
	<?php the_title( '<h2 class="pattern-grid__title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
	<p class="pattern-grid__meta">
		<a href="https://wordpress.org/patterns/author/wordpressdotorg" class="pattern-grid__author-avatar">
			<?php echo get_avatar( get_the_author_meta( 'ID' ), 32 ); ?>
			<?php esc_html( get_the_author() ); ?>
		</a>
	</p>
</div>
