<?php

/**
 * Single Topic Part
 *
 * @package bbPress
 * @subpackage Theme
 */

if ( bbp_is_search_results() ) : ?>

<div id="post-<?php bbp_topic_id(); ?>" class="bbp-topic-header">
	<div class="bbp-meta">
			<span class="bbp-header">
				<?php esc_html_e( 'Forum:', 'wporg-forums' ); ?>
				<a class="bbp-forum-permalink" href="<?php bbp_forum_permalink( bbp_get_topic_forum_id() ); ?>"><?php bbp_forum_title( bbp_get_topic_forum_id() ); ?></a><br />

				<?php esc_html_e( 'As the topic: ', 'wporg-forums' ); ?>
				<a class="bbp-topic-permalink" href="<?php bbp_topic_permalink( bbp_get_topic_id() ); ?>"><?php bbp_topic_title( bbp_get_topic_id() ); ?></a>
			</span>
	</div><!-- .bbp-meta -->
</div><!-- #post-<?php bbp_topic_id(); ?> -->

<?php endif; ?>

<?php do_action( 'bbp_template_before_lead_topic' ); ?>

<ul id="bbp-topic-<?php bbp_topic_id(); ?>-lead" class="bbp-lead-topic">

	<li class="bbp-body">

		<div id="post-<?php bbp_topic_id(); ?>" <?php bbp_topic_class(); ?>>
			<?php if ( bbp_is_topic_sticky() ) : ?>
				<div class="topic-indicator">
					<span class="dashicons dashicons-admin-post" title="<?php esc_attr_e( 'Sticky Topic', 'wporg-forums' ); ?>"></span>
				</div>
			<?php elseif ( bbp_is_topic_closed() ) : ?>
				<div class="topic-indicator">
					<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Closed Topic', 'wporg-forums' ); ?>"></span>
				</div>
			<?php endif; ?>

			<div class="bbp-topic-author">

				<?php do_action( 'bbp_theme_before_topic_author_details' ); ?>

				<?php bbp_topic_author_link( array( 'sep' => '', 'show_role' => false, 'size' => 100 ) ); ?>

				<?php bbp_user_nicename( bbp_get_topic_author_id(), array( 'before' => '<p class="bbp-user-nicename">(@', 'after' => ')</p><br />' ) ); ?>

				<?php if ( $title = get_user_option( 'title', bbp_get_topic_author_id() ) ) : ?>

					<p class="bbp-author-title"><?php echo esc_html( $title ); ?></p>

				<?php endif; ?>

				<p class="bbp-topic-post-date"><a href="<?php bbp_topic_permalink(); ?>" title="#<?php bbp_topic_id(); ?>" class="bbp-topic-permalink"><?php bbp_topic_post_date( bbp_get_topic_id(), true ); ?></a></p>

				<?php if ( current_user_can( 'moderate', bbp_get_topic_id() ) ) : ?>

					<?php do_action( 'bbp_theme_before_topic_author_admin_details' ); ?>

					<div class="bbp-topic-ip"><?php bbp_author_ip( bbp_get_topic_id() ); ?></div>

					<?php do_action( 'bbp_theme_after_topic_author_admin_details' ); ?>

				<?php endif; ?>

				<?php do_action( 'bbp_theme_after_topic_author_details' ); ?>

			</div><!-- .bbp-topic-author -->

			<div class="bbp-topic-content">

				<?php do_action( 'bbp_theme_before_topic_content' ); ?>

				<?php bbp_topic_content(); ?>

				<?php do_action( 'bbp_theme_after_topic_content' ); ?>

			</div><!-- .bbp-topic-content -->

			<?php bbp_topic_admin_links(); ?>

		</div><!-- #post-<?php bbp_topic_id(); ?> -->

	</li><!-- .bbp-body -->

</ul><!-- #topic-<?php bbp_topic_id(); ?>-replies -->

<?php do_action( 'bbp_template_after_lead_topic' ); ?>
