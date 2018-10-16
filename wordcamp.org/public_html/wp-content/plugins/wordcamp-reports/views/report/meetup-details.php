<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\Meetup_Details;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var array $field_defaults */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\Meetup_Details::$name ); ?>
	</h1>

	<?php echo wpautop( wp_kses_post( Report\Meetup_Details::$description ) ); ?>

	<h4>Methodology</h4>

	<?php echo wpautop( wp_kses_post( Report\Meetup_Details::$methodology ) ); ?>

	<form method="post" action="">
		<input type="hidden" name="action" value="run-report" />
		<?php wp_nonce_field( 'run-report', Report\Meetup_Details::$slug . '-nonce' ); ?>


		<?php Report\Meetup_Details::render_available_fields( 'private', $field_defaults ) ?>

		<?php submit_button( 'Export CSV', 'primary', 'action', false ); ?>
	</form>
</div>
