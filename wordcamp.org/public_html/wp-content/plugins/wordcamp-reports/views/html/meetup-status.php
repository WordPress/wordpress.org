<?php
	/**
	 * @package WordCamp\Reports
	 */

	namespace WordCamp\Reports\Views\HTML\Meetup_Status;
	defined( 'WPINC' ) || die();

	use DateTime;

	/** @var DateTime $start_date */
	/** @var DateTime $end_date */
	/** @var string $status */
	/** @var array $meetups */
	/** @var array $statuses */
?>

<?php if ( count( $meetups ) ) : ?>
	<h3 id="active-heading">
		<?php if ( $status && $status !== 'any' ) : ?>
			Meetups set to &ldquo;<?php echo esc_html( $statuses[ $status ] ); ?>&rdquo;
		<?php else : ?>
			Meetups activity
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>
	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>Meetups</td>
			<td class="number"><?php echo number_format_i18n( count( $meetups ) ); ?></td>
		</tr>
	</table>

	<?php foreach ( $meetups as $meetup ) : ?>
		<p><strong class="active-camp"><?php echo esc_html( $meetup['name'] ); ?></strong> &ndash; <?php echo esc_html( $statuses[ $meetup['latest_status'] ] ); ?></p>
		<ul class="status-log ul-disc">
			<?php foreach ( $meetup['logs'] as $log ) : ?>
				<li><?php
						echo date( 'Y-m-d', $log['timestamp'] );
						echo ': ';
						echo esc_html( $log['message'] );
					?></li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( empty( $meetups ) ) : ?>
	<p>
		No data
		<?php if ( $status && $status !== 'any' ) : ?>
			involving &ldquo;<?php echo esc_html( $statuses[ $status ] ); ?>&rdquo;
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
