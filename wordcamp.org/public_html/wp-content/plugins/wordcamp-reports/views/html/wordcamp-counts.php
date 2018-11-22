<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\WordCamp_Counts;
defined( 'WPINC' ) || die();

use DateTime;

/** @var DateTime $start_date */
/** @var DateTime $end_date */
/** @var string $statuses */
/** @var array $data */
/** @var array $totals */
/** @var array $uniques */
/** @var array $genders */

$gender_legend = '<span class="description small"><span class="total">Total</span> / F / M / ?</span>';
?>

<?php if ( count( $data ) ) : ?>
	<h3 id="active-heading">
		Numbers for WordCamps occurring
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>WordCamps</td>
			<td class="number total"><?php echo number_format_i18n( count( $data ) ); ?></td>
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
			<td class="number"><?php echo number_format_i18n( $totals['attendees'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $uniques['attendees'] ); ?></td>
		</tr>
		<tr>
			<td>Organizers</td>
			<td class="number"><?php echo number_format_i18n( $totals['organizers'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $uniques['organizers'] ); ?></td>
		</tr>
		<tr>
			<td>Sessions</td>
			<td class="number"><?php echo number_format_i18n( $totals['sessions'] ); ?></td>
			<td class="number">n/a</td>
		</tr>
		<tr>
			<td>Speakers</td>
			<td class="number"><?php echo number_format_i18n( $totals['speakers'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $uniques['speakers'] ); ?></td>
		</tr>
		<tr>
			<td>Sponsors</td>
			<td class="number"><?php echo number_format_i18n( $totals['sponsors'] ); ?></td>
			<td class="number"><?php echo number_format_i18n( $uniques['sponsors'] ); ?></td>
		</tr>
	</table>

	<?php if ( ! empty( $genders ) ) : ?>
		<h4>Gender Breakdown</h4>

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
				<td class="number"><?php echo number_format_i18n( $totals['attendees'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['attendees']['female'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['attendees']['male'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['attendees']['unknown'] ); ?></td>
			</tr>
			<tr>
				<td>Organizers</td>
				<td class="number"><?php echo number_format_i18n( $totals['organizers'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['organizers']['female'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['organizers']['male'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['organizers']['unknown'] ); ?></td>
			</tr>
			<tr>
				<td>Speakers</td>
				<td class="number"><?php echo number_format_i18n( $totals['speakers'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['speakers']['female'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['speakers']['male'] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $genders['speakers']['unknown'] ); ?></td>
			</tr>
		</table>
	<?php endif; ?>

	<h4>WordCamp Details</h4>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>WordCamp</td>
			<td>Date</td>
			<td>Status</td>
			<td>Attendees<?php if ( ! empty( $genders ) ) : ?><br /><?php echo $gender_legend ?><?php endif; ?></td>
			<td>Organizers<?php if ( ! empty( $genders ) ) : ?><br /><?php echo $gender_legend ?><?php endif; ?></td>
			<td>Sessions</td>
			<td>Speakers<?php if ( ! empty( $genders ) ) : ?><br /><?php echo $gender_legend ?><?php endif; ?></td>
			<td>Sponsors</td>
		</tr>
		<?php foreach ( $data as $event ) : ?>
			<tr>
				<td><a href="<?php echo esc_attr( $event['URL'] ); ?>"><?php echo esc_html( $event['Name'] ); ?></a></td>
				<td><?php echo esc_html( $event['Start Date (YYYY-mm-dd)'] ); ?></td>
				<td><?php echo esc_html( $event['Status'] ); ?></td>
				<td class="number">
					<span class="total"><?php echo number_format_i18n( $event['attendees']['total'] ); ?></span>
					<?php if ( ! empty( $genders ) ) : ?>
						/ <?php echo number_format_i18n( $event['attendees']['gender']['female'] ); ?>
						/ <?php echo number_format_i18n( $event['attendees']['gender']['male'] ); ?>
						/ <?php echo number_format_i18n( $event['attendees']['gender']['unknown'] ); ?>
					<?php endif; ?>
				</td>
				<td class="number">
					<span class="total"><?php echo number_format_i18n( $event['organizers']['total'] ); ?></span>
					<?php if ( ! empty( $genders ) ) : ?>
						/ <?php echo number_format_i18n( $event['organizers']['gender']['female'] ); ?>
						/ <?php echo number_format_i18n( $event['organizers']['gender']['male'] ); ?>
						/ <?php echo number_format_i18n( $event['organizers']['gender']['unknown'] ); ?>
					<?php endif; ?>
				</td>
				<td class="number total">
					<?php echo number_format_i18n( $event['sessions']['total'] ); ?>
				</td>
				<td class="number">
					<span class="total"><?php echo number_format_i18n( $event['speakers']['total'] ); ?></span>
					<?php if ( ! empty( $genders ) ) : ?>
						/ <?php echo number_format_i18n( $event['speakers']['gender']['female'] ); ?>
						/ <?php echo number_format_i18n( $event['speakers']['gender']['male'] ); ?>
						/ <?php echo number_format_i18n( $event['speakers']['gender']['unknown'] ); ?>
					<?php endif; ?>
				</td>
				<td class="number total">
					<?php echo number_format_i18n( $event['sponsors']['total'] ); ?>
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
