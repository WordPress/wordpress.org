<?php

defined( 'WPINC' ) || die();

/** @var array $order */
/** @var array $metas */
/** @var string $invoice_vat_number */
/** @var int $txn_id */

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
			<td><?php echo esc_html( $item['name'] ); ?></td><!-- name -->
			<td><?php echo esc_html( number_format_i18n( $item['price'], 2 ) ); ?></td><!-- price -->
			<td><?php echo esc_html( number_format_i18n( $item['quantity'] ) ); ?></td><!-- qty -->
		</tr>

	<?php endforeach; ?>

	</tbody>
</table>

<table class="form-table">
	<tr>
		<th scope="row"><?php echo esc_html__( 'Total amount', 'wordcamporg' ); ?></th>
		<td><?php echo esc_html( number_format_i18n( $order['total'], 2 ) ); ?></td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Customer', 'wordcamporg' ); ?></th>
		<td><?php echo esc_html( $metas['name'] ); ?><td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Contact email', 'wordcamporg' ); ?></th>
		<td><?php echo esc_html( $metas['email'] ); ?><td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Customer Address', 'wordcamporg' ); ?></th>
		<td><?php echo wp_kses( nl2br( $metas['address'] ), array( 'br' => true ) ); ?><td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Transaction ID', 'wordcamporg' ); ?></th>
		<td><?php echo esc_html( $txn_id ); ?><td>
	</tr>

	<?php if ( ! empty( $invoice_vat_number ) ) : ?>
		<tr>
			<th scope="row"><?php echo esc_html__( 'VAT number', 'wordcamporg' ); ?></th>
			<td><?php echo esc_html( $metas['vat-number'] ); ?><td>
		</tr>
	<?php endif; ?>
</table>
