<div class="sidebar">

	<?php if ( function_exists( 'is_bbpress' ) && ( is_bbpress() ) || is_page( 'new-topic' ) ) : ?>
	
		<?php if ( bbp_is_single_forum() || bb_base_topic_search_query( false ) ) : ?>

			<div>
				<h3><?php _e( 'Forum Info', 'bbporg'); ?></h3>
				<ul class="forum-info">
					<?php bb_base_single_forum_description(); ?>
				</ul>
			</div>

			<?php bb_base_topic_search_form(); ?>

			<div>
				<h3><?php _e( 'Forum Feeds', 'bbporg'); ?></h3>
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/" title="Forum Posts">Recent Posts</a></li>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/?type=topic" title="Forum Topics">Recent Topics</a></li>
				</ul>
			</div>

		<?php elseif ( bbp_is_single_topic() || bbp_is_topic_edit() || bbp_is_reply_edit() ) : ?>

			<div>
				<h3><?php _e( 'Topic Info', 'bbporg'); ?></h3>
				<ul class="topic-info">
					<?php bb_base_single_topic_description(); ?>
				</ul>
			</div>

			<div>
				<?php bbp_topic_tag_list( 0, array(
					'before' => '<h3>Topic Tags</h3><ul class="topic-tags"><li>',
					'after'  => '</li></ul>',
					'sep'    => '</li><li>',
				) ); ?>
			</div>

			<?php bb_base_reply_search_form(); ?>

			<?php if ( current_user_can( 'moderate', bbp_get_topic_id() ) ) : ?>

				<div>
					<?php bbp_topic_admin_links( array (
						'id'     => bbp_get_topic_id(),
						'before' => '<h3>Topic Admin</h3><ul class="topic-admin-links"><li>',
						'after'  => '</li></ul>',
						'sep'    => '</li><li>',
						'links'  => array()
					) ); ?>
				</div>

			<?php endif; ?>

		<?php elseif ( ! bbp_is_single_user() ) : ?>

			<div>
				<h3><?php _e( 'Forums', 'bbporg'); ?></h3>
				<?php echo do_shortcode( '[bbp-forum-index]' ); ?>
			</div>
			<hr class="hidden" />

			<div>
				<h3><?php _e( 'Views', 'bbporg'); ?></h3>
				<ul class="topic-views">

					<?php foreach ( bbp_get_views() as $view => $args ) : ?>

						<li><a class="bbp-view-title" href="<?php bbp_view_url( $view ); ?>" title="<?php bbp_view_title( $view ); ?>"><?php bbp_view_title( $view ); ?></a></li>

					<?php endforeach; ?>

				</ul>
			</div>

			<div>
				<h3><?php _e( 'Feeds', 'bbporg'); ?></h3>
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forums_url(); ?>feed/" title="All Recent Posts">All Recent Posts</a></li>
					<li><a class="feed" href="<?php bbp_topics_url(); ?>feed/" title="All Recent Topics">All Recent Topics</a></li>
				</ul>
			</div>

			<div>
				<h3><?php _e( 'Tags', 'bbporg'); ?></h3>
				<?php echo do_shortcode( '[bbp-topic-tags]' ); ?>
			</div>

		<?php endif; ?>

	<?php elseif ( is_front_page() || is_404() ) : ?>

		<div class="feature">
			<h3><?php _e('WordPress', 'bbporg'); ?></h3>
			<p><a href="http://wordpress.org"><img width="78" height="58" alt="" src="<?php echo get_template_directory_uri(); ?>/images/wordpress.gif"/></a>The world&#8217;s most powerful web publishing software.</p>
		</div>
		<div class="feature">
			<h3><?php _e('bbPress', 'bbporg'); ?></h3>
			<p><a href="http://bbpress.org"><img width="78" height="58" alt="" src="<?php echo get_template_directory_uri(); ?>/images/bbpress.gif"/></a>Simple and elegant forum software from the creators of WordPress.</p>
		</div>
		<div style="margin-right: 0pt;" class="feature">
			<h3><?php _e('BuddyPress', 'bbporg'); ?></h3>
			<p><a href="http://buddypress.org"><img width="78" height="58" alt="" src="<?php echo get_template_directory_uri(); ?>/images/buddypress.gif"/></a>Create a fully featured niche social-network with a few easy clicks.</p>
		</div>

	<?php elseif ( ( ! is_page( 'login' ) && ! is_page( 'register' ) && ! is_page( 'lost-password' ) ) || is_home() || is_singular( 'post' ) || is_archive() ) : ?>

		<div>
			<h3><?php _e( 'Categories', 'bbporg'); ?></h3>
			<ul class="blog-categories">
				<?php wp_list_categories( array( 'title_li' => false ) ); ?>
			</ul>
		</div>

		<div>
			<h3><?php _e( 'Tags', 'bbporg'); ?></h3>
			<?php wp_tag_cloud(); ?>
		</div>

	<?php endif; ?>

</div>
