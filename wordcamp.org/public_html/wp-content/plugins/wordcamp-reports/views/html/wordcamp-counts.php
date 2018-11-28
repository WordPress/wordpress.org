<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\WordCamp_Counts;
defined( 'WPINC' ) || die();

use DateTime;

/** @var array $data */
/** @var DateTime $start_date */
/** @var DateTime $end_date */
/** @var string $statuses */

$gender_legend = '<span class="description small"><span class="total">Total</span> / F / M / ?</span>';
?>

<?php if ( count( $data['wordcamps'] ) ) : ?>
	<h3 id="active-heading">
		Counts for WordCamps occurring
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>WordCamps</td>
			<td class="number total"><?php echo number_format_i18n( count( $data['wordcamps'] ) ); ?></td>
		</tr>
	</table>

	<h4>Totals and Uniques</h4>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>Type</td>
			<td>Total</td>
			<td>Unique</td>
		</tr>
		<tr>
			<td>Attendees</td>
			<td class="number"><?php echo number_format_i18n( $data['totals']['attendee'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $data['uniques']['attendee'] ); ?></td>
		</tr>
		<tr>
			<td>Organizers</td>
			<td class="number"><?php echo number_format_i18n( $data['totals']['organizer'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $data['uniques']['organizer'] ); ?></td>
		</tr>
		<tr>
			<td>Sessions</td>
			<td class="number"><?php echo number_format_i18n( $data['totals']['session'] ); ?></td>
			<td class="number">n/a</td>
		</tr>
		<tr>
			<td>Speakers</td>
			<td class="number"><?php echo number_format_i18n( $data['totals']['speaker'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $data['uniques']['speaker'] ); ?></td>
		</tr>
		<tr>
			<td>Sponsors</td>
			<td class="number"><?php echo number_format_i18n( $data['totals']['sponsor'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $data['uniques']['sponsor'] ); ?></td>
		</tr>
	</table>

	<?php if ( ! empty( $data['genders'] ) ) : ?>
		<h4>Estimated Gender Breakdown</h4>

		<table class="striped widefat but-not-too-wide">
			<tr>
				<td>Type</td>
				<td>Total</td>
				<td>Female</td>
				<td>Male</td>
				<td>Unknown</td>
			</tr>
			<tr>
				<td>Attendees</td>
				<td class="number"><?php echo number_format_i18n( $data['totals']['attendee'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['attendee']['female'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['attendee']['male'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['attendee']['unknown'] ); ?></td>
			</tr>
			<tr>
				<td>Organizers</td>
				<td class="number"><?php echo number_format_i18n( $data['totals']['organizer'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['organizer']['female'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['organizer']['male'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['organizer']['unknown'] ); ?></td>
			</tr>
			<tr>
				<td>Speakers</td>
				<td class="number"><?php echo number_format_i18n( $data['totals']['speaker'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['speaker']['female'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['speaker']['male'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $data['genders']['speaker']['unknown'] ); ?></td>
			</tr>
		</table>
	<?php endif; ?>

	<h4>WordCamp Details</h4>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>WordCamp</td>
			<td>Date</td>
			<td>Status</td>
			<td>Attendees<?php if ( ! empty( $data['genders'] ) ) : ?><br /><?php echo $gender_legend ?><?php endif; ?></td>
			<td>Organizers<?php if ( ! empty( $data['genders'] ) ) : ?><br /><?php echo $gender_legend ?><?php endif; ?></td>
			<td>Sessions</td>
			<td>Speakers<?php if ( ! empty( $data['genders'] ) ) : ?><br /><?php echo $gender_legend ?><?php endif; ?></td>
			<td>Sponsors</td>
		</tr>
		<?php foreach ( $data['wordcamps'] as $event ) : ?>
			<tr>
				<td><a href="<?php echo esc_attr( $event['info']['URL'] ); ?>"><?php echo esc_html( $event['info']['Name'] ); ?></a></td>
				<td><?php echo esc_html( $event['info']['Start Date (YYYY-mm-dd)'] ); ?></td>
				<td><?php echo esc_html( $event['info']['Status'] ); ?></td>
				<td class="number">
					<span class="total"><?php echo number_format_i18n( $event['totals']['attendee'] ); ?></span>
					<?php if ( ! empty( $data['genders'] ) ) : ?>
						/ <?php echo number_format_i18n( $event['genders']['attendee']['female'] ); ?>
						/ <?php echo number_format_i18n( $event['genders']['attendee']['male'] ); ?>
						/ <?php echo number_format_i18n( $event['genders']['attendee']['unknown'] ); ?>
					<?php endif; ?>
				</td>
				<td class="number">
					<span class="total"><?php echo number_format_i18n( $event['totals']['organizer'] ); ?></span>
					<?php if ( ! empty( $data['genders'] ) ) : ?>
						/ <?php echo number_format_i18n( $event['genders']['organizer']['female'] ); ?>
						/ <?php echo number_format_i18n( $event['genders']['organizer']['male'] ); ?>
						/ <?php echo number_format_i18n( $event['genders']['organizer']['unknown'] ); ?>
					<?php endif; ?>
				</td>
				<td class="number total">
					<?php echo number_format_i18n( $event['totals']['session'] ); ?>
				</td>
				<td class="number">
					<span class="total"><?php echo number_format_i18n( $event['totals']['speaker'] ); ?></span>
					<?php if ( ! empty( $data['genders'] ) ) : ?>
						/ <?php echo number_format_i18n( $event['genders']['speaker']['female'] ); ?>
						/ <?php echo number_format_i18n( $event['genders']['speaker']['male'] ); ?>
						/ <?php echo number_format_i18n( $event['genders']['speaker']['unknown'] ); ?>
					<?php endif; ?>
				</td>
				<td class="number total">
					<?php echo number_format_i18n( $event['totals']['sponsor'] ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
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
