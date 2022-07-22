<?php if ( ! $can_approve_translation || ! $translation->translation_status ) {
	return;
}  ?>
<details>
	<summary class="feedback-summary">Give feedback</summary>
	<div id="feedback-form">
		<form>
			<h3 class="feedback-reason-title">Reason</h3>
			<ul class="feedback-reason-list">
			<?php
				$reject_reasons = Helper_Translation_Discussion::get_reject_reasons();
			foreach ( $reject_reasons as $key => $reason ) :
				?>
					<li>
						<label class="tooltip" title="<?php echo esc_attr( $reason['explanation'], 'glotpress' ); ?>"><input type="checkbox" name="feedback_reason" value="<?php echo esc_attr( $key, 'glotpress' ); ?>" /><?php echo esc_html( $reason['name'], 'glotpress' ); ?></label><span class="tooltip dashicons dashicons-info" title="<?php echo esc_attr( $reason['explanation'], 'glotpress' ); ?>"></span>
					</li>
			<?php endforeach; ?>
			</ul>
			<div class="feedback-comment">
				<label>Comment </label>
				<textarea name="feedback_comment"></textarea>
			</div>
		</form>
	</div>
</details>
