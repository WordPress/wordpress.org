<fieldset <?php disabled( $box['args']['fields_enabled'], false ); ?> >
	<table class="form-table">
		<?php $this->render_radio_input( $post, 'Payment Method', 'payment_method' ); ?>
		<?php $this->render_checkbox_input( $post, 'Reimbursing Personal Expense', 'requesting_reimbursement', 'Check this box if you paid for this expense out of pocket. Please attach the original payment support below with the vendor attached (if any), and proof of disbursed funds.' ); ?>
	</table>

	<table id="payment_method_check_fields" class="form-table payment_method_fields <?php echo 'Check' == $selected_payment_method ? 'active' : 'hidden'; ?>">
		<?php $this->render_text_input( $post, 'Payable To', 'payable_to' ); ?>
	</table>

	<p id="payment_method_credit_card_fields" class="description payment_method_fields <?php echo 'Credit Card' == $selected_payment_method ? 'active' : 'hidden'; ?>">
		<?php _e( 'Please make sure that you upload an authorization form below, if one is required by the vendor.', 'wordcamporg' ); ?>
	</p>

	<div id="payment_method_wire_fields" class="form-table payment_method_fields <?php echo 'Wire' == $selected_payment_method ? 'active' : 'hidden'; ?>">
		<table>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank Name',              'bank_name' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank Street Address',    'bank_street_address' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank City',              'bank_city' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank State / Province',  'bank_state' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank ZIP / Postal Code', 'bank_zip_code' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank Country',           'bank_country' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Bank SWIFT BIC',         'bank_bic' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Account Number or IBAN', 'beneficiary_account_number' ); ?>
		</table>

		<table>
			<?php $this->render_text_input( $post, 'Beneficiary’s Name',              'beneficiary_name' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Street Address',    'beneficiary_street_address' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s City',              'beneficiary_city' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s State / Province',  'beneficiary_state' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s ZIP / Postal Code', 'beneficiary_zip_code' ); ?>
			<?php $this->render_text_input( $post, 'Beneficiary’s Country',           'beneficiary_country' ); ?>
		</table>
	</div>
</fieldset>
