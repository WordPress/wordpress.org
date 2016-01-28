<table class="form-table">
	<?php
		$this->render_textarea_input( $post, 'Description', 'description' );
		$this->render_text_input( $post, 'Invoice date', 'invoice_date', '', 'date' );
		$this->render_text_input( $post, 'Requested date for payment/due by', 'due_by', '', 'date' );
		$this->render_text_input( $post, 'Amount', 'payment_amount', 'No commas, thousands separators or currency symbols. Ex. 1234.56' );
		$this->render_select_input( $post, 'Currency', 'currency' );
	?>

	<tr>
		<th><label for="payment_category">Category</label></th>
		<td>
			<select name="payment_category" id="payment_category" class="postform">
				<option value="null">-- Select a Category --</option>

				<?php foreach( $categories as $key => $name ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $assigned_category, $key ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>

	<?php
		$this->render_text_input(
			$post,
			'Other Category',
			'other_category_explanation',
			__( 'Please describe what category this request fits under.', 'wordcamporg' ),
			'text',
			isset( $assigned_category->name ) && 'Other' == $assigned_category->name ? array() : array( 'hidden')    // todo i18n, see notes in insert_default_terms()
		);
	?>

	<?php $this->render_textarea_input( $post, 'Notes', 'general_notes', 'Any other details you want to share.' ); ?>
</table>
