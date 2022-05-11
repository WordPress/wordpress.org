<ul class="discussion-list">
	<?php
		wp_list_comments(
			array(
				'style'              => 'ul',
				'type'               => 'comment',
				'callback'           => 'gth_discussion_callback',
				'translation_id'     => $translation_id,
				'original_permalink' => $original_permalink,
				'locale_slug'        => $locale_slug,
			),
			$comments
		);
		?>
</ul><!-- .discussion-list -->
<?php
	comment_form(
		$args = array(
			'title_reply'         => __( 'Discuss this string' ),
			/* translators: username */
			'title_reply_to'      => __( 'Reply to %s' ),
			'title_reply_before'  => '<h6 id="reply-title" class="discuss-title">',
			'title_reply_after'   => '</h6>',
			'id_form'             => 'commentform-' . $post_id,
			'comment_notes_after' => implode(
				"\n",
				array(
					'<input type="hidden" name="comment_locale" value="' . esc_attr( $locale_slug ) . '" />',
					'<input type="hidden" name="translation_id" value="' . esc_attr( $translation_id ) . '" />',
					'<input type="hidden" name="redirect_to" value="' . esc_url( home_url( $_SERVER['REQUEST_URI'] ) ) . '" />',

				)
			),
		),
		$post_id
	);
	?>
