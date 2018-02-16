<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Ticket_Revenue;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var array $data */
?>

<?php if ( $data['total_groups'] ) : ?>
	<h3>Meetup groups in the chapter program as of <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

	<h4>Total groups: <?php echo number_format_i18n( $data['total_groups'] ); ?></h4>
	<h4>Total groups by country:</h4>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Country</td>
			<td># of Groups</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $data['total_groups_by_country'] ) as $country ) : ?>
			<tr>
				<td><?php echo esc_html( $country ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['total_groups_by_country'][ $country ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h4>Total group members (non-unique): <?php echo number_format_i18n( $data['total_members'] ); ?></h4>
	<h4>Total group members by country:</h4>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Country</td>
			<td># of Members</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $data['total_members_by_country'] ) as $country ) : ?>
			<tr>
				<td><?php echo esc_html( $country ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['total_members_by_country'][ $country ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $data['joined_groups'] ) : ?>
		<h3>Meetup groups that joined the chapter program between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

		<h4>Total groups that joined: <?php echo number_format_i18n( $data['joined_groups'] ); ?></h4>
		<h4>Total groups that joined by country:</h4>

		<table class="striped widefat but-not-too-wide">
			<thead>
			<tr>
				<td>Country</td>
				<td># of Groups</td>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( array_keys( $data['joined_groups_by_country'] ) as $country ) : ?>
				<tr>
					<td><?php echo esc_html( $country ); ?></td>
					<td class="number"><?php echo number_format_i18n( $data['joined_groups_by_country'][ $country ] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h4>Total group members that joined (non-unique): <?php echo number_format_i18n( $data['joined_members'] ); ?></h4>
		<h4>Total group members that joined by country:</h4>

		<table class="striped widefat but-not-too-wide">
			<thead>
			<tr>
				<td>Country</td>
				<td># of Members</td>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( array_keys( $data['joined_members_by_country'] ) as $country ) : ?>
				<tr>
					<td><?php echo esc_html( $country ); ?></td>
					<td class="number"><?php echo number_format_i18n( $data['joined_members_by_country'][ $country ] ); ?></td>
				</tr>
			<?php endforeach; ?>
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
