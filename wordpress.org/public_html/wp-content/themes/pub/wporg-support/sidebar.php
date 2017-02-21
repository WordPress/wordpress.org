<div class="entry-meta sidebar">

	<?php if ( function_exists( 'is_bbpress' ) && ( is_bbpress() ) || is_page( 'new-topic' ) ) : ?>

		<?php if ( bbp_is_single_forum() || ( bb_is_intl_forum() && bb_base_topic_search_query( false ) ) ) : ?>

			<div>
				<!--h4><?php //_e( 'Forum Info', 'wporg-forums' ); ?></h4-->
				<ul class="forum-info">
					<?php bb_base_single_forum_description(); ?>
				</ul>
			</div>

			<!--div>
				<?php
					//if ( bb_is_intl_forum() ) :
					//	bb_base_topic_search_form();
					//else :
					//	bb_base_search_form();
					//endif;
				?>
			</div-->

			<div>
				<!--h3><?php //_e( 'Forum Feeds', 'wporg-forums' ); ?></h3-->
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/"><?php _e( 'Recent Posts', 'wporg-forums' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/?type=topic"><?php _e( 'Recent Topics', 'wporg-forums' ); ?></a></li>
					<?php if ( bbp_current_user_can_access_create_topic_form() ) : ?>
						<li class="create-topic"><a href="#new-post"><?php _e( 'Create Topic', 'wporg-forums' ); ?></a></li>
					<?php endif; ?>
					<?php if ( is_user_logged_in() ) : ?>
						<li class="forum-subscribe"><?php bbp_forum_subscription_link(); ?></li>
					<?php endif; ?>
				</ul>
			</div>

		<?php elseif ( bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'theme', 'plugin', 'reviews', 'active' ) ) ) : ?>

			<?php do_action( 'wporg_compat_view_sidebar' ); ?>

		<?php elseif ( bbp_is_single_topic() || bbp_is_topic_edit() || bbp_is_reply_edit() ) : ?>

			<?php do_action( 'wporg_compat_single_topic_sidebar_pre' ); ?>

			<div>
				<?php if ( wporg_support_is_single_review() ) : ?>
					<!--h3><?php //_e( 'Review Info', 'wporg-forums' ); ?></h3-->
				<?php else : ?>
					<!-- h3><?php //_e( 'Topic Info', 'wporg-forums' ); ?></h3-->
				<?php endif; ?>

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

			<!--div>
				<?php
					//if ( bb_is_intl_forum() ) :
					//	bb_base_reply_search_form();
					//else :
					//	bb_base_search_form();
					//endif;
				?>
			</div-->

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

		<?php if ( ! bbp_is_single_user() ) : ?>

			<div>
				<h4><?php _e( 'Views', 'wporg-forums' ); ?></h4>
				<ul class="topic-views">

					<?php foreach ( bbp_get_views() as $view => $args ) :
						if ( in_array( $view, array( 'theme', 'plugin', 'reviews', 'active' ) ) ) {
							continue;
						}
						?>

						<li><a class="bbp-view-title" href="<?php bbp_view_url( $view ); ?>"><?php bbp_view_title( $view ); ?></a></li>

					<?php endforeach; ?>

				</ul>
			</div>

		<?php endif; ?>

		<?php if ( bbp_is_single_view() && ! in_array( bbp_get_view_id(), array( 'theme', 'plugin', 'reviews', 'active' ) ) || is_tax( 'topic-tag' ) ) : ?>

			<div>
				<h4><?php _e( 'Feeds', 'wporg-forums' ); ?></h4>
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forums_url(); ?>feed/"><?php _e( 'All Recent Posts', 'wporg-forums' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_topics_url(); ?>feed/"><?php _e( 'All Recent Topics', 'wporg-forums' ); ?></a></li>
				</ul>
			</div>

			<div>
				<h4><?php _e( 'Tags', 'wporg-forums' ); ?></h4>
				<?php wp_tag_cloud( array( 'smallest' => 14, 'largest' => 24, 'number' => 22, 'taxonomy' => bbp_get_topic_tag_tax_id() ) ); ?>
			</div>

		<?php endif; ?>

	<?php endif; ?>

	<?php if ( is_user_logged_in() ) : ?>

		<div class="my-account">
			<h4><?php _e( 'My Account', 'wporg-forums' ); ?></h4>
			<ul>
				<li><?php echo sprintf( __( 'Howdy, %s', 'wporg-forums' ), '<a href="' . esc_url( bbp_get_user_profile_url( bbp_get_current_user_id() ) ) . '">' . bbp_get_current_user_name() . '</a>' ); ?></li>
				<li><a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php _e( 'Log Out', 'wporg-forums' ); ?></a></li>
			</ul>
		</div>

	<?php endif; ?>

</div>
