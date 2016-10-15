<?php do_action( 'bbp_template_before_forums_loop' ); ?>

<div id="forums-list-<?php bbp_forum_id(); ?>" class="bbp-forums three-up">


		<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

			<?php bbp_get_template_part( 'loop', 'single-forum-homepage' ); ?>

		<?php endwhile; ?>


</div><!-- .forums-directory -->

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
