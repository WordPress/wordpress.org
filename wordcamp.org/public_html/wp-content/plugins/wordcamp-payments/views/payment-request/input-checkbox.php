<tr id="row-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
	<th>
		<label for="<?php echo esc_attr( $name ); ?>">
			<?php echo esc_html( $label ); ?>:
		</label>
	</th>

	<td>
		<input
			type="checkbox"
			id="<?php echo esc_attr( $name ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $name ); ?>"
			<?php checked( $value, $name ); ?>
			/>

		<?php if ( ! empty( $description ) ) : ?>
			<label for="<?php echo esc_attr( $name ); ?>">
				<span class="description"><?php echo esc_html( $description ); ?></span>
			</label>
		<?php endif; ?>
	</td>
</tr>
