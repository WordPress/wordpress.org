<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Counts;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var array  $statuses */
/** @var bool   $include_gender */
/** @var array  $all_statuses */
/** @var Report\WordCamp_Counts|null $report */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\WordCamp_Counts::$name ); ?>
	</h1>

	<?php echo wpautop( wp_kses_post( Report\WordCamp_Counts::$description ) ); ?>

	<h4>Methodology</h4>

	<?php echo wpautop( wp_kses_post( Report\WordCamp_Counts::$methodology ) ); ?>

	<form method="post" action="">
		<input type="hidden" name="action" value="run-report" />
		<?php wp_nonce_field( 'run-report', Report\WordCamp_Counts::$slug . '-nonce' ); ?>

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
				<th scope="row"><label for="statuses">Statuses (optional)</label></th>
				<td>
					<select id="statuses" name="statuses[]" class="select2-container" multiple>
						<?php foreach ( $all_statuses as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( in_array( $value, $statuses ) ); ?>><?php echo esc_attr( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="refresh">Include estimated gender breakdowns</label></th>
				<td><input type="checkbox" id="include-gender" name="include-gender" <?php checked( $include_gender ); ?> /></td>
			</tr>
			<tr>
				<th scope="row"><label for="refresh">Refresh results</label></th>
				<td><input type="checkbox" id="refresh" name="refresh" /></td>
			</tr>
			</tbody>
		</table>

		<?php submit_button( 'Show results', 'primary', 'action', false ); ?>
	</form>

	<?php if ( $report instanceof Report\WordCamp_Counts ) : ?>
		<div class="report-results">
			<?php $report->render_html(); ?>
		</div>
	<?php endif; ?>
</div>

<script type="application/javascript">
	jQuery( document ).ready( function() {
		jQuery( '#statuses' ).select2();
	} );
</script>
