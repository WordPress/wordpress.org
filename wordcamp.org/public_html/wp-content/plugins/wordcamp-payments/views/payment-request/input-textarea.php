<tr id="row-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
	<th>
		<label for="<?php echo esc_attr( $name ); ?>">
			<?php echo esc_html( $label ); ?>:
		</label>
	</th>

	<td>
		<textarea
			id="<?php echo esc_attr( $name ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			class="large-text"
		    <?php __checked_selected_helper( $required, true, true, 'required' ); ?>
		><?php
			echo esc_html( $text );
		?></textarea>

		<?php
			if ( $required ) {
				WordCamp_Budgets::render_form_field_required_indicator();
			}
		?>

		<?php if ( ! empty( $description ) ) : ?>
			<label for="<?php echo esc_attr( $name ); ?>">
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</label>
		<?php endif; ?>
	</td>
</tr>
