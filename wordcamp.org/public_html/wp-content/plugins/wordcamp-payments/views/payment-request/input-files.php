<tr id="row-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
	<th><?php echo esc_html( $label ); ?>:</th>

	<td>
		<?php if ( ! empty( $description ) ) : ?>
			<p class="description">
				<?php echo esc_html( $description ); ?>
			</p>
		<?php endif; ?>

		<?php // todo move to centralized view file ?>
		<p>
			<a class="button wcp-insert-media" role="button">
				<?php _e( 'Add files', 'wordcamporg' ); ?>
			</a>
		</p>

		<h4><?php _e( 'Attached files:', 'wordcamporg' ); ?></h4>

		<ul class="wcp_files_list"></ul>

		<p class="wcp_no_files_uploaded <?php echo $files ? 'hidden' : 'active'; ?>">
			<?php _e( "You haven't uploaded any files yet.", 'wordcamporg' ); ?>
		</p>

		<script type="text/html" id="tmpl-wcp-attached-file">
			<a href="{{ data.url }}">{{ data.filename }}</a>
		</script>

		<?php wp_localize_script( 'wcb-attached-files', 'wcbAttachedFiles', $files ); // todo merge into wordcampBudgets var ?>
	</td>
</tr>
