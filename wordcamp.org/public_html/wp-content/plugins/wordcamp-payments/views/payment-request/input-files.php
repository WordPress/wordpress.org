<tr id="row-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
	<th><?php echo esc_html( $label ); ?>:</th>

	<td>
		<?php if ( ! empty( $description ) ) : ?>
			<p class="description">
				<?php echo esc_html( $description ); ?>
			</p>
		<?php endif; ?>

		<?php require_once( dirname( __DIR__ ) . '/wordcamp-budgets/field-attached-files.php' ); ?>
	</td>
</tr>
