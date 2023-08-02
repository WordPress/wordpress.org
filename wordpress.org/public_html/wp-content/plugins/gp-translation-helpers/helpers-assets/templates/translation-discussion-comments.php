<?php
/**
 * The template part for the comments and comment form on an original discussion
 */
?>
<div class="discussion-wrapper">
	<?php if ( $comments ) : ?>

		<h6>
			<?php
			/* translators: number of comments. */
			printf( _n( '%s Comment', '%s Comments', count( $comments ) ), number_format_i18n( count( $comments ) ) );
			?>
		<?php if ( $locale_slug ) : ?>
			(<?php echo esc_html( $locale_slug ); ?>)
			<?php
			$locale_comments_count    = 0;
			$count_rejection_feedback = 0;
			foreach ( $comments as $_comment ) {
				$comment_locale = get_comment_meta( $_comment->comment_ID, 'locale', true );
				if ( $locale_slug == $comment_locale ) {

					$reject_reason = get_comment_meta( $_comment->comment_ID, 'reject_reason', true );
					if ( ! empty( $reject_reason ) ) {
						$count_rejection_feedback++;
					}
					$locale_comments_count++;
				}
			}
			?>

			<span class="comments-selector">
				<a href="#" class="active-link" data-selector="all">Show all (<?php echo esc_html( count( $comments ) ); ?>)</a> |
				<a href="#" data-selector="<?php echo esc_attr( $locale_slug ); ?>"><?php echo esc_html( $locale_slug ); ?> only (<?php echo esc_html( $locale_comments_count ); ?>)</a> |
				<a href="#" data-selector="rejection-feedback">Rejection Feedback (<?php echo esc_html( $count_rejection_feedback ); ?>)</a>
			</span>
		<?php endif; ?>
		</h6>
	<?php else : ?>
		<?php esc_html_e( 'No comments have been made on this yet.' ); ?>
	<?php endif; ?>
	<ul class="discussion-list">
		<?php
		wp_list_comments(
			array(
				'style'                => 'ul',
				'type'                 => 'comment',
				'callback'             => 'gth_discussion_callback',
				'translation_id'       => $translation_id,
				'locale_slug'          => $locale_slug,
				'original_permalink'   => $original_permalink,
				'original_id'          => $original_id,
				'project'              => $project,
				'translation_set_slug' => $translation_set_slug,
				'reverse_children'     => false,
			),
			$comments
		);
		?>
	</ul><!-- .discussion-list -->
	<?php
	// $option_typo     = '<option value="typo">Typo in the English text (admins will be notified)</option>';
	// $option_context  = '<option value="context">Where does this string appear? (more context) (admins will be notified)</option>';
	$optgroup_question = '';
	if ( $locale_slug ) {
		$gp_locale = GP_Locales::by_slug( $locale_slug );
		if ( $gp_locale ) {
			$optgroup_question = '
					<optgroup label="Notify validators">
					    <option value="question">Question about translating to ' . esc_html( $gp_locale->english_name ) . '</option>
					</optgroup>';
		}
	}
	$options = '<select required="" name="comment_topic" id="comment_topic">
					<option value="">Select a topic</option>
					<optgroup label="Notify admins">
						<option value="typo">Typo in the English text</option>
						<option value="context">Where does this string appear? (more context)</option>
					</optgroup>' .
			   $optgroup_question .
			   '</select>';

	add_action(
		'comment_form_logged_in_after',
		function () use ( $locale_slug, $options ) {
			/**
			 * Filters the options.
			 *
			 * @param string $options     The options for the select element.
			 * @param string $locale_slug The slug of the current locale.
			 *
			 *@since 0.0.2
			 */
			$options = apply_filters( 'gp_discussion_new_comment_options', $options, $locale_slug );

			echo '<p class="comment-form-topic">
					<label for="comment_topic">Topic <span class="required" aria-hidden="true">*</span> (required)</label> ' .
						wp_kses(
							$options,
							array(
								'select'   =>
										   array(
											   'required' => true,
											   'name'     => true,
											   'id'       => true,
										   ),
								'optgroup' =>
										array(
											'label' => true,
										),
								'option'   =>
										array(
											'value' => true,
										),
							)
						) .
				'</p>';
		},
		10,
		2
	);

	if ( is_user_logged_in() && isset( $post ) ) {
		if ( $post instanceof Gth_Temporary_Post ) {
			$_post_id = $post->ID;
			$post_obj = $post;
		} else {
			$post_obj = $post->ID;
			$_post_id = $post->ID;
		}
		?>

		<?php if ( $comments ) : ?>
		<details class="hide-textarea">
			<summary>Start a new conversation</summary>
		<?php endif; ?>

		<?php
		comment_form(
			array(
				'title_reply'         => __( 'Discuss this string' ),
				/* translators: username */
				'title_reply_to'      => __( 'Reply to %s' ),
				'title_reply_before'  => '<h5 id="reply-title" class="discuss-title">',
				'title_reply_after'   => '</h5>',
				'id_form'             => 'commentform-' . $_post_id,
				'cancel_reply_link'   => '<span></span>',
				'format'              => 'html5',
				'class_submit'        => 'button is-primary',
				'comment_notes_after' => implode(
					"\n",
					array(
						'<input type="hidden" name="comment_locale" value="' . esc_attr( $locale_slug ) . '" />',
						'<input type="hidden" name="translation_id" value="' . esc_attr( $translation_id ) . '" />',
						'<input type="hidden" name="redirect_to" value="' . esc_url( $original_permalink ) . '" />',
					)
				),
			),
			$post_obj
		);
		echo '<div class="optin-message-for-each-discussion">';
		echo wp_kses(
			GP_Notifications::optin_message_for_each_discussion( $original_id ),
			array(
				'a' => array(
					'href'             => array(),
					'class'            => array(),
					'data-original-id' => array(),
					'data-opt-type'    => array(),
				),
			)
		);
		echo '</div>';
		?>

		<?php if ( $comments ) : ?>
			</details>
		<?php endif; ?>

			<?php
	} else {
		/* translators: Log in URL. */
		echo sprintf( __( 'You have to be <a href="%s">logged in</a> to comment.' ), esc_html( wp_login_url() ) );
	}
	?>
</div><!-- .discussion-wrapper -->
<script>
	jQuery(function( e, mentions ) {
		var mentionsList = '<?php echo wp_json_encode( $mentions_list ); ?>';
		var jetpackMentionsData = JSON.parse( mentionsList );
		if( jetpackMentionsData.length > 0 && typeof jQuery.fn.mentions !== 'undefined' ) {
			jQuery( 'textarea#comment' ).mentions( jetpackMentionsData );
		}
	});
</script>
