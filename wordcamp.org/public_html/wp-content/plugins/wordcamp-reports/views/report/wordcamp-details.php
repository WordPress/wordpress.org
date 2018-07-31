<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Details;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var bool   $include_dateless */
/** @var string $status */
/** @var array  $statuses */
/** @var array  $available_fields */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\WordCamp_Details::$name ); ?>
	</h1>

	<?php echo wpautop( wp_kses_post( Report\WordCamp_Details::$description ) ); ?>

	<h4>Methodology</h4>

	<?php echo wpautop( wp_kses_post( Report\WordCamp_Details::$methodology ) ); ?>

	<form method="post" action="">
		<input type="hidden" name="action" value="run-report" />
		<?php wp_nonce_field( 'run-report', Report\WordCamp_Details::$slug . '-nonce' ); ?>

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
				<th scope="row"><label for="include_dateless">Include WordCamps without a date</label></th>
				<td><input type="checkbox" id="include_dateless" name="include_dateless"<?php checked( $include_dateless ); ?> /></td>
			</tr>
			<tr>
				<th scope="row"><label for="status">Status (optional)</label></th>
				<td>
					<select id="status" name="status">
						<option value="any"<?php selected( ( ! $status || 'any' === $status ) ); ?>>Any</option>
						<?php foreach ( $statuses as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $status ); ?>><?php echo esc_attr( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="refresh">Refresh results</label></th>
				<td><input type="checkbox" id="refresh" name="refresh" /></td>
			</tr>
			</tbody>
		</table>

		<fieldset class="fields-container">
			<legend class="fields-label">Available Fields</legend>

			<?php foreach ( $available_fields as $field_name => $extra_props ) : ?>
				<div class="field-checkbox">
					<input
						type="checkbox"
						id="fields-<?php echo esc_attr( $field_name ); ?>"
						name="fields[]"
						value="<?php echo esc_attr( $field_name ); ?>"
						<?php if ( $extra_props && is_string( $extra_props ) ) echo esc_html( $extra_props ); ?>
					/>
					<label for="fields-<?php echo esc_attr( $field_name ); ?>">
						<?php echo esc_attr( $field_name ); ?>
					</label>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<?php submit_button( 'Export CSV', 'primary', 'action', false ); ?>
	</form>
</div>
