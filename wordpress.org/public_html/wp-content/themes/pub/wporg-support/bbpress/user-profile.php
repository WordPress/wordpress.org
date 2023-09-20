<?php

/**
 * User Profile
 *
 * @package bbPress
 * @subpackage Theme
 */

$is_user_blocked     = bbpress()->displayed_user->has_cap( bbp_get_blocked_role() );
$hide_profile_fields = ( $is_user_blocked && ! current_user_can( 'moderate' ) );

do_action( 'bbp_template_before_user_profile' ); ?>

<div id="bbp-user-profile" class="bbp-user-profile">
	<h2 class="entry-title"><?php esc_html_e( 'Profile', 'wporg-forums' ); ?></h2>
	<div class="bbp-user-section"><?php
		if ( current_user_can( 'moderate' ) && class_exists( 'WordPressdotorg\Forums\User_Moderation\Plugin' ) ) {
			$displayed_user_id = bbp_get_displayed_user_id();
			$plugin_instance   = WordPressdotorg\Forums\User_Moderation\Plugin::get_instance();
			$is_user_flagged   = $plugin_instance->is_user_flagged( $displayed_user_id );
			$moderator         = get_user_meta( $displayed_user_id, $plugin_instance::MODERATOR_META, true );
			$moderation_date   = get_user_meta( $displayed_user_id, $plugin_instance::MODERATION_DATE_META, true );

			if ( $is_user_flagged ) {
				if ( $moderator && $moderation_date ) {
					$msg = sprintf(
						/* translators: 1: linked moderator's username, 2: moderation date, 3: moderation time */
						__( 'This user has been flagged by %1$s on %2$s at %3$s.', 'wporg-forums' ),
						sprintf( '<a href="%s">%s</a>', esc_url( home_url( "/users/$moderator/" ) ), $moderator ),
						/* translators: localized date format, see https://www.php.net/date */
						mysql2date( __( 'F j, Y', 'wporg-forums' ), $moderation_date ),
						/* translators: localized time format, see https://www.php.net/date */
						mysql2date( __( 'g:i a', 'wporg-forums' ), $moderation_date )
					);
				} elseif ( $moderator ) {
					$msg = sprintf(
						/* translators: %s: linked moderator's username */
						__( 'This user has been flagged by %s.', 'wporg-forums' ),
						sprintf( '<a href="%s">%s</a>', esc_url( home_url( "/users/$moderator/" ) ), $moderator )
					);
				} else {
					$msg = __( 'This user has been flagged.', 'wporg-forums' );
				}

				printf(
					'<div class="bbp-template-notice warning"><p>%s</p></div>',
					$msg
				);
			}
		}
		?>

		<?php if ( ! $hide_profile_fields && bbp_get_displayed_user_field( 'description' ) ) : ?>

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

		<?php if ( ! $hide_profile_fields && ( $custom_title = get_user_option( 'title', bbp_get_displayed_user_id() ) ) ) : ?>

			<p class="bbp-user-custom-title"><?php
				/* translators: %s: user's custom title */
				printf( esc_html__( 'Title: %s', 'wporg-forums' ), esc_html( $custom_title ) );
			?></p>

		<?php endif; ?>

		<?php
		// Only show the forum role when they're privledged, or the current user is privledged.
		if (
			current_user_can( 'moderate' ) ||
			bbpress()->displayed_user->has_cap( bbp_get_moderator_role() ) ||
			bbpress()->displayed_user->has_cap( bbp_get_keymaster_role() )
		) {
			?><p class="bbp-user-forum-role"><?php
			/* translators: %s: user's forum role */
			printf( esc_html__( 'Forum Role: %s', 'wporg-forums' ), bbp_get_user_display_role() );
			?></p><?php
		}
		?>

		<?php if ( is_user_logged_in() && ! $hide_profile_fields && ( $website = bbp_get_displayed_user_field( 'user_url' ) ) ) : ?>

			<p class="bbp-user-website"><?php
				/* translators: %s: link to user's website */
				printf( esc_html__( 'Website: %s', 'wporg-forums' ), sprintf( '<a href="%s" rel="nofollow ugc">%s</a>', esc_url( $website ), esc_html( $website ) ) );
			?></p>

		<?php endif; ?>

		<p class="bbp-user-member-since"><?php
			/* translators: %s: user's registration date */
			printf( esc_html__( 'Member Since: %s', 'wporg-forums' ), wporg_support_get_user_registered_date() );
		?></p>

		<p class="bbp-user-topic-count"><?php
			/* translators: %s: number of user's topics */
			printf( esc_html__( 'Topics Started: %s', 'wporg-forums' ), number_format_i18n( wporg_support_get_user_topics_count() ) );
		?></p>

		<p class="bbp-user-reply-count"><?php
			/* translators: %s: number of user's replies */
			printf( esc_html__( 'Replies Created: %s', 'wporg-forums' ), number_format_i18n( bbp_get_user_reply_count_raw() ) );
		?></p>

		<?php if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) && WPORG_SUPPORT_FORUMS_BLOGID == get_current_blog_id() ) : ?>
			<p class="bbp-user-review-count"><?php
				/* translators: %s: number of user's reviews */
				printf( esc_html__( 'Reviews Written: %s', 'wporg-forums' ), number_format_i18n( wporg_support_get_user_reviews_count() ) );
			?></p>
		<?php endif; ?>

		<?php if ( bbp_is_user_home() || current_user_can( 'moderate' ) ) : ?>
			<p class="bbp-user-report-count"><?php
				/* translators: %s: number of user's reviews */
				printf( esc_html__( 'Reports Submitted: %s', 'wporg-forums' ), number_format_i18n( wporg_support_get_user_report_count() ) );
			?></p>
		<?php endif; ?>
	</div>
</div><!-- #bbp-author-topics-started -->

<?php do_action( 'bbp_template_after_user_profile' );
