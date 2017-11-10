<?php if ( current_user_can( 'view_wordcamp_payment_details' ) ) : ?>

	<?php if ( ! empty( $box['args']['introduction_message'] ) ) : ?>
		<p>
			<?php echo wp_kses( $box['args']['introduction_message'], array( 'p' => array() ) ); ?>
		</p>
	<?php endif; ?>

	<p>
		<?php echo esc_html( sprintf(
			__( "Payment information will be deleted %d days after the payment has been sent. Until then, it will be available to you and to trusted network administrators.", 'wordcamporg' ),
			WordCamp_Budgets::PAYMENT_INFO_RETENTION_PERIOD
		) ); ?>
	</p>

	<fieldset <?php disabled( $box['args']['fields_enabled'], false ); ?> >
		<table class="form-table">
			<?php if ( $box['args']['show_vendor_requested_payment_method'] ) : ?>
				<?php $this->render_textarea_input(
					$post,
					esc_html__( 'Did the vendor request a specific type of payment?', 'wordcamporg' ),
					'vendor_requested_payment_method',
					esc_html__( 'Add any relevant details', 'wordcamporg' ),
					false
				); ?>
			<?php endif;?>

			<?php $this->render_radio_input( $post, esc_html__( 'Payment Method', 'wordcamporg' ), 'payment_method' ); ?>
		</table>

		<table id="payment_method_direct_deposit_fields" class="form-table payment_method_fields <?php echo 'Direct Deposit' == $selected_payment_method ? 'active' : 'hidden'; ?>">
			<?php $this->render_text_input(  $post, esc_html__( 'Bank Name',           'wordcamporg' ),	'ach_bank_name'           ); ?>
			<?php $this->render_radio_input( $post, esc_html__( 'Account Type',        'wordcamporg' ),	'ach_account_type'        ); ?>
			<?php $this->render_text_input(  $post, esc_html__( 'Routing Number',      'wordcamporg' ),	'ach_routing_number'      ); ?>
			<?php $this->render_text_input(  $post, esc_html__( 'Account Number',      'wordcamporg' ),	'ach_account_number'      ); ?>
			<?php $this->render_text_input(  $post, esc_html__( 'Account Holder Name', 'wordcamporg' ),	'ach_account_holder_name' ); ?>
		</table>

		<div id="payment_method_check_fields" class="form-table payment_method_fields <?php echo 'Check' == $selected_payment_method ? 'active' : 'hidden'; ?>">
			<table>
				<?php $this->render_text_input(    $post, esc_html__( 'Payable To',        'wordcamporg' ), 'payable_to'           ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Street Address',    'wordcamporg' ), 'check_street_address' ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'City',              'wordcamporg' ), 'check_city'           ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'State / Province',  'wordcamporg' ), 'check_state'          ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'ZIP / Postal Code', 'wordcamporg' ), 'check_zip_code'       ); ?>
				<?php $this->render_country_input( $post, esc_html__( 'Country',           'wordcamporg' ), 'check_country'        ); ?>
			</table>
		</div>

		<p id="payment_method_credit_card_fields" class="description payment_method_fields <?php echo 'Credit Card' == $selected_payment_method ? 'active' : 'hidden'; ?>">
			<?php esc_html_e( 'Please make sure that you upload an authorization form above, if one is required by the vendor.', 'wordcamporg' ); ?>
		</p>

		<div id="payment_method_wire_fields" class="form-table payment_method_fields <?php echo 'Wire' == $selected_payment_method ? 'active' : 'hidden'; ?>">
			<h3>
				<?php esc_html_e( 'Beneficiary’s Bank', 'wordcamporg' ); ?>
			</h3>

			<table>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Bank Name',              'wordcamporg' ), 'bank_name'                  ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Bank Street Address',    'wordcamporg' ), 'bank_street_address'        ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Bank City',              'wordcamporg' ), 'bank_city'                  ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Bank State / Province',  'wordcamporg' ), 'bank_state'                 ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Bank ZIP / Postal Code', 'wordcamporg' ), 'bank_zip_code'              ); ?>
				<?php $this->render_country_input( $post, esc_html__( 'Beneficiary’s Bank Country',           'wordcamporg' ), 'bank_country_iso3166'       ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Bank SWIFT BIC',         'wordcamporg' ), 'bank_bic'                   ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Account Number or IBAN', 'wordcamporg' ), 'beneficiary_account_number' ); ?>
			</table>

			<hr />

			<h3>
				<?php esc_html_e( 'Intermediary Bank', 'wordcamporg' ); ?>
			</h3>

			<?php $this->render_checkbox_input(
				$post,
				esc_html__( 'Send this payment through an intermediary bank', 'wordcamporg' ),
				'needs_intermediary_bank'
			); ?>

			<table id="intermediary_bank_fields">
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank Name',              'wordcamporg' ), 'interm_bank_name'            ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank Street Address',    'wordcamporg' ), 'interm_bank_street_address'  ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank City',              'wordcamporg' ), 'interm_bank_city'            ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank State / Province',  'wordcamporg' ), 'interm_bank_state'           ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank ZIP / Postal Code', 'wordcamporg' ), 'interm_bank_zip_code'        ); ?>
				<?php $this->render_country_input( $post, esc_html__( 'Intermediary Bank Country',           'wordcamporg' ), 'interm_bank_country_iso3166' ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank SWIFT BIC',         'wordcamporg' ), 'interm_bank_swift'           ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Intermediary Bank Account',           'wordcamporg' ), 'interm_bank_account'         ); ?>
			</table>

			<hr />

			<h3>
				<?php esc_html_e( 'Beneficiary', 'wordcamporg' ); ?>
			</h3>

			<table>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Name',              'wordcamporg' ), 'beneficiary_name'            ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s Street Address',    'wordcamporg' ), 'beneficiary_street_address'  ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s City',              'wordcamporg' ), 'beneficiary_city'            ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s State / Province',  'wordcamporg' ), 'beneficiary_state'           ); ?>
				<?php $this->render_text_input(    $post, esc_html__( 'Beneficiary’s ZIP / Postal Code', 'wordcamporg' ), 'beneficiary_zip_code'        ); ?>
				<?php $this->render_country_input( $post, esc_html__( 'Beneficiary’s Country',           'wordcamporg' ), 'beneficiary_country_iso3166' ); ?>
			</table>
		</div>
	</fieldset>

	<p class="wcb-form-required">
		<?php esc_html_e( '* required', 'wordcamporg' ); ?>
	</p>

<?php else : ?>

	<?php esc_html_e( 'Only the request author and network administrators can view payment details.', 'wordcamporg' ); ?>

<?php endif; ?>
