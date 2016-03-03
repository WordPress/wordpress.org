<?php

namespace WordCamp\Budgets\Reimbursement_Requests;
defined( 'WPINC' ) or die();

?>

<fieldset <?php disabled( user_can_edit_request( $post ), false ); ?> >
	<input
		id="wcbrr-expenses-data"
		name="wcbrr-expenses-data"
		type="hidden"
		value="<?php echo esc_attr( wp_json_encode( $expenses ) ); ?>"
	/>

	<div id="wcbrr-expenses-container" class="loading-content">
		<span class="spinner is-active"></span>
	</div>

	<?php submit_button( __( 'Add Another Expense', 'wordcamporg' ), 'secondary', 'wcbrr-add-another-expense' ); ?>
</fieldset>

<p class="wcb-form-required">
	<?php _e( '* required', 'wordcamporg' ); ?>
</p>
