<?php $topic_id = bbp_get_reply_topic_id(); ?>

<ul id="bbp-topic-<?php bbp_topic_id( $topic_id ); ?>" <?php bbp_topic_class( $topic_id ); ?>>

	<li class="bbp-topic-title">

		<?php do_action( 'bbp_theme_before_topic_title' ); ?>

		<a class="bbp-topic-permalink" href="<?php bbp_topic_permalink( $topic_id ); ?>"><?php bbp_topic_title( $topic_id ); ?></a>

		<?php do_action( 'bbp_theme_after_topic_title' ); ?>

		<?php bbp_topic_pagination( array( 'topic_id' => $topic_id ) ); ?>

		<?php do_action( 'bbp_theme_before_topic_meta' ); ?>

		<p class="bbp-topic-meta">

			<?php do_action( 'bbp_theme_before_topic_started_by' ); ?>

			<span class="bbp-topic-started-by"><?php printf( __( 'Started by: %1$s', 'wporg-forums' ), bbp_get_topic_author_link( array( 'post_id' => $topic_id, 'size' => '14' ) ) ); ?></span>

			<?php do_action( 'bbp_theme_after_topic_started_by' ); ?>

			<?php do_action( 'bbp_theme_before_topic_started_in' ); ?>

			<span class="bbp-topic-started-in"><?php printf( __( 'in: <a href="%1$s">%2$s</a>', 'wporg-forums' ), bbp_get_forum_permalink( bbp_get_topic_forum_id( $topic_id ) ), bbp_get_forum_title( bbp_get_topic_forum_id( $topic_id ) ) ); ?></span>

			<?php do_action( 'bbp_theme_after_topic_started_in' ); ?>

		</p>

		<?php do_action( 'bbp_theme_after_topic_meta' ); ?>

		<?php bbp_topic_row_actions(); ?>

	</li>

	<li class="bbp-topic-voice-count"><?php bbp_topic_voice_count( $topic_id ); ?></li>

	<li class="bbp-topic-reply-count"><?php bbp_show_lead_topic() ? bbp_topic_reply_count( $topic_id ) : bbp_topic_post_count( $topic_id ); ?></li>

	<li class="bbp-topic-freshness">

		<?php do_action( 'bbp_theme_before_topic_freshness_link' ); ?>

		<?php bbp_topic_freshness_link( $topic_id ); ?>

		<?php do_action( 'bbp_theme_after_topic_freshness_link' ); ?>

		<p class="bbp-topic-meta">

			<?php do_action( 'bbp_theme_before_topic_freshness_author' ); ?>

			<span class="bbp-topic-freshness-author"><?php bbp_author_link( array( 'post_id' => bbp_get_topic_last_active_id( $topic_id ), 'size' => 14 ) ); ?></span>

			<?php do_action( 'bbp_theme_after_topic_freshness_author' ); ?>

		</p>
	</li>

</ul><!-- #bbp-topic-<?php bbp_topic_id( $topic_id ); ?> -->
