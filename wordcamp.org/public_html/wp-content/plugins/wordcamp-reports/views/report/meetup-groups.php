<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\Meetup_Groups;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var Report\Meetup_Groups|null $report */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\Meetup_Groups::$name ); ?>
	</h1>

	<?php echo wpautop( wp_kses_post( Report\Meetup_Groups::$description ) ); ?>

	<h4>Methodology</h4>

	<?php echo wpautop( wp_kses_post( Report\Meetup_Groups::$methodology ) ); ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'run-report', Report\Meetup_Groups::$slug . '-nonce' ); ?>

		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row"><label for="start-date">Start Date</label></th>
				<td><input type="date" id="start-date" name="start-date" value="<?php echo esc_attr( $start_date ) ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="end-date">End Date</label></th>
				<td><input type="date" id="end-date" name="end-date" value="<?php echo esc_attr( $end_date ) ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="refresh">Refresh results</label></th>
				<td><input type="checkbox" id="refresh" name="refresh" /></td>
			</tr>
			</tbody>
		</table>

		<?php submit_button( 'Show results', 'primary', 'action', false ); ?>
		<?php submit_button( 'Export CSV', 'secondary', 'action', false ); ?>
	</form>

	<?php if ( $report instanceof Report\Meetup_Groups ) : ?>
		<div class="report-results">
			<?php $report->render_html(); ?>
		</div>
	<?php endif; ?>
</div>
