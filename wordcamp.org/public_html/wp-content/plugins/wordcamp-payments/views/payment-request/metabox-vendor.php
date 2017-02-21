<table class="form-table">
	<?php
		$this->render_text_input( $post, __( 'Vendor Name', 'wordcamporg' ), 'vendor_name' );
		$this->render_text_input( $post, __( 'Contact Person', 'wordcamporg' ), 'vendor_contact_person' );
		$this->render_text_input( $post, __( 'Phone Number', 'wordcamporg' ), 'vendor_phone_number', '', 'tel' );
		$this->render_text_input( $post, __( 'Email Address', 'wordcamporg' ), 'vendor_email_address', '', 'email' );
		$this->render_text_input( $post, __( 'Street Address', 'wordcamporg' ), 'vendor_street_address' );
		$this->render_text_input( $post, __( 'City', 'wordcamporg' ), 'vendor_city' );
		$this->render_text_input( $post, __( 'State / Province', 'wordcamporg' ), 'vendor_state' );
		$this->render_text_input( $post, __( 'ZIP / Postal Code', 'wordcamporg' ), 'vendor_zip_code' );
		$this->render_country_input( $post, __( 'Country', 'wordcamporg' ), 'vendor_country_iso3166' );
	?>
</table>

<p class="wcb-form-required">
	<?php esc_html_e( '* required', 'wordcamporg' ); ?>
</p>
