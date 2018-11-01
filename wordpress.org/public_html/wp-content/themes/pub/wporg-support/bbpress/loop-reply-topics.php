<?php do_action( 'bbp_template_before_topics_loop' ); ?>

<ul id="bbp-forum-<?php bbp_forum_id(); ?>" class="bbp-topics">
	<li class="bbp-header">
		<ul class="forum-titles">
			<li class="bbp-topic-title"><?php esc_html_e( 'Topic', 'wporg-forums' ); ?></li>
			<li class="bbp-topic-voice-count"><?php esc_html_e( 'Voices', 'wporg-forums' ); ?></li>
			<li class="bbp-topic-reply-count"><?php bbp_show_lead_topic()
				? esc_html_e( 'Replies', 'wporg-forums' )
				: esc_html_e( 'Posts',   'wporg-forums' );
			?></li>
			<li class="bbp-topic-freshness"><?php esc_html_e( 'Last Post', 'wporg-forums' ); ?></li>
		</ul>
	</li>

	<li class="bbp-body">

		<?php while ( bbp_replies() ) : bbp_the_reply(); ?>

			<?php bbp_get_template_part( 'loop', 'single-reply-topic' ); ?>

		<?php endwhile; ?>

	</li>

	<li class="bbp-footer">
		<div class="tr">
			<p>
				<span class="td colspan4">&nbsp;</span>
			</p>
		</div><!-- .tr -->
	</li>
</ul><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

<?php do_action( 'bbp_template_after_topics_loop' ); ?>
