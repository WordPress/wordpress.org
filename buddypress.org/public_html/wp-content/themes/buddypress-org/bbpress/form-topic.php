<?php if ( !bbp_is_single_forum() ) : ?>

<div id="bbpress-forums">

<?php endif; ?>

<?php if ( bbp_current_user_can_access_create_topic_form() ) : ?>

	<script type="text/javascript">
		document.body.className = document.body.className.replace( 'no-js', 'js' );
	</script>

	<div id="new-topic-<?php bbp_topic_id(); ?>" class="bbp-topic-form">

		<form id="new-post" name="new-post" method="post" action="">

			<?php do_action( 'bbp_theme_before_topic_form' ); ?>

			<fieldset class="bbp-form">
				<legend>

					<?php
						if ( bbp_is_topic_edit() )
							/* translators: %s: Topic title. */
							printf( esc_html__( 'Now Editing &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_topic_title() );
						else
							/* translators: %s: Forum title. */
							bbp_is_single_forum() ? printf( esc_html__( 'Create New Topic in &ldquo;%s&rdquo;', 'bbpress' ), bbp_get_forum_title() ) : esc_html_e( 'Create New Topic', 'bbpress' );
					?>

				</legend>

				<?php do_action( 'bbp_theme_before_topic_form_notices' ); ?>

				<?php if ( ! bbp_is_topic_edit() && ! bbp_is_forum_closed() ) : ?>

					<div class="bbp-template-notice">
						<p><?php esc_html_e( 'If asking for help, please include your WordPress version, BuddyPress version, and a link to your site.', 'bporg' ); ?></p>
						<p><?php esc_html_e( 'If you are using a non-bundled theme, confirm your issue still happens with Twenty Twelve, Twenty Thirteen, etc...', 'bporg' ); ?></p>
						<p><?php echo wp_kses_post( __( 'If you found a bug, please create a <a href="https://buddypress.trac.wordpress.org/">trac ticket</a> with detailed duplication steps.', 'bporg' ) ); ?></p>
						<p><?php esc_html_e( 'Posts with 3 or more links will be manually approved by our forum moderators.', 'bporg' ); ?></p>
					</div>

				<?php endif; ?>

				<?php if ( !bbp_is_topic_edit() && bbp_is_forum_closed() ) : ?>

					<div class="bbp-template-notice">
						<p><?php esc_html_e( 'This forum is marked as closed to new topics, however your posting capabilities still allow you to do so.', 'bbpress' ); ?></p>
					</div>

				<?php endif; ?>

				<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>

					<div class="bbp-template-notice">
						<p><?php esc_html_e( 'Your account has the ability to post unrestricted HTML content.', 'bbpress' ); ?></p>
					</div>

				<?php endif; ?>

				<?php do_action( 'bbp_template_notices' ); ?>

				<div>

					<?php bbp_get_template_part( 'form', 'anonymous' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_title' ); ?>

					<p>
						<?php /* translators: %d: Maximum title length. */ ?>
						<label for="bbp_topic_title"><?php printf( esc_html__( 'Topic Title (Maximum Length: %d):', 'bbpress' ), bbp_get_title_max_length() ); ?></label><br />
						<input type="text" id="bbp_topic_title" value="<?php bbp_form_topic_title(); ?>" size="40" name="bbp_topic_title" maxlength="<?php bbp_title_max_length(); ?>" />
					</p>

					<?php do_action( 'bbp_theme_after_topic_form_title' ); ?>

					<?php do_action( 'bbp_theme_before_topic_form_content' ); ?>

					<?php if ( !function_exists( 'wp_editor' ) ) : ?>

						<p>
							<label for="bbp_reply_content"><?php esc_html_e( 'Reply:', 'bbpress' ); ?></label><br />
							<textarea id="bbp_topic_content" name="bbp_topic_content" cols="60" rows="6"><?php bbp_form_topic_content(); ?></textarea>
						</p>

					<?php else : ?>

						<?php bbp_the_content( array( 'context' => 'topic' ) ); ?>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_after_topic_form_content' ); ?>

					<?php if ( !current_user_can( 'unfiltered_html' ) ) : ?>

						<p class="form-allowed-tags">
							<label><?php echo wp_kses_post( __( 'You may use these <abbr title="HyperText Markup Language">HTML</abbr> tags and attributes:', 'bbpress' ) ); ?></label><br />
							<code><?php bbp_allowed_tags(); ?></code>
						</p>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_before_topic_form_tags' ); ?>

					<p>
						<label for="bbp_topic_tags"><?php esc_html_e( 'Topic Tags:', 'bbpress' ); ?></label><br />
						<input type="text" value="<?php bbp_form_topic_tags(); ?>" size="40" name="bbp_topic_tags" id="bbp_topic_tags" <?php disabled( bbp_is_topic_spam() ); ?> />
					</p>

					<?php do_action( 'bbp_theme_after_topic_form_tags' ); ?>

					<?php if ( !bbp_is_single_forum() ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_forum' ); ?>

						<p>
							<label for="bbp_forum_id"><?php esc_html_e( 'Forum:', 'bbpress' ); ?></label><br />
							<?php bbp_dropdown( array( 'selected' => bbp_get_form_topic_forum() ) ); ?>
						</p>

						<?php do_action( 'bbp_theme_after_topic_form_forum' ); ?>

					<?php endif; ?>

					<?php if ( current_user_can( 'moderate' ) ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_type' ); ?>

						<p>

							<label for="bbp_stick_topic"><?php esc_html_e( 'Topic Type:', 'bbpress' ); ?></label><br />

							<?php bbp_topic_type_select(); ?>

						</p>

						<?php do_action( 'bbp_theme_after_topic_form_type' ); ?>

					<?php endif; ?>

					<?php if ( bbp_is_subscriptions_active() && !bbp_is_anonymous() && ( !bbp_is_topic_edit() || ( bbp_is_topic_edit() && !bbp_is_topic_anonymous() ) ) ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_subscriptions' ); ?>

						<p>
							<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe" <?php bbp_form_topic_subscribed(); ?> />

							<?php if ( bbp_is_topic_edit() && ( get_the_author_meta( 'ID' ) != bbp_get_current_user_id() ) ) : ?>

								<label for="bbp_topic_subscription"><?php esc_html_e( 'Notify the author of follow-up replies via email', 'bbpress' ); ?></label>

							<?php else : ?>

								<label for="bbp_topic_subscription"><?php esc_html_e( 'Notify me of follow-up replies via email', 'bbpress' ); ?></label>

							<?php endif; ?>
						</p>

						<?php do_action( 'bbp_theme_after_topic_form_subscriptions' ); ?>

					<?php endif; ?>

					<?php if ( bbp_allow_revisions() && bbp_is_topic_edit() ) : ?>

						<?php do_action( 'bbp_theme_before_topic_form_revisions' ); ?>

						<fieldset class="bbp-form">
							<legend>
								<input name="bbp_log_topic_edit" id="bbp_log_topic_edit" type="checkbox" value="1" <?php bbp_form_topic_log_edit(); ?> />
								<label for="bbp_log_topic_edit"><?php esc_html_e( 'Keep a log of this edit:', 'bbpress' ); ?></label><br />
							</legend>

							<div>
								<label for="bbp_topic_edit_reason"><?php printf( esc_html__( 'Optional reason for editing:', 'bbpress' ), bbp_get_current_user_name() ); ?></label><br />
								<input type="text" value="<?php bbp_form_topic_edit_reason(); ?>" size="40" name="bbp_topic_edit_reason" id="bbp_topic_edit_reason" />
							</div>
						</fieldset>

						<?php do_action( 'bbp_theme_after_topic_form_revisions' ); ?>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_before_topic_form_submit_wrapper' ); ?>

					<div class="bbp-submit-wrapper">

						<?php do_action( 'bbp_theme_before_topic_form_submit_button' ); ?>

						<button type="submit" id="bbp_topic_submit" name="bbp_topic_submit" class="button submit"><?php esc_html_e( 'Submit', 'bbpress' ); ?></button>

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
			<?php /* translators: %s: Forum title. */ ?>
			<p><?php printf( esc_html__( 'The forum &#8216;%s&#8217; is closed to new topics and replies.', 'bbpress' ), bbp_get_forum_title() ); ?></p>
		</div>
	</div>

<?php else : ?>

	<div id="no-topic-<?php bbp_topic_id(); ?>" class="bbp-no-topic">
		<div class="bbp-template-notice">
			<p><?php is_user_logged_in() ? esc_html_e( 'You cannot create new topics at this time.', 'bbpress' ) : esc_html_e( 'You must be logged in to create new topics.', 'bbpress' ); ?></p>
		</div>
	</div>

<?php endif; ?>

<?php if ( !bbp_is_single_forum() ) : ?>

</div>

<?php endif; ?>
