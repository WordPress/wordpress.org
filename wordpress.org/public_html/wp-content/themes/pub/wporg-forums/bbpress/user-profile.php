<?php

/**
 * User Profile
 *
 * @package bbPress
 * @subpackage Theme
 */

do_action( 'bbp_template_before_user_profile' ); ?>

<div id="bbp-user-profile" class="bbp-user-profile">
	<h2 class="entry-title"><?php esc_html_e( 'Profile', 'wporg-forums' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_displayed_user_field( 'description' ) ) : ?>

			<p class="bbp-user-description"><?php bbp_displayed_user_field( 'description' ); ?></p>

		<?php endif; ?>

		<?php if ( current_user_can( 'moderate' ) ) : ?>

			<p class="bbp-user-email"><?php
				/* translators: %s: user's email address */
				printf( esc_html__( 'Email: %s', 'wporg-forums' ), bbp_get_displayed_user_field( 'user_email' ) );
			?></p>

		<?php endif; ?>

		<p class="bbp-user-wporg-profile"><?php
			$user_nicename  = bbp_get_displayed_user_field( 'user_nicename' );
			$slack_username = wporg_support_get_slack_username();

			if ( $slack_username && $slack_username != $user_nicename ) {
				/* translators: 1: user's WordPress.org profile link, 2: user's Slack username, 3: make.wordpress.org/chat URL */
				printf( __( '%1$s on WordPress.org, %2$s on <a href="%3$s">Slack</a>', 'wporg-forums' ),
					wporg_support_get_wporg_profile_link(),
					'@' . $slack_username,
					'https://make.wordpress.org/chat/'
				);
			} elseif( $slack_username ) {
				/* translators: 1: WordPress.org and Slack username, 2: URL for information about Slack */
				printf( __( '%1$s on WordPress.org and <a href="%2$s">Slack</a>', 'wporg-forums' ),
					wporg_support_get_wporg_profile_link(),
					'https://make.wordpress.org/chat/'
				);
			} else {
				/* translators: %s: user's WordPress.org profile link */
				printf( esc_html__( '%s on WordPress.org', 'wporg-forums' ),
					wporg_support_get_wporg_profile_link()
				);
			}
		?></p>

		<?php if ( $custom_title = get_user_option( 'title', bbp_get_displayed_user_id() ) ) : ?>
	
			<p class="bbp-user-custom-title"><?php
				/* translators: %s: user's custom title */
				printf( esc_html__( 'Title: %s', 'wporg-forums' ), esc_html( $custom_title ) );
			?></p>
	
		<?php endif; ?>

		<p class="bbp-user-forum-role"><?php
			/* translators: %s: user's forum role */
			printf( esc_html__( 'Forum Role: %s', 'wporg-forums' ), bbp_get_user_display_role() );
		?></p>

		<?php if ( $website = bbp_get_displayed_user_field( 'user_url' ) ) : ?>
	
			<p class="bbp-user-website"><?php
			/* translators: %s: link to user's website */ 
			printf( esc_html__( 'Website: %s', 'wporg-forums' ), sprintf( '<a href="%s">%s</a>', esc_url( $website ), esc_html( $website ) ) );
			?></p>
	
		<?php endif; ?>

		<p class="bbp-user-member-since"><?php
			/* translators: %s: user's registration date */
			printf( esc_html__( 'Member Since: %s', 'wporg-forums' ), wporg_support_get_user_registered_date() );
		?></p>

		<p class="bbp-user-topic-count"><?php
			/* translators: %s: number of user's topics */
			printf( esc_html__( 'Topics Started: %s', 'wporg-forums' ), bbp_get_user_topic_count_raw() );
		?></p>

		<p class="bbp-user-reply-count"><?php
			/* translators: %s: number of user's replies */
			printf( esc_html__( 'Replies Created: %s', 'wporg-forums' ), bbp_get_user_reply_count_raw() );
		?></p>
	</div>
</div><!-- #bbp-author-topics-started -->

<?php do_action( 'bbp_template_after_user_profile' );
