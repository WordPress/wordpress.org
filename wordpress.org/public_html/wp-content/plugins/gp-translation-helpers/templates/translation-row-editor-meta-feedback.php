<?php if ( ! $can_approve_translation || ! $translation->translation_status ) {
	return;
}  ?>
<details open>
	<summary class="feedback-summary"><?php esc_html_e( 'Give feedback', 'glotpress' ); ?></summary>
	<div id="feedback-form">
		<form>
			<h3 class="feedback-reason-title"><?php esc_html_e( 'Type (Optional)', 'glotpress' ); ?></h3>
			<ul class="feedback-reason-list">
			<?php
				$comment_reasons = Helper_Translation_Discussion::get_comment_reasons( $locale_slug );
			foreach ( $comment_reasons as $key => $reason ) :
				?>
					<li>
						<label><input type="checkbox" name="feedback_reason" value="<?php echo esc_attr( $key ); ?>" /><span class="gp-reason-text"><?php echo esc_html( $reason['name'] ); ?></span><span class="tooltip dashicons dashicons-info" title="<?php echo esc_attr( $reason['explanation'] ); ?>"></span></label>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="feedback-comment">
				<label for="feedback_comment"><?php esc_html_e( 'Comment (Optional)', 'glotpress' ); ?>
				</label>
				<textarea name="feedback_comment"></textarea>

				<label class="note">Please note that all feedback is visible to the public.</label>
			</div>
		</form>
	</div>
</details>
