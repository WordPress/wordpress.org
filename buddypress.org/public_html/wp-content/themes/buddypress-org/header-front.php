<?php if ( bb_base_is_buddypress() && is_front_page() ) : ?>

	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php _e( 'Fun & flexible software for online communities, teams, and groups', 'bporg' ); ?></h2>
		<p><?php _e( 'BuddyPress helps you build any kind of community website using WordPress, with member profiles, activity streams, user groups, messaging, and more.', 'bporg' ); ?></p>
		<div>
			<a href="<?php bloginfo( 'url' ); ?>/download/" id="big-demo-button" class="button"><?php _e( 'Download BuddyPress &rarr;', 'bporg' ); ?></a>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=6" srcset="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=6 1x, <?php echo get_stylesheet_directory_uri(); ?>/images/screenshots-2x.png?v=6 2x" alt="">
		</div>
	</div></div>
	<hr class="hidden" />

	<div id="showcase"><div id="showcase-inner">
		<div class="feature">
			<h3><?php _e( 'Profiles', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/profiles/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_profiles.gif" alt="" width="78" height="58"></a>
				<?php _e( 'Custom profile fields.<br /> Visibility levels.<br /> Common field types.', 'bporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Settings', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/settings/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_settings.gif" alt="" width="78" height="58"></a>
				<?php _e( 'Manage account settings.<br /> Email notifications.<br /> Email and Password.', 'bporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Groups', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/groups/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_groups.gif" alt="" width="78" height="58"></a>
				<?php _e( 'Extensible user groups. Allow your users to create micro-communities.', 'bporg' ); ?>
			</p>
		</div>
		<div class="feature" style="margin:0;">
			<h3><?php _e( 'Activity Streams', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/activity/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_activity.gif" alt="" width="78" height="58"></a>
				<?php _e( 'For members and groups. Sitewide directory and single threads.', 'bporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Notifications', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/notifications/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_notifications.gif" alt="" width="78" height="58"></a>
				<?php _e( 'Get notified.<br /> Smart read/unread.<br /> Fully integrated.', 'bporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Friendships', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/friends/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_friends.gif" alt="" width="78" height="58"></a>
				<?php _e( "Friendship connections.<br /> It's always about<br /> who you know!", 'bporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Private Messaging', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/private-messaging/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_pms.gif" alt="" width="78" height="58"></a>
				<?php _e( 'Private conversations, with several members at one time.', 'bporg' ); ?>
			</p>
		</div>
		<div class="feature" style="margin:0;">
			<h3><?php _e( '...and more!', 'bporg' ); ?></h3>
			<p>
				<a href="//buddypress.org/about/more/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_more.gif" alt="" width="78" height="58"></a>
				<?php _e( 'Extend BuddyPress with hundreds of third party components.', 'bporg' ); ?>
			</p>
		</div>
	</div></div>

<?php endif;
