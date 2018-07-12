<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Ticket_Revenue;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var \DateTime $xrt_date */
/** @var string    $wordcamp_name */
/** @var array     $data */
/** @var array     $total */

$asterisk2 = false;
?>

<?php foreach ( $data as $key => $group ) : ?>
	<?php if ( empty( $group['gross_revenue_by_currency'] ) ) continue; ?>

	<h3>
		<?php echo esc_html( $group['label'] ); ?>
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<?php if ( $group['description'] ) : ?>
		<p class="description"><?php echo wp_kses_post( $group['description'] ); ?></p>
	<?php endif; ?>

	<table class="striped widefat but-not-too-wide">
		<tbody>
		<tr>
			<td>Tickets sold:</td>
			<td class="number"><?php echo number_format_i18n( $group['tickets_sold'] ); ?></td>
		</tr>
		<tr>
			<td>Tickets refunded:</td>
			<td class="number"><?php echo number_format_i18n( $group['tickets_refunded'] ); ?></td>
		</tr>
		</tbody>
	</table>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Gross Revenue</td>
			<td>Discounts</td>
			<td>Refunds</td>
			<td>Net Revenue</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $group['net_revenue_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $group['gross_revenue_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $group['discounts_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $group['amount_refunded_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $group['net_revenue_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $group['converted_net_revenue'][ $currency ] ); ?>
					<?php if ( $group['net_revenue_by_currency'][ $currency ] > 0 && $group['converted_net_revenue'][ $currency ] === 0 ) : $group['asterisk2'] = $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td>Total: </td>
			<td class="number total">
				<?php echo number_format_i18n( $group['total_converted_revenue'] ); ?>
				<?php if ( isset( $group['asterisk2'] ) ) : ?>
					**
				<?php endif; ?>
			</td>
		</tr>
		</tbody>
	</table>
<?php endforeach; ?>

<?php if ( ! empty( $total['net_revenue_by_currency'] ) ) : ?>
	<p class="description">
		* Estimate based on exchange rates for <?php echo esc_html( $xrt_date->format( 'M jS, Y' ) ); ?>.
		<?php if ( $asterisk2 ) : ?>
			<br />** Currency exchange rate not available.
		<?php endif; ?>
	</p>
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
