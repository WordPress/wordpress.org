<?php

namespace WordCamp\Budgets\Reimbursement_Requests;
defined( 'WPINC' ) or die();

?>

<fieldset <?php disabled( user_can_edit_request( $post ), false ); ?> >
	<ul class="wcb-form">
		<li>
			<label for="_wcbrr_name_of_payer">
				<?php _e( 'Name of Payer:', 'wordcamporg' ); ?>
			</label>

			<input
				type="text"
				class="regular-text"
				id="_wcbrr_name_of_payer"
				name="_wcbrr_name_of_payer"
				value="<?php echo esc_attr( $name_of_payer ); ?>"
			/>
		</li>

		<li>
			<label for="_wcbrr_currency">
				<?php _e( 'Currency:', 'wordcamporg' ) ?>
			</label>

			<select id="_wcbrr_currency" name="_wcbrr_currency">
				<option value="null-select-one">
					<?php _e( '-- Select a Currency --', 'wordcamporg' ); ?>
				</option>
				<option value="null-separator1"></option>

				<?php foreach ( $available_currencies as $currency_key => $currency_name ) : ?>
					<option value="<?php echo esc_attr( $currency_key ); ?>" <?php selected( $currency_key, $selected_currency ); ?> >
						<?php echo esc_html( $currency_name ); ?>
						<?php // todo - For better UX, prepend the code to the name (USD - United States Dollar), and sort by the code. Updating the sorting in get_currencies(). Also make this change other places this is used ?>
					</option>
				<?php endforeach; ?>
			</select>
		</li>

		<li>
			<label for="_wcbrr_reason">
				<?php _e( 'Reason for Reimbursement:', 'wordcamporg' ); ?>
			</label>

			<select id="_wcbrr_reason" name="_wcbrr_reason">
				<option value="null-select-one">
					<?php _e( '-- Select a Reason --', 'wordcamporg' ); ?>
				</option>
				<option value="null-separator1"></option>

				<?php foreach ( $available_reasons as $reason_key => $reason_name ) : ?>
					<option value="<?php echo esc_attr( $reason_key ); ?>" <?php selected( $reason_key, $selected_reason ); ?> >
						<?php echo esc_html( $reason_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</li>

		<li id="_wcbrr_reason_other_container">
			<label for="_wcbrr_reason_other">
				<?php _e( 'Other Reason:', 'wordcamporg' ); ?>
			</label>

			<input
				type="text"
				class="regular-text"
				id="_wcbrr_reason_other"
				name="_wcbrr_reason_other"
				value="<?php echo esc_attr( $other_reason ); ?>"
			/>
		</li>

		<li>
			<label for="_wcbrr_files">
				<?php _e( 'Files:', 'wordcamporg' ); ?>
			</label>

			<div class="wcb-form-input-wrapper">
				<?php require_once( dirname( __DIR__ ) . '/wordcamp-budgets/field-attached-files.php' ); ?>
			</div>
		</li>

	</ul>
</fieldset>
