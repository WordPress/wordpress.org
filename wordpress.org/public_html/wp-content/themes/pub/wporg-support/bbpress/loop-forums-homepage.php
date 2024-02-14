<?php do_action( 'bbp_template_before_forums_loop' ); ?>

<h2>[TBD] Check these Forums</h2>
<p>[TBD] Paragraph describing the page content</p>

<div id="forums-list-<?php bbp_forum_id(); ?>" class="bbp-forums three-up cards-grid">

		<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

			<?php bbp_get_template_part( 'loop', 'single-forum-homepage' ); ?>

		<?php endwhile; ?>

</div><!-- .forums-directory -->

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
