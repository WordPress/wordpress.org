<?php

/**
 * Posts Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<ul class="bbp-topics">
	<li class="bbp-header">
		<ul class="forum-titles">
			<li class="bbp-topic-title"><?php esc_html_e( 'Title', 'wporg-forums' ); ?></li>
			<li class="bbp-post-excerpt"><?php esc_html_e( 'Excerpt', 'wporg-forums' ); ?></li>
		</ul>
	</li>

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
