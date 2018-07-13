<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\WordCamp_Payment_Methods;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string    $wordcamp_name */
/** @var array     $method_totals */
/** @var array     $site_totals */

?>

<?php if ( $method_totals['Total'] > 0 ) : ?>
	<h3>
		Payment methods used
		<?php if ( $wordcamp_name ) : ?>
			by <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Payment method</td>
			<td># of tickets purchased</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( $method_totals as $method_id => $method_total ) : if ( 'Total' === $method_id ) continue; ?>
			<tr>
				<td><?php echo esc_html( $method_id ); ?></td>
				<td class="number"><?php echo number_format_i18n( $method_total ); ?></td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $method_totals['Total'] ); ?></td>
		</tr>
		</tbody>
	</table>

	<?php if ( ! $wordcamp_name ) : ?>
		<h3>
			Payment methods by WordCamp
			<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
				on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
			<?php else : ?>
				between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
			<?php endif; ?>
		</h3>

		<table class="striped widefat but-not-too-wide">
			<thead>
			<tr>
				<td>WordCamp Name</td>
				<?php foreach ( array_keys( $method_totals ) as $method_id ) : ?>
					<td><?php echo esc_html( $method_id ); ?></td>
				<?php endforeach; ?>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $site_totals as $blog_id => $totals ) : ?>
				<tr>
					<td><?php echo esc_html( $totals['name'] ); ?></td>
					<?php foreach ( $totals as $method_id => $total ) : if ( 'name' === $method_id ) continue; ?>
						<td class="number<?php if ( 'Total' === $method_id ) echo ' total' ?>"><?php echo number_format_i18n( $total ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
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
