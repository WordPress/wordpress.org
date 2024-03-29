
<div class="entry-meta sidebar">

	<?php if ( is_user_logged_in() ) : ?>

		<div class="my-account">
			<ul>
				<li><?php
					/* translators: %s: user's display name */
					printf( __( 'Howdy, %s', 'wporg-forums' ),
						'<a href="' . esc_url( bbp_get_user_profile_url( bbp_get_current_user_id() ) ) . '">' . bbp_get_current_user_name() . '</a>'
					);
				?></li>
				<li><a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php _e( 'Log Out', 'wporg-forums' ); ?></a></li>
			</ul>
		</div>

	<?php endif; ?>

	<?php if ( function_exists( 'is_bbpress' ) && ( is_bbpress() ) || is_page() ) : ?>

		<?php if ( bbp_is_single_forum() || ( bb_is_intl_forum() && bb_base_topic_search_query( false ) ) ) : ?>

			<div>
				<ul class="forum-info">
					<?php bb_base_single_forum_description(); ?>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/"><?php _e( 'RSS Recent Posts', 'wporg-forums' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/?type=topic"><?php _e( 'RSS Recent Topics', 'wporg-forums' ); ?></a></li>
					<?php if ( is_user_logged_in() && $forum_subscription_link = bbp_get_forum_subscription_link() ) : ?>
						<li class="forum-subscribe"><?php echo $forum_subscription_link; ?></li>
					<?php endif; ?>
				</ul>
			</div>

		<?php elseif ( wporg_support_is_compat_view() ) : ?>

			<?php do_action( 'wporg_compat_view_sidebar' ); ?>

		<?php elseif ( bbp_is_single_topic() || bbp_is_topic_edit() || bbp_is_single_reply() || bbp_is_reply_edit() ) : ?>

			<?php do_action( 'wporg_compat_single_topic_sidebar_pre' ); ?>

			<div>
				<ul class="topic-info">
					<?php bb_base_single_topic_description(); ?>
				</ul>
			</div>

			<div>
				<?php bbp_topic_tag_list( 0, array(
					'before' => '<h4>' . __( 'Topic Tags', 'wporg-forums' ) . '</h4><ul class="topic-tags"><li>',
					'after'  => '</li></ul>',
					'sep'    => '</li><li>',
				) ); ?>
			</div>

			<?php if ( current_user_can( 'moderate', bbp_get_topic_id() ) || wporg_support_current_user_can_stick( bbp_get_topic_id() ) ) : ?>

				<div>
					<?php bbp_topic_admin_links( array (
						'id'     => bbp_get_topic_id(),
						'before' => '<h4>' . __( 'Topic Admin', 'wporg-forums' ) . '</h4><ul class="topic-admin-links"><li>',
						'after'  => '</li></ul>',
						'sep'    => '</li><li>',
						'links'  => array()
					) ); ?>
				</div>

			<?php endif; ?>

		<?php elseif ( is_tax( 'topic-tag' ) ) : ?>

			<?php
				$term_subscription = '';
				if ( function_exists( 'WordPressdotorg\Forums\Term_Subscription\get_subscription_link' ) ) {
					$term_subscription = WordPressdotorg\Forums\Term_Subscription\get_subscription_link( get_queried_object()->term_id );
				}
				if ( $term_subscription ) {
					echo '<div>' . $term_subscription . "</div>\n";
				}
			?>

		<?php endif; ?>

		<?php if ( ! bbp_is_single_user() && ! ( wporg_support_is_compat_forum() ) ) : ?>

			<div>
				<h4><?php _e( 'Views', 'wporg-forums' ); ?></h4>
				<ul class="topic-views">

					<?php foreach ( bbp_get_views() as $view => $args ) :
						if ( in_array( $view, wporg_support_get_compat_views() ) ) {
							continue;
						}
						?>

						<li><a class="bbp-view-title" href="<?php bbp_view_url( $view ); ?>"><?php bbp_view_title( $view ); ?></a></li>

					<?php endforeach; ?>

				</ul>
			</div>

		<?php endif; ?>

		<?php if ( bbp_is_single_view() && ! wporg_support_is_compat_view() || is_tax( 'topic-tag' ) ) : ?>

			<div>
				<h4><?php _e( 'Feeds', 'wporg-forums' ); ?></h4>
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forums_url(); ?>feed/"><?php _e( 'All Recent Posts', 'wporg-forums' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_topics_url(); ?>feed/"><?php _e( 'All Recent Topics', 'wporg-forums' ); ?></a></li>
				</ul>
			</div>

		<?php endif; ?>

	<?php endif; ?>

</div>
