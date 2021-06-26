<?php
/**
 * The template file for displaying the comments and comment form
 *
 * @package WPBBP
 */

if ( post_password_required() ) {
	return;
}

$post_id = get_the_ID();

if ( comments_open() ) :
	if ( filter_input( INPUT_GET, 'feedback_submitted' ) ) : ?>
		<h2 id="reply-title" class="comment-reply-title">
			<?php esc_html_e( 'Thank you for your feedback', 'wporg-forums' ); ?>
		</h2>
		<p>
			<?php esc_html_e( 'We will review it as quickly as possible.', 'wporg-forums' ); ?>
		</p>
	<?php else :
	comment_form(
		array(
			'title_reply_before'  => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply'         => __( 'Was this article helpful? How could it be improved?', 'wporg-forums' ),
			'title_reply_after'   => '</h2>',
			'must_log_in'         => sprintf(
				'<p class="must-log-in">%s</p>',
				sprintf(
					wp_kses_post( __( 'You must be <a href="%s">logged in</a> to submit feedback.', 'wporg-forums' ) ),
					wp_login_url( apply_filters( 'the_permalink', get_permalink( $post_id ), $post_id ) )
				)
			),
			'comment_field'       => sprintf(
				'<p class="comment-form-comment">%s %s</p>',
				sprintf(
					'<label for="comment" class="screen-reader-text">%s</label>',
					_x( 'Feedback', 'noun', 'wporg-forums' )
				),
				'<textarea id="comment" name="comment" rows="6" maxlength="65525" required></textarea>'
			),
			'comment_notes_after' => esc_html__( 'Feedback you send to us will go only to the folks who maintain documentation. They may reach out in case there are questions or would like to followup feedback. But that too will stay behind the scenes.', 'wporg-forums' ),
			'label_submit'        => __( 'Submit Feedback', 'wporg-forums' ),
		)
	);
	endif;
endif;
