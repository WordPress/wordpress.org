<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Payment_Activity;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var array $requests */
/** @var array $payments */
/** @var array $failures */

$asterisk2 = false;
?>

<?php if ( $requests['vendor_payment_count'] || $requests['reimbursement_count'] ) : ?>
	<h3>
		Requested Payments
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<?php if ( $requests['vendor_payment_count'] ) : ?>
			<li>Vendor payments: <?php echo number_format_i18n( $requests['vendor_payment_count'] ) ?></li>
		<?php endif; ?>
		<?php if ( $requests['reimbursement_count'] ) : ?>
			<li>Reimbursements: <?php echo number_format_i18n( $requests['reimbursement_count'] ) ?></li>
		<?php endif; ?>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Requested</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $requests['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $requests['total_amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $requests['converted_amounts'][ $currency ] ); ?>
					<?php if ( $requests['total_amount_by_currency'][ $currency ] > 0 && $requests['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $requests['total_amount_converted'] ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $payments['vendor_payment_count'] || $payments['reimbursement_count'] ) : ?>
	<h3>
		Completed Payments
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<?php if ( $payments['vendor_payment_count'] ) : ?>
			<li>Vendor payments: <?php echo number_format_i18n( $payments['vendor_payment_count'] ) ?></li>
		<?php endif; ?>
		<?php if ( $payments['reimbursement_count'] ) : ?>
			<li>Reimbursements: <?php echo number_format_i18n( $payments['reimbursement_count'] ) ?></li>
		<?php endif; ?>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Requested</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $payments['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $payments['total_amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $payments['converted_amounts'][ $currency ] ); ?>
					<?php if ( $payments['total_amount_by_currency'][ $currency ] > 0 && $payments['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $payments['total_amount_converted'] ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $failures['vendor_payment_count'] || $failures['reimbursement_count'] ) : ?>
	<h3>
		Failed/Cancelled Payments
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<?php if ( $failures['vendor_payment_count'] ) : ?>
			<li>Vendor payments: <?php echo number_format_i18n( $failures['vendor_payment_count'] ) ?></li>
		<?php endif; ?>
		<?php if ( $failures['reimbursement_count'] ) : ?>
			<li>Reimbursements: <?php echo number_format_i18n( $failures['reimbursement_count'] ) ?></li>
		<?php endif; ?>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Requested</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $failures['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $failures['total_amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $failures['converted_amounts'][ $currency ] ); ?>
					<?php if ( $failures['total_amount_by_currency'][ $currency ] > 0 && $failures['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $failures['total_amount_converted'] ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $requests['vendor_payment_count'] || $requests['reimbursement_count'] || $payments['vendor_payment_count'] || $payments['reimbursement_count'] || $failures['vendor_payment_count'] || $failures['reimbursement_count'] ) : ?>
	<p class="description">* Estimate based on exchange rates for <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></p>
	<?php if ( $asterisk2 ) : ?>
		<p class="description">** Currency exchange rate not available.</p>
	<?php endif; ?>
<?php else : ?>
	<p>
		No data
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
