<tr id="row-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
	<th>
		<label for="<?php echo esc_attr( $name ); ?>">
			<?php echo esc_html( $label ); ?>:
		</label>
	</th>
	<td>
		<select id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<option value="">(None)</option>
			<?php foreach ( $options as $value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $selected ); ?>><?php
					echo esc_html( $option_label['name'] );
				?></option>
			<?php endforeach; ?>
		</select>
	</td>
</tr>
