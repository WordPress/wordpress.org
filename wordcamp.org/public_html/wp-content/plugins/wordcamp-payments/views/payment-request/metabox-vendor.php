<table class="form-table">
	<?php
		$this->render_text_input( $post, 'Vendor Name', 'vendor_name' );
		$this->render_text_input( $post, 'Contact Person', 'vendor_contact_person' );
		$this->render_text_input( $post, 'Phone Number', 'vendor_phone_number', '', 'tel' );
		$this->render_text_input( $post, 'Email Address', 'vendor_email_address', '', 'email' );
		$this->render_text_input( $post, 'Street Address', 'vendor_street_address' );
		$this->render_text_input( $post, 'City', 'vendor_city' );
		$this->render_text_input( $post, 'State / Province', 'vendor_state' );
		$this->render_text_input( $post, 'ZIP / Postal Code', 'vendor_zip_code' );
		$this->render_text_input( $post, 'Country', 'vendor_country' );
	?>
</table>
