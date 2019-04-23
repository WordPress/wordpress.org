<?php

defined( 'WPINC' ) || die();

/** @var array $order */
/** @var array $metas */
/** @var string $invoice_vat_number */

?>

<h3><?php echo esc_html__( 'Order details', 'wordcamporg' ); ?></h3>

<table class="widefat">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Title', 'wordcamporg' ); ?></th>
			<th><?php echo esc_html__( 'Unit price', 'wordcamporg' ); ?></th>
			<th><?php echo esc_html__( 'Quantity', 'wordcamporg' ); ?></th>
		</tr>
	</thead>

	<tbody>

	<?php foreach ( $order['items'] as $k => $item ) : ?>

		<tr>
			<td>
				<input type="text" value="<?php echo esc_html( $item['name'] ); ?>" name="order[items][<?php echo esc_attr( $k ); ?>][name]" class="widefat">
			</td><!-- name -->
			<td>
				<input type="number" min="0" step="0.01" value="<?php echo esc_attr( number_format_i18n( $item['price'], 2 ) ); ?>" name="order[items][<?php echo esc_attr( $k ); ?>][price]" class="widefat">
			</td><!-- price -->
			<td>
				<input type="number" min="0" value="<?php echo esc_attr( number_format_i18n( $item['quantity'] ) ); ?>" name="order[items][<?php echo esc_attr( $k ); ?>][quantity]" class="widefat">
			</td><!-- qty -->
		</tr>

	<?php endforeach; ?>

		<tr>
			<td><input type="text" value="" name="order[items][<?php echo esc_attr( count( $order['items'] ) + 1 ); ?>][name]" class="widefat"></td><!-- name -->
			<td><input type="number" min="0" step="0.01" value="<?php echo esc_attr( number_format_i18n( 0, 2 ) ); ?>" name="order[items][<?php echo esc_attr( count( $order['items'] ) + 1 ); ?>][price]" class="widefat"></td><!-- price -->
			<td><input type="number" min="0" value="" name="order[items][<?php echo esc_attr( count( $order['items'] ) + 1 ); ?>][quantity]" class="widefat"></td><!-- qty -->
		</tr>

	</tbody>
</table>

<table class="form-table">
	<tr>
		<th scope="row">
			<label for="order[total]"><?php echo esc_html__( 'Total amount', 'wordcamporg' ); ?></label>
		</th>
		<td>
			<input
				type="number"
				min="0"
				step="0.01"
				value="<?php echo esc_attr( number_format_i18n( empty( $order['total'] ) ? '0' : $order['total'], 2 ) ); ?>"
				name="order[total]"
				id="order[total]"
			/>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="invoice_metas[name]"><?php echo esc_html__( 'Customer', 'wordcamporg' ); ?> •</label>
		</th>
		<td>
			<input
				required
				name="invoice_metas[name]"
				id="invoice_metas[name]"
				value="<?php echo esc_attr( empty( $metas['name'] ) ? '' : $metas['name'] ); ?>"
				type="text"
				class="widefat"
			/>
		<td>
	</tr>
	<tr>
		<th scope="row">
			<label for="invoice_metas[email]"><?php echo esc_html__( 'Contact email', 'wordcamporg' ); ?></label>
		</th>
		<td>
			<input
				name="invoice_metas[email]"
				id="invoice_metas[email]"
				value="<?php echo esc_attr( empty( $metas['email'] ) ? '' : $metas['email'] ); ?>"
				type="email"
				class="widefat"
			/>
		<td>
	</tr>
	<tr>
		<th scope="row">
			<label for="invoice_metas[address]"><?php echo esc_html__( 'Customer Address', 'wordcamporg' ); ?> •</label>
		</th>
		<td>
			<textarea
				required
				name="invoice_metas[address]"
				id="invoice_metas[address]"
				class="widefat"
			><?php
				echo esc_textarea( empty( $metas['address'] ) ? '' : $metas['address'] );
			?></textarea>
		<td>
	</tr>

	<?php if ( ! empty( $invoice_vat_number ) ) : ?>
		<tr>
			<th scope="row">
				<label for="invoice_metas[vat-number]"><?php echo esc_html__( 'VAT number', 'wordcamporg' ); ?></label>
			</th>
			<td>
				<input
					name="invoice_metas[vat-number]"
					id="invoice_metas[vat-number]"
					value="<?php echo esc_textarea( empty( $metas['vat-number'] ) ? '' : $metas['vat-number'] ); ?>"
					type="text"
					class="widefat"
				/>
			<td>
		</tr>
	<?php endif; ?>
</table>
