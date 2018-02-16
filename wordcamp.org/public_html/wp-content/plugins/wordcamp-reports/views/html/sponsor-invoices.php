<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Sponsor_Invoices;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var array $invoices */
/** @var array $payments */

$asterisk2 = false;
?>

<?php if ( $invoices['total_count'] > 0 ) : ?>
	<h3>
		Sponsor Invoices Sent
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
		<li>Invoices sent: <?php echo number_format_i18n( $invoices['total_count'] ); ?></li>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Amount</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $invoices['amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $invoices['amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $invoices['converted_amounts'][ $currency ] ); ?>
					<?php if ( $invoices['amount_by_currency'][ $currency ] > 0 && $invoices['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $invoices['total_amount_converted'] ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $payments['total_count'] > 0 ) : ?>
	<h3>
		Sponsor Invoice Payments Received
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
		<li>Payments received: <?php echo number_format_i18n( $payments['total_count'] ); ?></li>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Amount</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $payments['amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $payments['amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $payments['converted_amounts'][ $currency ] ); ?>
					<?php if ( $invoices['amount_by_currency'][ $currency ] > 0 && $invoices['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
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

<?php if ( $invoices['total_count'] > 0 || $payments['total_count'] > 0 ) : ?>
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
