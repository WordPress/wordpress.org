<?php

/**
 * Replies Loop - Single Reply
 *
 * @package bbPress
 * @subpackage Theme
 */

if ( bbp_is_single_user_replies() ) : ?>

<div id="post-<?php bbp_reply_id(); ?>" class="bbp-reply-header">
	<div class="bbp-meta">
			<span class="bbp-header">
				<?php esc_html_e( 'In reply to: ', 'wporg-forums' ); ?>
				<a class="bbp-topic-permalink" href="<?php bbp_topic_permalink( bbp_get_reply_topic_id() ); ?>"><?php bbp_topic_title( bbp_get_reply_topic_id() ); ?></a>
			</span>
	</div><!-- .bbp-meta -->
</div><!-- #post-<?php bbp_reply_id(); ?> -->

<?php endif; ?>

<div id="post-<?php bbp_reply_id(); ?>" <?php bbp_reply_class(); ?>>

	<div class="bbp-reply-author">

		<?php do_action( 'bbp_theme_before_reply_author_details' ); ?>

		<?php bbp_reply_author_link( array( 'sep' => '', 'show_role' => true, 'size' => 100 ) ); ?>

		<?php bbp_user_nicename( bbp_get_reply_author_id(), array( 'before' => '<p class="bbp-user-nicename">@', 'after' => '</p>' ) ); ?>

		<p class="bbp-reply-post-date"><a href="<?php bbp_reply_url(); ?>" title="#<?php bbp_reply_id(); ?>" class="bbp-reply-permalink"><?php bbp_reply_post_date( bbp_get_reply_id(), true ); ?></a></p>

		<?php if ( current_user_can( 'moderate', bbp_get_reply_topic_id() ) ) : ?>

			<?php do_action( 'bbp_theme_before_reply_author_admin_details' ); ?>

			<div class="bbp-reply-ip"><?php bbp_author_ip( bbp_get_reply_id() ); ?></div>

			<?php do_action( 'bbp_theme_after_reply_author_admin_details' ); ?>

		<?php endif; ?>

		<?php do_action( 'bbp_theme_after_reply_author_details' ); ?>

	</div><!-- .bbp-reply-author -->

	<div class="bbp-reply-content">

		<?php do_action( 'bbp_theme_before_reply_content' ); ?>

		<?php bbp_reply_content(); ?>

		<?php do_action( 'bbp_theme_after_reply_content' ); ?>

	</div><!-- .bbp-reply-content -->

	<?php do_action( 'bbp_theme_before_reply_admin_links' ); ?>

	<?php bbp_reply_admin_links(); ?>

	<?php do_action( 'bbp_theme_after_reply_admin_links' ); ?>

</div><!-- #post-<?php bbp_reply_id(); ?> -->
