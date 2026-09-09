<?php do_action( 'bp_before_directory_activity' ); ?>

<div id="buddypress">

	<?php do_action( 'bp_before_directory_activity_content' ); ?>

	<?php if ( is_user_logged_in() ) : ?>

		<?php bp_get_template_part( 'activity/post-form' ); ?>

	<?php endif; ?>

	<?php do_action( 'template_notices' ); ?>

	<div class="item-list-tabs activity-type-tabs" role="navigation">
		<ul>
			<?php do_action( 'bp_before_activity_type_tab_all' ); ?>

			<?php /* translators: %s: Member count. */ ?>
			<li class="selected" id="activity-all"><a href="<?php bp_activity_directory_permalink(); ?>" title="<?php esc_attr_e( 'The public activity for everyone on this site.', 'buddypress' ); ?>"><?php printf( wp_kses_post( __( 'All Members <span>%s</span>', 'buddypress' ) ), 'ms' ); ?></a></li>

			<?php if ( is_user_logged_in() ) : ?>

				<?php do_action( 'bp_before_activity_type_tab_friends' ); ?>

				<?php if ( bp_is_active( 'friends' ) ) : ?>

					<?php if ( bp_get_total_friend_count( bp_loggedin_user_id() ) ) : ?>

						<?php /* translators: %s: Friend count. */ ?>
						<li id="activity-friends"><a href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . bp_get_friends_slug() . '/' ); ?>" title="<?php esc_attr_e( 'The activity of my friends only.', 'buddypress' ); ?>"><?php printf( wp_kses_post( __( 'My Friends <span>%s</span>', 'buddypress' ) ), (int) bp_get_total_friend_count( bp_loggedin_user_id() ) ); ?></a></li>

					<?php endif; ?>

				<?php endif; ?>

				<?php do_action( 'bp_before_activity_type_tab_groups' ); ?>

				<?php if ( bp_is_active( 'groups' ) ) : ?>

					<?php if ( bp_get_total_group_count_for_user( bp_loggedin_user_id() ) ) : ?>

						<?php /* translators: %s: Group count. */ ?>
						<li id="activity-groups"><a href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_activity_slug() . '/' . bp_get_groups_slug() . '/' ); ?>" title="<?php esc_attr_e( 'The activity of groups I am a member of.', 'buddypress' ); ?>"><?php printf( wp_kses_post( __( 'My Groups <span>%s</span>', 'buddypress' ) ), (int) bp_get_total_group_count_for_user( bp_loggedin_user_id() ) ); ?></a></li>

					<?php endif; ?>

				<?php endif; ?>

				<?php do_action( 'bp_before_activity_type_tab_favorites' ); ?>

				<?php if ( bp_get_total_favorite_count_for_user( bp_loggedin_user_id() ) ) : ?>

					<?php /* translators: %s: Favorite count. */ ?>
					<li id="activity-favorites"><a href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_activity_slug() . '/favorites/' ); ?>" title="<?php esc_attr_e( 'The activity I&#8217;ve marked as a favorite.', 'buddypress' ); ?>"><?php printf( wp_kses_post( __( 'My Favorites <span>%s</span>', 'buddypress' ) ), (int) bp_get_total_favorite_count_for_user( bp_loggedin_user_id() ) ); ?></a></li>

				<?php endif; ?>

				<?php do_action( 'bp_before_activity_type_tab_mentions' ); ?>

				<li id="activity-mentions"><a href="<?php echo esc_url( bp_loggedin_user_domain() . bp_get_activity_slug() . '/mentions/' ); ?>" title="<?php esc_attr_e( 'Activity that I have been mentioned in.', 'buddypress' ); ?>"><?php esc_html_e( 'Mentions', 'buddypress' ); ?>
				<?php
				if ( bp_get_total_mention_count_for_user( bp_loggedin_user_id() ) ) :
					?>
					<?php /* translators: %s: Number of new mentions. */ ?>
					<strong><?php printf( wp_kses_post( __( '<span>%s new</span>', 'buddypress' ) ), (int) bp_get_total_mention_count_for_user( bp_loggedin_user_id() ) ); ?></strong><?php endif; ?></a></li>

			<?php endif; ?>

			<?php do_action( 'bp_activity_type_tabs' ); ?>
		</ul>
	</div><!-- .item-list-tabs -->

	<div class="item-list-tabs no-ajax" id="subnav" role="navigation">
		<ul>
			<li class="feed"><a href="<?php bp_sitewide_activity_feed_link(); ?>" title="<?php esc_attr_e( 'RSS Feed', 'buddypress' ); ?>"><?php esc_html_e( 'RSS', 'buddypress' ); ?></a></li>

			<?php do_action( 'bp_activity_syndication_options' ); ?>

			<li id="activity-filter-select" class="last">
				<label for="activity-filter-by"><?php esc_html_e( 'Show:', 'buddypress' ); ?></label>
				<select id="activity-filter-by">
					<option value="-1"><?php esc_html_e( 'Everything', 'buddypress' ); ?></option>
					<option value="activity_update"><?php esc_html_e( 'Updates', 'buddypress' ); ?></option>

					<?php if ( bp_is_active( 'blogs' ) ) : ?>

						<option value="new_blog_post"><?php esc_html_e( 'Posts', 'buddypress' ); ?></option>
						<option value="new_blog_comment"><?php esc_html_e( 'Comments', 'buddypress' ); ?></option>

					<?php endif; ?>

					<?php if ( bp_is_active( 'forums' ) ) : ?>

						<option value="new_forum_topic"><?php esc_html_e( 'Forum Topics', 'buddypress' ); ?></option>
						<option value="new_forum_post"><?php esc_html_e( 'Forum Replies', 'buddypress' ); ?></option>

					<?php endif; ?>

					<?php if ( bp_is_active( 'groups' ) ) : ?>

						<option value="created_group"><?php esc_html_e( 'New Groups', 'buddypress' ); ?></option>
						<option value="joined_group"><?php esc_html_e( 'Group Memberships', 'buddypress' ); ?></option>

					<?php endif; ?>

					<?php if ( bp_is_active( 'friends' ) ) : ?>

						<option value="friendship_accepted,friendship_created"><?php esc_html_e( 'Friendships', 'buddypress' ); ?></option>

					<?php endif; ?>

					<option value="new_member"><?php esc_html_e( 'New Members', 'buddypress' ); ?></option>

					<?php do_action( 'bp_activity_filter_options' ); ?>

				</select>
			</li>
		</ul>
	</div><!-- .item-list-tabs -->

	<?php do_action( 'bp_before_directory_activity_list' ); ?>

	<div class="activity" role="main">

		<?php bp_get_template_part( 'activity/activity-loop' ); ?>

	</div><!-- .activity -->

	<?php do_action( 'bp_after_directory_activity_list' ); ?>

	<?php do_action( 'bp_directory_activity_content' ); ?>

	<?php do_action( 'bp_after_directory_activity_content' ); ?>

	<?php do_action( 'bp_after_directory_activity' ); ?>

</div>