<?php

/**
 * Topics Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_topics_loop' );

$is_all_replies_view = bbp_get_view_id() === 'all-replies';

?>

<ul id="bbp-forum-<?php bbp_forum_id(); ?>" class="bbp-topics<?php if ( $is_all_replies_view ) { echo ' full-posts'; } ?>">

	<?php if ( ! $is_all_replies_view ) { ?>
		<li class="bbp-header">
			<ul class="forum-titles">
				<li class="bbp-topic-title"><?php esc_html_e( 'Topic', 'bbpress' ); ?></li>
				<li class="bbp-topic-voice-count"><?php esc_html_e( 'Participants', 'bbpress' ); ?></li>
				<li class="bbp-topic-reply-count"><?php bbp_show_lead_topic()
					? esc_html_e( 'Replies', 'bbpress' )
					: esc_html_e( 'Posts',   'bbpress' );
				?></li>
				<li class="bbp-topic-freshness"><?php esc_html_e( 'Last Post', 'bbpress' ); ?></li>
			</ul>
		</li>
	<?php } ?>

	<li class="bbp-body">

		<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

			<?php
			if ( bbp_is_reply( bbp_get_topic_id() ) ) {
				bbp_get_template_part( 'loop', 'single-reply' );
			} else {
				bbp_get_template_part( 'loop', 'single-topic' );
			}
			?>

		<?php endwhile; ?>

	</li>

	<li class="bbp-footer">
		<div class="tr">
			<p>
				<span class="td colspan<?php echo ( bbp_is_user_home() && ( bbp_is_favorites() || bbp_is_subscriptions() ) ) ? '5' : '4'; ?>">&nbsp;</span>
			</p>
		</div><!-- .tr -->
	</li>
</ul><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

<?php do_action( 'bbp_template_after_topics_loop' );
