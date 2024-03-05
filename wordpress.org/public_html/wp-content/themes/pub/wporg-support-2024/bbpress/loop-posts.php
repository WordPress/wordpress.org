<?php

/**
 * Posts Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<ul class="bbp-topics full-posts">
	<li class="bbp-body">

		<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

			<?php if ( 'topic' == get_post_type() ) : ?>

				<?php bbp_get_template_part( 'content', 'single-topic-lead' ); ?>

			<?php // This actually works. ?>
			<?php else : bbpress()->reply_query = bbpress()->topic_query; ?>

				<?php bbp_get_template_part( 'loop', 'single-reply' ); ?>

			<?php endif; ?>

		<?php endwhile; ?>

	</li>

	<li class="bbp-footer">
		<div class="tr">
			<p>
				<span class="td colspan2">&nbsp;</span>
			</p>
		</div><!-- .tr -->
	</li>
</ul>
