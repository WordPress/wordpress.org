<tr id="row-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
	<th>
		<label for="<?php echo esc_attr( $name ); ?>">
			<?php echo esc_html( $label ); ?>:
		</label>
	</th>

	<td>
		<select
			id="<?php echo esc_attr( $name ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
		    <?php __checked_selected_helper( $required, true, true, 'required' ); ?>
		>
			<option value="">
				<?php // translators: %s is a label in a dropdown. For example, "Select a currency", or "Select a category". ?>
				<?php printf( esc_html__( '-- Select a %s --', 'wordcamporg' ), $label ); ?>
			</option>
			<option value=""></option>

			<?php foreach ( $options as $value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $selected ); ?>>
					<?php echo esc_html( $option_label ); ?>
					<?php if ( 'currency' === $name && $value ) : ?>
						(<?php echo esc_html( $value ); ?>)
					<?php endif; ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php
			if ( $required ) {
				WordCamp_Budgets::render_form_field_required_indicator();
			}
		?>
	</td>
</tr>
