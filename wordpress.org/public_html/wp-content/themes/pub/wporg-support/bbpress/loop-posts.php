<?php

/**
 * Posts Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

$is_moderator_view = class_exists( '\WordPressdotorg\Forums\Moderators' )
				&& defined( '\WordPressdotorg\Forums\Moderators::VIEWS' )
				&& in_array( bbp_get_view_id(), \WordPressdotorg\Forums\Moderators::VIEWS );

?>

<ul class="bbp-topics">
	<li class="bbp-body">

		<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

			<?php if ( 'topic' == get_post_type() ) : ?>

				<div id="post-<?php bbp_topic_id(); ?>" class="bbp-topic-header">
					<div class="bbp-meta">
						<span class="bbp-header">
							<?php esc_html_e( 'Forum:', 'wporg-forums' ); ?>
							<a class="bbp-forum-permalink" href="<?php
								$forum_url = bbp_get_forum_permalink( bbp_get_topic_forum_id() );
								if ( $is_moderator_view ) {
									$forum_url = add_query_arg( 'view', 'all', $forum_url );
								}
								echo esc_url( $forum_url );
							?>"><?php bbp_forum_title( bbp_get_topic_forum_id() ); ?></a><br />
							<?php esc_html_e( 'As the topic:', 'wporg-forums' ); ?>
							<a class="bbp-topic-permalink" href="<?php
								$topic_url = bbp_get_topic_permalink( bbp_get_topic_id() );
								if ( $is_moderator_view ) {
									$topic_url = add_query_arg( 'view', 'all', $topic_url );
								}
								echo esc_url( $topic_url );
							?>"><?php bbp_topic_title( bbp_get_topic_id() ); ?></a>
						</span>
					</div><!-- .bbp-meta -->
				</div><!-- #post-<?php bbp_topic_id(); ?> -->

				<?php bbp_get_template_part( 'content', 'single-topic-lead' ); ?>

			<?php // This actually works. ?>
			<?php else : bbpress()->reply_query = bbpress()->topic_query; ?>

				<div id="post-<?php bbp_reply_id(); ?>" class="bbp-reply-header">
					<div class="bbp-meta">
						<span class="bbp-header">
							<?php esc_html_e( 'Forum:', 'wporg-forums' ); ?>
							<a class="bbp-forum-permalink" href="<?php
								$forum_url = bbp_get_forum_permalink( bbp_get_reply_forum_id() );
								if ( $is_moderator_view ) {
									$forum_url = add_query_arg( 'view', 'all', $forum_url );
								}
								echo esc_url( $forum_url );
							?>"><?php bbp_forum_title( bbp_get_reply_forum_id() ); ?></a><br />
							<?php esc_html_e( 'In reply to:', 'wporg-forums' ); ?>
							<a class="bbp-topic-permalink" href="<?php
								$topic_url = bbp_get_topic_permalink( bbp_get_reply_topic_id() );
								if ( $is_moderator_view ) {
									$topic_url = add_query_arg( 'view', 'all', $topic_url );
								}
								echo esc_url( $topic_url );
							?>"><?php bbp_topic_title( bbp_get_reply_topic_id() ); ?></a>
						</span>
					</div><!-- .bbp-meta -->
				</div><!-- #post-<?php bbp_reply_id(); ?> -->

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
