<?php

/**
 * New/Edit Reply
 *
 * @package bbPress
 * @subpackage Theme
 */

if ( bbp_is_reply_edit() ) : ?>

<div id="bbpress-forums">

	<?php bbp_breadcrumb(); ?>

<?php endif; ?>

<?php if ( bbp_current_user_can_access_create_reply_form() ) : ?>

	<div id="new-reply-<?php bbp_topic_id(); ?>" class="bbp-reply-form">

		<form id="new-post" name="new-post" method="post" action="<?php the_permalink(); ?>">

			<?php do_action( 'bbp_theme_before_reply_form' ); ?>

			<fieldset class="bbp-form">
				<legend><?php printf( esc_html__( 'Reply To: %s', 'wporg-forums' ), ( bbp_get_form_reply_to() ) ? sprintf( esc_html__( 'Reply #%1$s in %2$s', 'wporg-forums' ), bbp_get_form_reply_to(), bbp_get_topic_title() ) : bbp_get_topic_title() ); ?></legend>

				<?php do_action( 'bbp_theme_before_reply_form_notices' ); ?>

				<?php if ( ! bbp_is_topic_open() && ! bbp_is_reply_edit() ) : ?>

					<div class="bbp-template-notice">
						<ul>
							<li><?php esc_html_e( 'This topic is marked as closed to new replies, however your posting capabilities still allow you to do so.', 'wporg-forums' ); ?></li>
						</ul>
					</div>

				<?php endif; ?>

				<?php if ( current_user_can( 'unfiltered_html' ) ) : ?>

					<div class="bbp-template-notice">
						<ul>
							<li><?php esc_html_e( 'Your account has the ability to post unrestricted HTML content.', 'wporg-forums' ); ?></li>
						</ul>
					</div>

				<?php endif; ?>

				<?php do_action( 'bbp_template_notices' ); ?>

				<div>

					<?php bbp_get_template_part( 'form', 'anonymous' ); ?>

					<?php do_action( 'bbp_theme_before_reply_form_content' ); ?>

					<?php bbp_the_content( array( 'context' => 'reply' ) ); ?>

					<?php do_action( 'bbp_theme_after_reply_form_content' ); ?>

					<?php if ( ! ( bbp_use_wp_editor() || current_user_can( 'unfiltered_html' ) ) ) : ?>

						<p class="form-allowed-tags">
							<label><?php esc_html_e( 'You may use these <abbr title="HyperText Markup Language">HTML</abbr> tags and attributes:','wporg-forums' ); ?></label><br />
							<code><?php bbp_allowed_tags(); ?></code>
						</p>

					<?php endif; ?>

					<?php if ( bbp_allow_topic_tags() && current_user_can( 'assign_topic_tags' ) ) : ?>

						<?php do_action( 'bbp_theme_before_reply_form_tags' ); ?>

						<p>
							<label for="bbp_topic_tags"><?php esc_html_e( 'Tags:', 'wporg-forums' ); ?></label><br />
							<input type="text" value="<?php bbp_form_topic_tags(); ?>" size="40" name="bbp_topic_tags" id="bbp_topic_tags" <?php disabled( bbp_is_topic_spam() ); ?> />
						</p>

						<?php do_action( 'bbp_theme_after_reply_form_tags' ); ?>

					<?php endif; ?>

					<?php if ( bbp_is_subscriptions_active() && ! bbp_is_anonymous() && ( ! bbp_is_reply_edit() || ( bbp_is_reply_edit() && ! bbp_is_reply_anonymous() ) ) ) : ?>

						<?php do_action( 'bbp_theme_before_reply_form_subscription' ); ?>

						<p>

							<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="checkbox" value="bbp_subscribe"<?php bbp_form_topic_subscribed(); ?> />

							<?php if ( bbp_is_reply_edit() && ( bbp_get_reply_author_id() !== bbp_get_current_user_id() ) ) : ?>

								<label for="bbp_topic_subscription"><?php esc_html_e( 'Notify the author of follow-up replies via email', 'wporg-forums' ); ?></label>

							<?php else : ?>

								<label for="bbp_topic_subscription"><?php esc_html_e( 'Notify me of follow-up replies via email', 'wporg-forums' ); ?></label>

							<?php endif; ?>

						</p>

						<?php do_action( 'bbp_theme_after_reply_form_subscription' ); ?>

					<?php endif; ?>

					<?php if ( bbp_is_reply_edit() ) : ?>

						<?php if ( current_user_can( 'moderate', bbp_get_reply_id() ) ) : ?>

							<?php do_action( 'bbp_theme_before_reply_form_reply_to' ); ?>

							<p class="form-reply-to">
								<label for="bbp_reply_to"><?php esc_html_e( 'Reply To:', 'wporg-forums' ); ?></label><br />
								<?php bbp_reply_to_dropdown(); ?>
							</p>

							<?php do_action( 'bbp_theme_after_reply_form_reply_to' ); ?>

							<?php do_action( 'bbp_theme_before_reply_form_status' ); ?>

							<p>
								<label for="bbp_reply_status"><?php esc_html_e( 'Reply Status:', 'wporg-forums' ); ?></label><br />
								<?php bbp_form_reply_status_dropdown(); ?>
							</p>

							<?php do_action( 'bbp_theme_after_reply_form_status' ); ?>

						<?php endif; ?>

						<?php if ( bbp_allow_revisions() ) : ?>

							<?php do_action( 'bbp_theme_before_reply_form_revisions' ); ?>

							<fieldset class="bbp-form">
								<legend>
									<input name="bbp_log_reply_edit" id="bbp_log_reply_edit" type="checkbox" value="1" <?php bbp_form_reply_log_edit(); ?> />
									<label for="bbp_log_reply_edit"><?php esc_html_e( 'Keep a log of this edit:', 'wporg-forums' ); ?></label><br />
								</legend>

								<div>
									<label for="bbp_reply_edit_reason"><?php printf( esc_html__( 'Optional reason for editing:', 'wporg-forums' ), bbp_get_current_user_name() ); ?></label><br />
									<input type="text" value="<?php bbp_form_reply_edit_reason(); ?>" size="40" name="bbp_reply_edit_reason" id="bbp_reply_edit_reason" />
								</div>
							</fieldset>

							<?php do_action( 'bbp_theme_after_reply_form_revisions' ); ?>

						<?php endif; ?>

					<?php endif; ?>

					<?php do_action( 'bbp_theme_before_reply_form_submit_wrapper' ); ?>

					<div class="bbp-submit-wrapper">

						<?php do_action( 'bbp_theme_before_reply_form_submit_button' ); ?>

						<?php bbp_cancel_reply_to_link(); ?>

						<button type="submit" id="bbp_reply_submit" name="bbp_reply_submit" class="button submit"><?php esc_html_e( 'Submit', 'wporg-forums' ); ?></button>

						<?php do_action( 'bbp_theme_after_reply_form_submit_button' ); ?>

					</div>

					<?php do_action( 'bbp_theme_after_reply_form_submit_wrapper' ); ?>

				</div>

				<?php bbp_reply_form_fields(); ?>

			</fieldset>

			<?php do_action( 'bbp_theme_after_reply_form' ); ?>

		</form>
	</div>

<?php elseif ( bbp_is_topic_closed() ) : ?>

	<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
		<div class="bbp-template-notice">
			<ul>
				<li><?php printf( esc_html__( 'The topic &#8216;%s&#8217; is closed to new replies.', 'wporg-forums' ), bbp_get_topic_title() ); ?></li>
			</ul>
		</div>
	</div>

<?php elseif ( bbp_is_forum_closed( bbp_get_topic_forum_id() ) ) : ?>

	<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
		<div class="bbp-template-notice">
			<ul>
				<li><?php printf( esc_html__( 'The forum &#8216;%s&#8217; is closed to new topics and replies.', 'wporg-forums' ), bbp_get_forum_title( bbp_get_topic_forum_id() ) ); ?></li>
			</ul>
		</div>
	</div>

<?php else : ?>

	<div id="no-reply-<?php bbp_topic_id(); ?>" class="bbp-no-reply">
		<div class="bbp-template-notice">
			<ul>
				<li><?php is_user_logged_in()
					? esc_html_e( 'You cannot reply to this topic.',               'wporg-forums' )
					: esc_html_e( 'You must be logged in to reply to this topic.', 'wporg-forums' );
				?></li>
			</ul>
		</div>
	</div>

<?php endif; ?>

<?php if ( bbp_is_reply_edit() ) : ?>

</div>

<?php endif;
