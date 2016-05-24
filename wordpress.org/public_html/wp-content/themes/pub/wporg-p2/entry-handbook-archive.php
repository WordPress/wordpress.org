<?php
/**
 * Displays the excerpt for a handbook page.
 *
 * @package P2
 */
?>
<li id="prologue-<?php the_ID(); ?>" <?php post_class( array( 'handbook' ) ); ?>>
	<h3><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h3>

	<div id="content-<?php the_ID(); ?>" class="postcontent">
		<?php the_excerpt(); ?>
	</div>
</li>
