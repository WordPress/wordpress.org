<?php
namespace WordCamp\Budgets\Sponsor_Payment_Stripe;

get_header();
?>

<div id="container">
	<div id="content" class="wcorg-sponsor-payment" role="main">

		<h1 class="entry-title"><?php esc_html_e( 'Sponsorship Payment', 'wordcamporg' ); ?></h1>

		<?php if ( ! empty( $data['errors'] ) ) : ?>
			<?php foreach ( $data['errors'] as $error ) : ?>
				<p><strong><?php esc_html_e( 'Error:', 'wordcamporg' ); ?></strong> <?php echo esc_html( $error ); ?></p>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( $data['step'] == STEP_SELECT_INVOICE ) : ?>

			<p><?php esc_html_e( 'Use this form to pay your WordCamp sponsorship fee to WordPress Community Support, PBC. If you did not receive an invoice ID yet, please get in touch with the event\'s Sponsorships Coordinator for more information.', 'wordcamporg' ); ?></p>

			<form method="POST">
				<input type="hidden" name="step" value="<?php echo STEP_SELECT_INVOICE; ?>" />
				<input type="hidden" name="sponsor_payment_submit" value="1" />

				<label><?php esc_html_e( 'Event', 'wordcamporg' ); ?></label>
				<div class="control">
					<select name="wordcamp_id">
						<option value="" disabled selected><?php esc_html_e( 'Select a WordCamp', 'wordcamporg' ); ?></option>
						<?php foreach ( $data['wordcamps'] as $wordcamp ) : ?>
						<option value="<?php echo esc_attr( $wordcamp->ID ); ?>"><?php echo esc_html( $wordcamp->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<label><?php esc_html_e( 'Invoice ID', 'wordcamporg' ); ?></label>
				<div class="control">
					<input type="text" name="invoice_id" />
				</div>

				<label><?php esc_html_e( 'Currency', 'wordcamporg' ); ?></label>
				<div class="control">
					<select name="currency">
						<option value="" disabled selected><?php esc_html_e( 'Select a Currency', 'wordcamporg' ); ?></option>
						<option value=""></option>

						<?php foreach ( $data['currencies'] as $currency_key => $currency_name ) : ?>
							<option value="<?php echo esc_attr( $currency_key ); ?>">
								<?php echo esc_html( $currency_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<label><?php esc_html_e( 'Amount', 'wordcamporg' ); ?></label>
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
				<tr>
					<td><?php esc_html_e( 'Invoice', 'wordcamporg' ); ?></td>
					<td><?php echo esc_html( $data['payment']['invoice_id'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Event', 'wordcamporg' ); ?></td>
					<td><?php echo esc_html( $data['payment']['wordcamp_obj']->post_title ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Currency', 'wordcamporg' ); ?></td>
					<td><?php echo esc_html( $data['payment']['currency'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Amount', 'wordcamporg' ); ?></td>
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
					data-description="<?php esc_attr_e( 'Event Sponsorship Payment', 'wordcamporg' ); ?>"
					data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
					data-locale="auto"
					data-panel-label="<?php esc_attr_e( 'Pay', 'wordcamporg' ); ?>"
					data-label="<?php esc_attr_e( 'Make a Payment', 'wordcamporg' ); ?>"
					data-zip-code="true">
				</script>
			</form>

		<?php elseif ( $data['step'] == STEP_PAYMENT_SUCCESS ) : ?>

			<p><strong><?php esc_html_e( 'Success!', 'wordcamporg' ); ?></strong> <?php esc_html_e( 'Your payment has been received, thank you!', 'wordcamporg' ); ?></p>

			<ul>
				<li><a href="<?php echo esc_url( add_query_arg( 'again', 1 ) ); ?>"><?php esc_html_e( 'Make another payment', 'wordcamporg' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go back to Central', 'wordcamporg' ); ?></a></li>
			</ul>

		<?php endif; ?>

	</div><!-- #content -->
</div><!-- #container -->

<?php get_footer(); ?>
