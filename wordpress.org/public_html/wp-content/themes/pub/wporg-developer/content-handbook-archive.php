<?php
/**
 * Displays the content and meta information for a handbook archive.
 *
 * @package wporg
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( array( 'handbook' ) ); ?>>

	<h1><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h1>

	<div class="description">
		<?php the_excerpt(); ?>
	</div>

</article>

