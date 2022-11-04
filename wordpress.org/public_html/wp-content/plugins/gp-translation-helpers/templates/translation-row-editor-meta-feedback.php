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
				$comment_reasons = Helper_Translation_Discussion::get_comment_reasons();
			foreach ( $comment_reasons as $key => $reason ) :
				?>
					<li>
						<label class="tooltip" title="<?php echo esc_attr( $reason['explanation'] ); ?>"><input type="checkbox" name="feedback_reason" value="<?php echo esc_attr( $key ); ?>" /><?php echo esc_html( $reason['name'] ); ?></label><span class="tooltip dashicons dashicons-info" title="<?php echo esc_attr( $reason['explanation'] ); ?>"></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="feedback-comment">
				<label><?php esc_html_e( 'Comment (Optional)', 'glotpress' ); ?>
					<textarea name="feedback_comment"></textarea>
				</label>
				<label class="note">Please note that all feedback is visible to the public.</label>
			</div>
		</form>
	</div>
</details>
