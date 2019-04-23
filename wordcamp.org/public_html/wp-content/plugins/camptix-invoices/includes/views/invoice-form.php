<?php

defined( 'WPINC' ) || die();

/** @var string $invoice_vat_number */

?>

<div class="camptix-invoice-toggle-wrapper">

	<input type="checkbox" value="1" name="camptix-need-invoice" id="camptix-need-invoice"/>
	<label for="camptix-need-invoice">
		<?php echo esc_html__( 'I need an invoice', 'wordcamporg' ); ?>
	</label>

	<table class="camptix-invoice-details tix_tickets_table tix_invoice_table">
		<tbody>

			<tr>
				<td class="tix-left">
					<label for="invoice-email">
						<?php echo esc_html__( 'Email for the invoice to be sent to', 'wordcamporg' ); ?><span class="tix-required-star">*</span>
					</label>
				</td>
				<td class="tix-right">
					<input type="text" name="invoice-email" id="invoice-email" pattern="^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$" />
				</td>
			</tr>

			<tr>
				<td class="tix-left">
					<label for="invoice-name">
						<?php echo esc_html__( 'Name or organisation that the invoice should be made out to', 'wordcamporg' ); ?><span class="tix-required-star">*</span>
					</label>
				</td>
				<td class="tix-right">
					<input type="text" name="invoice-name" id="invoice-name" />
				</td>
			</tr>

			<tr>
				<td class="tix-left">
					<label for="invoice-address">
						<?php echo esc_html__( 'Street address', 'wordcamporg' ); ?><span class="tix-required-star">*</span>
					</label>
				</td>
				<td class="tix-right">
					<textarea name="invoice-address" id="invoice-address" rows="2"></textarea>
				</td>
			</tr>

			<?php if ( ! empty( $invoice_vat_number ) ) : ?>
				<tr>
					<td class="tix-left">
						<label for="invoice-vat-number">
							<?php echo esc_html__( 'VAT number', 'wordcamporg' ); ?><span class="tix-required-star">*</span>
						</label>
					</td>
					<td class="tix-right">
						<input type="text" name="invoice-vat-number" id="invoice-vat-number" />
					</td>
				</tr>
			<?php endif; ?>

		</tbody>
	</table>
</div>
