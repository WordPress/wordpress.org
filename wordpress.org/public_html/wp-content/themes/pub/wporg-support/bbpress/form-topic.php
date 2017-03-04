<?php if ( ! bbp_is_single_forum() && ! bbp_is_single_view() ) : ?>

<div id="bbpress-forums">

<?php endif; ?>

<?php if ( bbp_current_user_can_access_create_topic_form() ) : ?>

	<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">

		<form id="new-post" name="new-post" method="post" action="">

			<?php do_action( 'bbp_theme_before_topic_form' ); ?>

			<fieldset class="bbp-form">
				<legend>

					<?php
						if ( bbp_is_topic_edit() ) {
							printf( __( 'Now Editing &ldquo;%s&rdquo;', 'wporg-forums' ), bbp_get_topic_title() );
						} else {
							if ( bbp_is_single_forum() ) {
								printf( __( 'Create a new topic in &ldquo;%s Forum&rdquo;', 'wporg-forums' ), bbp_get_forum_title() );
							} elseif ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) {
								_e( 'Create a new review', 'wporg-forums' );
							} else {
								_e( 'Create a new topic', 'wporg-forums' );
							}
						}
					?>

				</legend>

				<?php do_action( 'bbp_theme_before_topic_form_notices' ); ?>

				<?php if ( ! bbp_is_topic_edit() && ! bbp_is_forum_closed() ) : ?>

					<div class="bbp-template-notice">

						<?php if ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) : ?>

							<?php do_action( 'wporg_compat_new_review_notice' ); ?>

						<?php else : ?>

							<p><?php _e( 'When posting a new topic, follow these steps:', 'wporg-forums' ); ?></p>
							<ul>
								<li><?php
									/* translators: %s: Handbook URL for forum welcome */
									printf( __( '<strong>Read the <a href="%s">Forum Welcome</a></strong> to find out how to maximize your odds of getting help!', 'wporg-forums' ), esc_url( __( 'https://make.wordpress.org/support/handbook/forum-welcome/', 'wporg-forums' ) ) );
								?></li>
								<li><?php
									/* translators: %s: URL to search */
									printf( __( '<strong><a href="%s">Search</a> the forums</strong> to see if your topic has been resolved already.', 'wporg-forums' ), esc_url( bbp_get_search_url() ) );
								?></li>
								<li><?php _e( '<strong>Update to the latest versions</strong> of your plugins, themes, and WordPress.', 'wporg-forums' ); ?></li>
								<li><?php _e( '<strong>Note the exact steps</strong> needed to reproduce your issue.', 'wporg-forums' ); ?></li>
								<li><?php _e( '<strong>Provide any information</strong> you might think is useful. If your issue is visual, note your browser and operating system. If your issue is technical, note your server environment.', 'wporg-forums' ); ?></li>
								<?php if ( ! bbp_is_single_view() || ! in_array( bbp_get_view_id(), array( 'theme', 'plugin' ) ) ) : ?>
								<li><?php
									/* translators: 1: Theme Directory URL, 2: Appearance icon, 3: Plugin Directory URL, 4: Plugins icon */
									printf( __( '<strong>Looking for help with a specific <a href="%1$s">%2$s theme</a> or <a href="%3$s">%4$s plugin</a>?</strong> Don\'t post here &#8211; instead, head to the theme or plugin\'s page and find the "View support forum" link to visit the theme or plugin\'s individual forum.', 'wporg-forums' ),
										esc_url( __( 'https://wordpress.org/themes/', 'wporg-forums' ) ),
										'<span class="dashicons dashicons-admin-appearance"></span>',
										esc_url( __( 'https://wordpress.org/plugins/', 'wporg-forums' ) ),
										'<span class="dashicons dashicons-admin-plugins"></span>'
									);
								?></li>
								<?php endif; ?>
							</ul>

						<?php endif; ?>

					</div>

				<?php endif; ?>

				<?php if ( !bbp_is_topic_edit() && bbp_is_forum_closed() ) : ?>

					<div class="bbp-template-notice">
						<p><?php _e( 'This forum is marked as closed to new topics, however your posting capabilities still allow you to create a topic.', 'wporg-forums' ); ?></p>
					</div>

				<?php endif; ?>

				<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>

					<div class="bbp-template-notice">
						<p><?php _e( 'Your account has the ability to post unrestricted HTML content.', 'wporg-forums' ); ?></p>
					</div>

				<?php endif; ?>

				<?php do_action( 'bbp_template_notices' ); ?>

				<div>

					<?php bbp_get_template_part( 'form', 'anonymous' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_title' ); ?>

					<p>
						<label for="bbp_topic_title"><?php
							if ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) {
								printf( __( 'Review Title (Maximum Length: %d):', 'wporg-forums' ), bbp_get_title_max_length() );
							} else {
								printf( __( 'Topic Title (Maximum Length: %d):', 'wporg-forums' ), bbp_get_title_max_length() );
							}
						?></label><br />
						<input type="text" id="bbp_topic_title" value="<?php bbp_form_topic_title(); ?>" size="40" name="bbp_topic_title" maxlength="<?php bbp_title_max_length(); ?>" />
					</p>

					<?php do_action( 'bbp_theme_after_topic_form_title' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_content' ); ?>

					<?php if ( !function_exists( 'wp_editor' ) ) : ?>

						<p>
							<label for="bbp_reply_content"><?php _e( 'Reply:', 'wporg-forums' ); ?></label><br />
							<textarea id="bbp_topic_content" name="bbp_topic_content" cols="60" rows="6"><?php bbp_form_topic_content(); ?></textarea>
						</p>

					<?php else : ?>

						<?php bbp_the_content( array( 'context' => 'topic' ) ); ?>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_after_topic_form_content' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_tags' ); ?>

					<p>
						<label for="bbp_topic_tags"><?php
							if ( bbp_is_single_view() && 'reviews' === bbp_get_view_id() ) {
								_e( 'Review Tags:', 'wporg-forums' );
							} else {
								_e( 'Topic Tags:', 'wporg-forums' );
							}
						?></label><br />
						<input type="text" value="<?php bbp_form_topic_tags(); ?>" size="40" name="bbp_topic_tags" id="bbp_topic_tags" <?php disabled( bbp_is_topic_spam() ); ?> />
					</p>

					<?php do_action( 'bbp_theme_after_topic_form_tags' ); ?>

					<?php if ( ! bbp_is_single_forum() && ! bbp_is_single_view() ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_forum' ); ?>

						<p>
							<label for="bbp_forum_id"><?php _e( 'Forum:', 'wporg-forums' ); ?></label><br />
							<?php bbp_dropdown( array( 'selected' => bbp_get_form_topic_forum() ) ); ?>
						</p>

						<?php do_action( 'bbp_theme_after_topic_form_forum' ); ?>

					<?php endif; ?>

					<?php if ( current_user_can( 'moderate' ) ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_type' ); ?>

						<p>

							<label for="bbp_stick_topic_select"><?php _e( 'Topic Type:', 'wporg-forums' ); ?></label><br />

							<?php bbp_topic_type_select(); ?>

						</p>

						<?php do_action( 'bbp_theme_after_topic_form_type' ); ?>

					<?php endif; ?>

					<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() && ( !bbp_is_topic_edit() || ( bbp_is_topic_edit() && !bbp_is_topic_anonymous() ) ) ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_subscriptions' ); ?>

						<p>
							<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe" <?php bbp_form_topic_subscribed(); ?> />

							<?php if ( bbp_is_topic_edit() && ( get_the_author_meta( 'ID' ) != bbp_get_current_user_id() ) ) : ?>

								<label for="bbp_topic_subscription"><?php _e( 'Notify the author of follow-up replies via email', 'wporg-forums' ); ?></label>

							<?php else : ?>

								<label for="bbp_topic_subscription"><?php _e( 'Notify me of follow-up replies via email', 'wporg-forums' ); ?></label>

							<?php endif; ?>
						</p>

						<?php do_action( 'bbp_theme_after_topic_form_subscriptions' ); ?>

					<?php endif; ?>

					<?php if ( bbp_allow_revisions() && bbp_is_topic_edit() ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_revisions' ); ?>

						<fieldset class="bbp-form">
							<legend><?php _e( 'Revision', 'wporg-forums' ); ?></legend>
							<div>
								<input name="bbp_log_topic_edit" id="bbp_log_topic_edit" type="checkbox" value="1" <?php bbp_form_topic_log_edit(); ?> />
								<label for="bbp_log_topic_edit"><?php _e( 'Keep a log of this edit:', 'wporg-forums' ); ?></label><br />
							</div>

							<div>
								<label for="bbp_topic_edit_reason"><?php _e( 'Optional reason for editing:', 'wporg-forums' ); ?></label><br />
								<input type="text" value="<?php bbp_form_topic_edit_reason(); ?>" size="40" name="bbp_topic_edit_reason" id="bbp_topic_edit_reason" />
							</div>
						</fieldset>

						<?php do_action( 'bbp_theme_after_topic_form_revisions' ); ?>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_before_topic_form_submit_wrapper' ); ?>

					<div class="bbp-submit-wrapper">

						<?php do_action( 'bbp_theme_before_topic_form_submit_button' ); ?>

						<button type="submit" id="bbp_topic_submit" name="bbp_topic_submit" class="button button-primary submit"><?php _e( 'Submit', 'wporg-forums' ); ?></button>

						<?php do_action( 'bbp_theme_after_topic_form_submit_button' ); ?>

					</div>

					<?php do_action( 'bbp_theme_after_topic_form_submit_wrapper' ); ?>

				</div>

				<?php bbp_topic_form_fields(); ?>

			</fieldset>

			<?php do_action( 'bbp_theme_after_topic_form' ); ?>

		</form>
	</div>

<?php elseif ( bbp_is_forum_closed() ) : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<div class="bbp-template-notice">
			<p><?php printf( __( 'The forum &#8216;%s&#8217; is closed to new topics and replies.', 'wporg-forums' ), bbp_get_forum_title() ); ?></p>
		</div>
	</div>

<?php else : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<div class="bbp-template-notice">
			<?php if ( is_user_logged_in() ) : ?>
				<p><?php _e( 'You cannot create new topics at this time.', 'wporg-forums' ); ?></p>
			<?php else : ?>
				<p><?php printf( __( 'You must be <a href="%s">logged in</a> to create new topics.', 'wporg-forums' ), wp_login_url() ); ?></p>
			<?php endif; ?>
		</div>
	</div>

<?php endif; ?>

<?php if ( ! bbp_is_single_forum() && ! bbp_is_single_view() ) : ?>

</div>

<?php endif; ?>
