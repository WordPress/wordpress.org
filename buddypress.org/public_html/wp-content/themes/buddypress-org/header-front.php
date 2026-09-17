<?php if ( bb_base_is_buddypress() && is_front_page() ) : ?>

	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php esc_html_e( 'Meet BuddyPress', 'bporg' ); ?></h2>
		<p><?php esc_html_e( 'The open source community platform of choice worldwide — from creators & small businesses to enterprises & governments.', 'bporg' ); ?></p>
		<div>
			<a href="<?php bloginfo( 'url' ); ?>/download/" id="big-demo-button" class="button"><?php esc_html_e( 'Download &rarr;', 'bporg' ); ?></a>
			<img src="<?php echo esc_url( get_theme_file_uri( 'images/screenshots.png' ) . '?v=6' ); ?>" srcset="<?php echo esc_url( get_theme_file_uri( 'images/screenshots.png' ) . '?v=6' ); ?> 1x, <?php echo esc_url( get_theme_file_uri( 'images/screenshots-2x.png' ) . '?v=6' ); ?> 2x" alt="">
		</div>
	</div></div>
	<hr class="hidden" />

	<div id="showcase"><div id="showcase-inner">
		<div class="feature">
			<h2><?php esc_html_e( 'Profiles', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/profiles/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_profiles.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Custom profile fields.<br> Visibility levels.<br>Common field types.', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( 'Settings', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/settings/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_settings.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Manage account settings. <br>Email notifications. <br>Email and Password.', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( 'Groups', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/groups/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_groups.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Extensible user groups. <br>Allow your users to <br>create micro-communities.', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( 'Activity Streams', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/activity/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_activity.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'For members and groups. <br>Sitewide directory <br>and single threads.', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( 'Notifications', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/notifications/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_notifications.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Get notified.<br> Smart read/unread. <br>Fully integrated.', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( 'Friendships', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/friends/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_friends.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Friendship connections. <br>It&#8217;s always about <br>who you know!', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( 'Private Messaging', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/private-messaging/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_pms.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Private conversations <br>with several members <br>at one time.', 'bporg' ) ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php esc_html_e( '...and more!', 'bporg' ); ?></h2>
			<p>
				<a href="//buddypress.org/about/more/"><img src="<?php echo esc_url( get_theme_file_uri( 'images/feature_more.gif' ) ); ?>" alt="" width="156" height="116"></a>
				<span><?php echo wp_kses_post( __( 'Extend BuddyPress <br>with hundreds of <br>third party components.', 'bporg' ) ); ?></span>
			</p>
		</div>
	</div></div>

<?php endif;
