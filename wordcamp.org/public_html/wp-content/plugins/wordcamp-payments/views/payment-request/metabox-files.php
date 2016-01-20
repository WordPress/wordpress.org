<table class="form-table">
	<?php $this->render_files_input( $post, 'Files', 'files', __( 'Attach supporting documentation including invoices, contracts, or other vendor correspondence. If no supporting documentation is available, please indicate the reason in the notes below.', 'wordcamporg' ) ); ?>
	<?php $this->render_textarea_input( $post, 'Notes', 'file_notes' ); ?>
</table>
