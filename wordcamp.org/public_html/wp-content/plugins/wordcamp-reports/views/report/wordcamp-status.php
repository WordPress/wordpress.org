<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Status;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var string $status */
/** @var array  $statuses */
/** @var array  $field_defaults */
/** @var Report\WordCamp_Status|null $report */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\WordCamp_Status::$name ); ?>
	</h1>

	<?php echo wpautop( wp_kses_post( Report\WordCamp_Status::$description ) ); ?>

	<h4>Methodology</h4>

	<?php echo wpautop( wp_kses_post( Report\WordCamp_Status::$methodology ) ); ?>

	<form method="post" action="">
		<input type="hidden" name="action" value="run-report" />
		<?php wp_nonce_field( 'run-report', Report\WordCamp_Status::$slug . '-nonce' ); ?>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="start-date">Start Date</label></th>
					<td><input type="date" id="start-date" name="start-date" value="<?php echo esc_attr( $start_date ) ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="end-date">End Date</label></th>
					<td><input type="date" id="end-date" name="end-date" value="<?php echo esc_attr( $end_date ) ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="status">Status (optional)</label></th>
					<td>
						<select id="status" name="status" class="select2-container">
							<option value="any"<?php selected( ( ! $status || 'any' === $status ) ); ?>>Any</option>
							<?php foreach ( $statuses as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $status ); ?>><?php echo esc_attr( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							This will select all WordCamps that had this status at any time during the date range.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="refresh">Refresh results</label></th>
					<td><input type="checkbox" id="refresh" name="refresh" /></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( 'Show results', 'primary', 'action', false ); ?>

		<button id="fields-toggle" class="secondary button-secondary">Export resulting WordCamps as a CSV</button>

		<section id="fields-section" class="hidden">
			<?php Report\WordCamp_Details::render_available_fields( 'private', $field_defaults ); ?>
			<?php submit_button( 'Export CSV', 'primary', 'action', false ); ?>
		</section>
	</form>

	<?php if ( $report instanceof Report\WordCamp_Status ) : ?>
		<div class="report-results">
			<?php $report->render_html(); ?>
		</div>
	<?php endif; ?>
</div>
