<?php
namespace WordCamp\Budgets\Sponsor_Payment_Stripe;

get_header();
?>

<div id="container">
	<div id="content" class="wcorg-sponsor-payment" role="main">

		<h1 class="entry-title">Sponsorship Payment</h1>

		<?php if ( ! empty( $data['errors'] ) ) : ?>
			<?php foreach ( $data['errors'] as $error ) : ?>
				<p><strong>Error:</strong> <?php echo esc_html( $error ); ?></p>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( $data['step'] == STEP_SELECT_INVOICE ) : ?>

			<p>Use this form to pay your WordCamp sponsorship fee to WordPress Community Support, PBC. If you did not receive an invoice ID yet, please get in touch with the event's Sponsorships Coordinator for more information.</p>

			<form method="POST">
				<input type="hidden" name="step" value="<?php echo STEP_SELECT_INVOICE; ?>" />
				<input type="hidden" name="sponsor_payment_submit" value="1" />

				<label>Event</label>
				<div class="control">
					<select name="wordcamp_id">
						<option value="" disabled selected>Select a WordCamp</option>
						<?php foreach ( $data['wordcamps'] as $wordcamp ) : ?>
						<option value="<?php echo esc_attr( $wordcamp->ID ); ?>"><?php echo esc_html( $wordcamp->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<label>Invoice ID</label>
				<div class="control">
					<input type="text" name="invoice_id" />
				</div>

				<label>Currency</label>
				<div class="control">
					<select name="currency">
						<option value="" disabled selected>Select a Currency</option>
						<?php foreach ( $data['currencies'] as $currency_key => $currency_name ) : ?>
						<option value="<?php echo esc_attr( $currency_key ); ?>">
							<?php echo esc_html( $currency_name ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>

				<label>Amount</label>
				<div class="control">
					<input type="text" name="amount" />
				</div>

				<div class="clear"></div>

				<input type="submit" value="Continue" />
			</form>

		<?php elseif ( $data['step'] == STEP_PAYMENT_DETAILS ) : ?>

			<p>Please review the details below and hit "Make a Payment" when you're ready.</p>

			<table>
				<tr>
					<td>Invoice</td>
					<td><?php echo esc_html( $data['payment']['invoice_id'] ); ?></td>
				</tr>
				<tr>
					<td>Event</td>
					<td><?php echo esc_html( $data['payment']['wordcamp_obj']->post_title ); ?></td>
				</tr>
				<tr>
					<td>Currency</td>
					<td><?php echo esc_html( $data['payment']['currency'] ); ?></td>
				</tr>
				<tr>
					<td>Amount</td>
					<td><?php echo number_format( round( $data['payment']['amount'], 2 ), 2, '.', ' ' ); ?></td>
				</tr>
			</table>

			<form method="POST">
				<input type="hidden" name="step" value="<?php echo STEP_PAYMENT_DETAILS; ?>" />
				<input type="hidden" name="sponsor_payment_submit" value="1" />
				<input type="hidden" name="payment_data_json" value="<?php echo esc_attr( $data['payment_data_json'] ); ?>" />
				<input type="hidden" name="payment_data_signature" value="<?php echo esc_attr( $data['payment_data_signature'] ); ?>" />

				<script src="https://checkout.stripe.com/checkout.js" class="stripe-button"
					data-key="<?php echo esc_attr( $data['keys']['publishable'] ); ?>"
					data-amount="<?php echo esc_attr( round( $data['payment']['amount'], 2 ) * 100 ); ?>"
					data-currency="<?php echo esc_attr( $data['payment']['currency'] ); ?>"
					data-name="WordPress Community Support, PBC"
					data-description="Event Sponsorship Payment"
					data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
					data-locale="auto"
					data-panel-label="Pay"
					data-label="Make a Payment"
					data-zip-code="true">
				</script>
			</form>

		<?php elseif ( $data['step'] == STEP_PAYMENT_SUCCESS ) : ?>

			<p><strong>Success!</strong> Your payment has been received, thank you!</p>

			<ul>
				<li><a href="<?php echo esc_url( add_query_arg( 'again', 1 ) ); ?>">Make another payment</a></li>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Go back to Central</a></li>
			</ul>

		<?php endif; ?>

	</div><!-- #content -->
</div><!-- #container -->

<?php get_footer(); ?>
