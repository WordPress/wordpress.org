<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Meetup_Groups;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var array $data */
?>

<?php if ( $data['total_groups'] ) : ?>
	<h3>Total meetup groups in the chapter program as of <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Country</td>
			<td># of Groups</td>
			<td># of Members (non-unique)</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $data['total_groups_by_country'] ) as $country ) : ?>
			<tr>
				<td><?php echo esc_html( $country ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['total_groups_by_country'][ $country ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['total_members_by_country'][ $country ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td class="total">Total</td>
			<td class="number total"><?php echo number_format_i18n( $data['total_groups'] ); ?></td>
			<td class="number total"><?php echo number_format_i18n( $data['total_members'] ); ?></td>
		</tr>
		</tbody>
	</table>

	<?php if ( $data['joined_groups'] ) : ?>
		<h3>New meetup groups that joined the chapter program between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

		<table class="striped widefat but-not-too-wide">
			<thead>
			<tr>
				<td>Country</td>
				<td># of Groups</td>
				<td># of Members (non-unique)</td>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( array_keys( $data['joined_groups_by_country'] ) as $country ) : ?>
				<tr>
					<td><?php echo esc_html( $country ); ?></td>
					<td class="number"><?php echo number_format_i18n( $data['joined_groups_by_country'][ $country ] ); ?></td>
					<td class="number"><?php echo number_format_i18n( $data['joined_members_by_country'][ $country ] ); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td class="total">Total</td>
				<td class="number total"><?php echo number_format_i18n( $data['joined_groups'] ); ?></td>
				<td class="number total"><?php echo number_format_i18n( $data['joined_members'] ); ?></td>
			</tr>
			</tbody>
		</table>
	<?php endif; ?>
<?php else : ?>
	<p>
		No data
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
