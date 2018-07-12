<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Sponsorship_Grants;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var \DateTime $xrt_date */
/** @var string    $wordcamp_name */
/** @var array     $data */
/** @var array     $compiled_data */

$asterisk2 = false;
?>

<?php if ( $compiled_data['grant_count'] ) : ?>
	<h3>
		Global Sponsorship Grants
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<table class="striped widefat but-not-too-wide">
		<tbody>
		<tr>
			<td>Grants awarded:</td>
			<td class="number"><?php echo number_format_i18n( $compiled_data['grant_count'] ) ?></td>
		</tr>
		</tbody>
	</table>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Awarded</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $compiled_data['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $compiled_data['total_amount_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $compiled_data['converted_amounts'][ $currency ] ); ?>
					<?php if ( $compiled_data['total_amount_by_currency'][ $currency ] > 0 && $compiled_data['converted_amounts'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $compiled_data['total_amount_converted'] ); ?></td>
		</tr>
		</tbody>
	</table>

	<p class="description">
		* Estimate based on exchange rates for <?php echo esc_html( $xrt_date->format( 'M jS, Y' ) ); ?>.
		<?php if ( $asterisk2 ) : ?>
			<br />** Currency exchange rate not available.
		<?php endif; ?>
	</p>

	<h4>Grant details:</h4>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Date</td>
			<td>WordCamp</td>
			<td>Currency</td>
			<td>Amount</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $data as $grant ) : ?>
			<tr>
				<td><?php echo date( 'Y-m-d', $grant['timestamp'] ); ?></td>
				<td><?php echo esc_html( $grant['name'] ); ?></td>
				<td><?php echo esc_html( $grant['currency'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $grant['amount'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
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
