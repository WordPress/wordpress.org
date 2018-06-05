<?php
namespace WordCamp\Budgets\Sponsor_Payment_Stripe;

/** @var array $data */

get_header();
?>

<div id="container">
	<div id="content" class="wcorg-sponsor-payment" role="main">

		<h1 class="entry-title"><?php esc_html_e( 'Sponsorship Payment', 'wordcamporg' ); ?></h1>

		<?php if ( ! empty( $data['errors'] ) ) : ?>
			<?php foreach ( $data['errors'] as $error ) : ?>
				<p class="notice notice-error">
					<strong><?php esc_html_e( 'Error:', 'wordcamporg' ); ?></strong>
					<?php echo wp_kses_data( $error ); ?>
				</p>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( $data['step'] == STEP_SELECT_INVOICE ) : ?>
			<p class="payment-instructions">
				<?php esc_html_e( 'Use this form to pay your WordCamp sponsorship fee to WordPress Community Support, PBC. If you did not receive an invoice ID yet, please get in touch with the event\'s Sponsorships Coordinator for more information.', 'wordcamporg' ); ?>
			</p>

			<form method="POST" class="payment-form" data-step="<?php echo STEP_SELECT_INVOICE; ?>">
				<input type="hidden" name="step" value="<?php echo STEP_SELECT_INVOICE; ?>" />
				<input type="hidden" name="sponsor_payment_submit" value="1" />

				<div class="control">
					<input type="radio" id="payment_type_invoice" name="payment_type" value="invoice" checked> <label for="payment_type_invoice"><?php esc_html_e( 'Invoice payment', 'wordcamporg' ); ?></label>
					<input type="radio" id="payment_type_other" name="payment_type" value="other"> <label for="payment_type_other"><?php esc_html_e( 'Other payment', 'wordcamporg' ); ?></label>
				</div>

				<div class="clear"></div>

				<fieldset class="invoice-fields">
					<label class="control-header"><?php esc_html_e( 'Event', 'wordcamporg' ); ?></label>
					<div class="control">
						<?php echo get_wordcamp_dropdown( 'wordcamp_id', $data['wordcamp_query_options'] ); ?>
					</div>

					<label class="control-header"><?php esc_html_e( 'Invoice ID', 'wordcamporg' ); ?></label>
					<div class="control">
						<input type="text" name="invoice_id" />
					</div>
				</fieldset>

				<fieldset class="other-fields">
					<label class="control-header"><?php esc_html_e( 'Description (100 character limit)', 'wordcamporg' ); ?></label>
					<div class="control">
						<input type="text" name="description" maxlength="100" value="" />
					</div>
				</fieldset>

				<div class="clear"></div>

				<label class="control-header"><?php esc_html_e( 'Currency', 'wordcamporg' ); ?></label>
				<div class="control">
					<select name="currency">
						<option value="" disabled selected><?php esc_html_e( 'Select a Currency', 'wordcamporg' ); ?></option>

						<?php foreach ( $data['currencies'] as $currency_key => $currency_name ) : ?>
							<option value="<?php echo esc_attr( $currency_key ); ?>">
								<?php echo esc_html( $currency_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<label class="control-header"><?php esc_html_e( 'Amount', 'wordcamporg' ); ?></label>
				<div class="control">
					<input type="text" name="amount" /><br />
                    <em><?php esc_html_e( 'An additional 2.9% to cover processing fees on credit card payments is highly appreciated but not required.', 'wordcamporg' ); ?></em>
				</div>

				<div class="clear"></div>

				<input type="submit" value="<?php esc_attr_e( 'Continue', 'wordcamporg' ); ?>" />
			</form>

		<?php elseif ( $data['step'] == STEP_PAYMENT_DETAILS ) : ?>

			<p><?php esc_html_e( 'Please review the details below and hit "Make a Payment" when you\'re ready.', 'wordcamporg' ); ?></p>

			<table>
				<?php if ( 'invoice' === $data['payment']['payment_type'] ) : ?>
					<tr>
						<td><?php esc_html_e( 'Event', 'wordcamporg' ); ?></td>
						<td><?php echo esc_html( get_wordcamp_name( get_wordcamp_site_id( $data['payment']['wordcamp_obj'] ) ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Invoice', 'wordcamporg' ); ?></td>
						<td><?php echo esc_html( $data['payment']['invoice_id'] ); ?></td>
					</tr>
				<?php elseif ( 'other' === $data['payment']['payment_type'] ) : ?>
					<tr>
						<td><?php esc_html_e( 'Description', 'wordcamporg' ); ?></td>
						<td><?php echo esc_html( $data['payment']['description'] ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<td><?php esc_html_e( 'Currency', 'wordcamporg' ); ?></td>
					<td><?php echo esc_html( $data['payment']['currency'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Amount', 'wordcamporg' ); ?></td>
					<td><?php echo number_format( round( $data['payment']['amount'], 2 ), 2, '.', ' ' ); ?></td>
				</tr>
			</table>

			<form method="POST" class="payment-form" data-step="<?php echo STEP_PAYMENT_DETAILS; ?>">
				<input type="hidden" name="step" value="<?php echo STEP_PAYMENT_DETAILS; ?>" />
				<input type="hidden" name="sponsor_payment_submit" value="1" />
				<input type="hidden" name="payment_data_json" value="<?php echo esc_attr( $data['payment_data_json'] ); ?>" />
				<input type="hidden" name="payment_data_signature" value="<?php echo esc_attr( $data['payment_data_signature'] ); ?>" />

				<script src="https://checkout.stripe.com/checkout.js" class="stripe-button"
					data-key="<?php echo esc_attr( $data['keys']['publishable'] ); ?>"
					data-amount="<?php echo esc_attr( round( $data['payment']['amount'], 2 ) * 100 ); ?>" <?php // @todo: Handle currencies with multipliers other than 100. ?>
					data-currency="<?php echo esc_attr( $data['payment']['currency'] ); ?>"
					data-name="WordPress Community Support, PBC"
					data-description="<?php esc_attr_e( 'Event Sponsorship Payment', 'wordcamporg' ); ?>"
					data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
					data-locale="auto"
					data-panel-label="<?php esc_attr_e( 'Pay', 'wordcamporg' ); ?>"
					data-label="<?php esc_attr_e( 'Make a Payment', 'wordcamporg' ); ?>"
					data-zip-code="true">
				</script>
			</form>

		<?php elseif ( $data['step'] == STEP_PAYMENT_SUCCESS ) : ?>

			<p class="notice notice-success">
				<strong><?php esc_html_e( 'Success!', 'wordcamporg' ); ?></strong>
				<?php esc_html_e( 'Your payment has been received, thank you!', 'wordcamporg' ); ?>
			</p>

			<ul>
				<li><a href="<?php echo esc_url( add_query_arg( 'again', 1 ) ); ?>"><?php esc_html_e( 'Make another payment', 'wordcamporg' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go back to Central', 'wordcamporg' ); ?></a></li>
			</ul>

		<?php endif; ?>

	</div><!-- #content -->
</div><!-- #container -->

<?php get_footer(); ?>
