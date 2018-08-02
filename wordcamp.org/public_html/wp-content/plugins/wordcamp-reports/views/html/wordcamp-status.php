<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\WordCamp_Status;
defined( 'WPINC' ) || die();

use DateTime;

/** @var DateTime $start_date */
/** @var DateTime $end_date */
/** @var string $status */
/** @var array $active_camps */
/** @var array $inactive_camps */
/** @var array $statuses */
?>

<?php if ( count( $active_camps ) ) : ?>
	<h3 id="active-heading">
		<?php if ( $status ) : ?>
			WordCamps set to &ldquo;<?php echo esc_html( $statuses[ $status ] ); ?>&rdquo;
		<?php else : ?>
			WordCamp activity
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>Active WordCamps</td>
			<td class="number"><?php echo number_format_i18n( count( $active_camps ) ); ?></td>
		</tr>
	</table>

	<?php foreach ( $active_camps as $active_camp ) : ?>
		<p><strong class="active-camp"><?php echo esc_html( $active_camp['name'] ); ?></strong> &ndash; <?php echo esc_html( $statuses[ $active_camp['latest_status'] ] ); ?></p>
		<ul class="status-log ul-disc">
			<?php foreach ( $active_camp['logs'] as $log ) : ?>
				<li><?php
				echo date( 'Y-m-d', $log['timestamp'] );
				echo ': ';
				echo esc_html( $log['message'] );
				?></li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( count( $inactive_camps ) ) : ?>
	<h3 id="inactive-heading">
		WordCamps
		<?php if ( $status ) : ?>
			set to &ldquo;<?php echo esc_html( $statuses[ $status ] ); ?>&rdquo;
		<?php endif; ?>
		with no activity
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<table class="striped widefat but-not-too-wide">
		<tr>
			<td>Inactive WordCamps</td>
			<td class="number"><?php echo number_format_i18n( count( $inactive_camps ) ); ?></td>
		</tr>
	</table>

	<ul>
	<?php foreach ( $inactive_camps as $inactive_camp ) : ?>
		<li>
			<strong class="inactive-camp"><?php echo esc_html( $inactive_camp['name'] ); ?></strong> &ndash;
			<?php echo esc_html( $statuses[ $inactive_camp['latest_status'] ] ); ?> &ndash;
			<em>Last activity before date range: <?php echo date( 'Y-m-d', $inactive_camp['latest_log']['timestamp'] ); ?></em>
		</li>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( empty( $active_camps ) && empty( $inactive_camps ) ) : ?>
	<p>
		No data
		<?php if ( $status ) : ?>
			involving &ldquo;<?php echo esc_html( $statuses[ $status ] ); ?>&rdquo;
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
