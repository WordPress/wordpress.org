<table class="form-table">
	<?php
		$this->render_textarea_input( $post, esc_html__( 'Description', 'wordcamporg' ), 'description' );
		$this->render_text_input( $post, esc_html__( 'Invoice Number', 'wordcamporg' ), 'invoice_number' );
		$this->render_text_input( $post, esc_html__( 'Invoice date', 'wordcamporg' ), 'invoice_date', '', 'date' );
		$this->render_text_input( $post, esc_html__( 'Requested date for payment/due by', 'wordcamporg' ), 'due_by', '', 'date' );
		$this->render_text_input( $post, esc_html__( 'Amount', 'wordcamporg' ), 'payment_amount', esc_html__( 'No commas, thousands separators or currency symbols. Ex. 1234.56', 'wordcamporg' ) );
		$this->render_select_input( $post, esc_html__( 'Currency', 'wordcamporg' ), 'currency' );
		$this->render_select_input( $post, esc_html__( 'Category', 'wordcamporg' ), 'payment_category' );
	?>

	<?php
		$this->render_text_input(
			$post,
			esc_html__( 'Other Category', 'wordcamporg' ),
			'other_category_explanation',
			esc_html__( 'Please describe what category this request fits under.', 'wordcamporg' ),
			'text',
			isset( $assigned_category->name ) && 'Other' == $assigned_category->name ? array() : array( 'hidden')    // todo i18n, see notes in insert_default_terms()
		);
	?>

	<?php $this->render_files_input(
		$post,
		esc_html__( 'Files', 'wordcamporg' ),
		'files',
		esc_html__( 'Attach supporting documentation including invoices, contracts, or other vendor correspondence. If no supporting documentation is available, please indicate the reason in the notes below.', 'wordcamporg' )
	); ?>

	<?php $this->render_textarea_input(
		$post,
		esc_html__( 'Notes', 'wordcamporg' ),
		'general_notes',
		esc_html__( 'Any other details you want to share.', 'wordcamporg' ),
		false
	); ?>
</table>

<p class="wcb-form-required">
	<?php esc_html_e( '* required', 'wordcamporg' ); ?>
</p>
