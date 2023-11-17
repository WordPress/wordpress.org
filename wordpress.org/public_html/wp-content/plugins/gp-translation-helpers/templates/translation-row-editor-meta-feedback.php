<?php if ( ! $can_approve_translation || ! $translation->translation_status ) {
	return;
}

if ( 'waiting' === $translation->translation_status || 'fuzzy' === $translation->translation_status ) :
	?>
<details class="details-chatgpt" open>
	<summary class="chatgpt-summary"><?php esc_html_e( 'ChatGPT Review', 'glotpress' ); ?></summary>
	<div>
		<div class="openai-review">
			<p class="suggestions__loading-indicator">ChatGPT review in progress <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span></p>
			<div class="auto-review-result"></div>
		</div>
	</div>
</details>
<?php endif; ?>
<details class="details-feedback" open>
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
				<label for="<?php echo 'feedback-' . esc_attr( $translation->row_id ); ?>"><?php esc_html_e( 'Comment (Optional)', 'glotpress' ); ?>
				</label>
				<textarea id="<?php echo 'feedback-' . esc_attr( $translation->row_id ); ?>" name="feedback_comment"></textarea>

				<span class="note">Please note that all feedback is visible to the public.</span>
			</div>
		</form>
	</div>
</details>
