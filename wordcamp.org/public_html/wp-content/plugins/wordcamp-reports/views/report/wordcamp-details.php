<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Details;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var array $field_defaults */
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
				<th scope="row"><label for="start-date">Start Date (optional)</label></th>
				<td><input type="date" id="start-date" name="start-date" value="" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="end-date">End Date (optional)</label></th>
				<td><input type="date" id="end-date" name="end-date" value="" /></td>
			</tr>
			</tbody>
		</table>

		<?php Report\WordCamp_Details::render_available_fields( 'private', $field_defaults ) ?>

		<?php submit_button( 'Export CSV', 'primary', 'action', false ); ?>
	</form>
</div>
