<div class="sidebar">

	<?php if ( function_exists( 'is_bbpress' ) && ( is_bbpress() ) || is_page( 'new-topic' ) ) : ?>

		<?php if ( bbp_is_single_forum() || ( bb_is_intl_forum() && bb_base_topic_search_query( false ) ) ) : ?>

			<div>
				<h3><?php _e( 'Forum Info', 'wporg-forums' ); ?></h3>
				<ul class="forum-info">
					<?php bb_base_single_forum_description(); ?>
				</ul>
			</div>

			<div>
				<?php
					if ( bb_is_intl_forum() ) :
						bb_base_topic_search_form();
					else :
						bb_base_search_form();
					endif;
				?>
			</div>

			<div>
				<h3><?php _e( 'Forum Feeds', 'wporg-forums' ); ?></h3>
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/"><?php _e( 'Recent Posts', 'wporg-forums' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_forum_permalink(); ?>feed/?type=topic"><?php _e( 'Recent Topics', 'wporg-forums' ); ?></a></li>
				</ul>
			</div>

		<?php elseif ( bbp_is_single_view() && in_array( bbp_get_view_id(), array( 'theme', 'plugin', 'reviews', 'active' ) ) ) : ?>

			<?php do_action( 'wporg_compat_view_sidebar' ); ?>

		<?php elseif ( bbp_is_single_topic() || bbp_is_topic_edit() || bbp_is_reply_edit() ) : ?>

			<?php do_action( 'wporg_compat_single_topic_sidebar_pre' ); ?>

			<div>
				<h3><?php _e( 'Topic Info', 'wporg-forums' ); ?></h3>
				<ul class="topic-info">
					<?php bb_base_single_topic_description(); ?>
				</ul>
			</div>

			<div>
				<?php bbp_topic_tag_list( 0, array(
					'before' => '<h3>' . __( 'Topic Tags', 'wporg-forums' ) . '</h3><ul class="topic-tags"><li>',
					'after'  => '</li></ul>',
					'sep'    => '</li><li>',
				) ); ?>
			</div>

			<div>
				<?php
					if ( bb_is_intl_forum() ) :
						bb_base_reply_search_form();
					else :
						bb_base_search_form();
					endif;
				?>
			</div>

			<?php if ( current_user_can( 'moderate', bbp_get_topic_id() ) ) : ?>

				<div>
					<?php bbp_topic_admin_links( array (
						'id'     => bbp_get_topic_id(),
						'before' => '<h3>' . __( 'Topic Admin', 'wporg-forums' ) . '</h3><ul class="topic-admin-links"><li>',
						'after'  => '</li></ul>',
						'sep'    => '</li><li>',
						'links'  => array()
					) ); ?>
				</div>

			<?php endif; ?>

		<?php elseif ( ! bbp_is_single_user() ) : ?>

			<div>
				<h3><?php _e( 'Views', 'wporg-forums' ); ?></h3>
				<ul class="topic-views">

					<?php foreach ( bbp_get_views() as $view => $args ) : ?>

						<li><a class="bbp-view-title" href="<?php bbp_view_url( $view ); ?>"><?php bbp_view_title( $view ); ?></a></li>

					<?php endforeach; ?>

				</ul>
			</div>

			<div>
				<h3><?php _e( 'Feeds', 'wporg-forums' ); ?></h3>
				<ul class="forum-feeds">
					<li><a class="feed" href="<?php bbp_forums_url(); ?>feed/"><?php _e( 'All Recent Posts', 'wporg-forums' ); ?></a></li>
					<li><a class="feed" href="<?php bbp_topics_url(); ?>feed/"><?php _e( 'All Recent Topics', 'wporg-forums' ); ?></a></li>
				</ul>
			</div>

			<div>
				<h3><?php _e( 'Tags', 'wporg-forums' ); ?></h3>
				<?php echo do_shortcode( '[bbp-topic-tags]' ); ?>
			</div>

		<?php endif; ?>

	<?php elseif ( is_front_page() ) : ?>

		<div class="feature">
			<h3><?php _e( 'WordPress', 'wporg-forums' ); ?></h3>
			<p><a href="http://wordpress.org"><img width="78" height="58" alt="" src="<?php echo get_template_directory_uri(); ?>/images/wordpress.gif"/></a>The world&#8217;s most powerful web publishing software.</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'bbPress', 'wporg-forums' ); ?></h3>
			<p><a href="http://bbpress.org"><img width="78" height="58" alt="" src="<?php echo get_template_directory_uri(); ?>/images/bbpress.gif"/></a>Simple and elegant forum software from the creators of WordPress.</p>
		</div>
		<div style="margin-right: 0pt;" class="feature">
			<h3><?php _e( 'BuddyPress', 'wporg-forums' ); ?></h3>
			<p><a href="http://buddypress.org"><img width="78" height="58" alt="" src="<?php echo get_template_directory_uri(); ?>/images/buddypress.gif"/></a>Create a fully featured niche social-network with a few easy clicks.</p>
		</div>

	<?php elseif ( ( ! is_page( 'login' ) && ! is_page( 'register' ) && ! is_page( 'lost-password' ) ) || is_home() || is_singular( 'post' ) || is_archive() ) : ?>

		<div>
			<h3><?php _e( 'Categories', 'wporg-forums' ); ?></h3>
			<ul class="blog-categories">
				<?php wp_list_categories( array( 'title_li' => false ) ); ?>
			</ul>
		</div>

		<div>
			<h3><?php _e( 'Tags', 'wporg-forums' ); ?></h3>
			<?php wp_tag_cloud( array( 'smallest' => 14, 'largest' => 24, 'number' => 22, 'taxonomy' => bbp_get_topic_tag_tax_id() ) ); ?>
		</div>

	<?php endif; ?>

</div>
