<?php
/**
 * Loop Forums Content Part
 *
 * @package WPBBP
 */
?>

<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

	<article id="forums-list-<?php bbp_forum_id(); ?>" class="forums-id-<?php bbp_forum_id(); ?> bbp-forums">

		<?php bbp_get_template_part( 'loop', 'single-forum' ); ?>

	</article><!-- . -->

<?php endwhile; ?>
