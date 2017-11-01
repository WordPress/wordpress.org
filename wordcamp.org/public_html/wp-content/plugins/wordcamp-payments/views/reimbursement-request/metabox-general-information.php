<?php

namespace WordCamp\Budgets\Reimbursement_Requests;
defined( 'WPINC' ) or die();

/** @var \WP_Post $post */
/** @var string   $name_of_payer */
/** @var array    $available_currencies */
/** @var string   $selected_currency  */
/** @var array    $available_reasons */
/** @var string   $selected_reason */
/** @var string   $other_reason */
/** @var int      $date_paid */

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
			    required
			/>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_currency">
				<?php _e( 'Currency:', 'wordcamporg' ) ?>
			</label>

			<select id="_wcbrr_currency" name="_wcbrr_currency">
				<option value="">
					<?php _e( '-- Select a Currency --', 'wordcamporg' ); ?>
				</option>
				<option value=""></option>

				<?php foreach ( $available_currencies as $currency_key => $currency_name ) : ?>
					<option value="<?php echo esc_attr( $currency_key ); ?>" <?php selected( $currency_key, $selected_currency ); ?> >
						<?php echo esc_html( $currency_name ); ?>
						<?php if ( $currency_key ) : ?>
							(<?php echo esc_html( $currency_key ); ?>)
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_reason">
				<?php _e( 'Reason for Reimbursement:', 'wordcamporg' ); ?>
			</label>

			<select id="_wcbrr_reason" name="_wcbrr_reason">
				<option value="">
					<?php _e( '-- Select a Reason --', 'wordcamporg' ); ?>
				</option>
				<option value=""></option>

				<?php foreach ( $available_reasons as $reason_key => $reason_name ) : ?>
					<option value="<?php echo esc_attr( $reason_key ); ?>" <?php selected( $reason_key, $selected_reason ); ?> >
						<?php echo esc_html( $reason_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
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

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_date_paid">
				<?php _e( 'Payment Release Date:', 'wordcamporg' ); ?>
			</label>

			<?php if ( $date_paid ) $date_paid = date( 'Y-m-d', $date_paid ); ?>
			<input type="date" class="regular-text" id="_wcbrr_date_paid" name="_wcbrr_date_paid"
				<?php if ( ! current_user_can( 'manage_network' ) ) : ?>readonly<?php endif; ?>
				value="<?php echo esc_attr( $date_paid ); ?>" />

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

<p class="wcb-form-required">
	<?php _e( '* required', 'wordcamporg' ); ?>
</p>
