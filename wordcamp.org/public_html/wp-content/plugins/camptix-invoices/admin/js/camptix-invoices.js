jQuery( document ).ready( function ($) {

	$(document).on( 'change', '#camptix-need-invoice', toggleInvoiceDetailsForm );
	function toggleInvoiceDetailsForm() {
		var $camptixInvoiceDetailsForm = $( '.camptix-invoice-details' );
		$camptixInvoiceDetailsForm.toggle();
		var $camptixInvoiceDetailsFormFields = $camptixInvoiceDetailsForm.find( 'input,textarea,select' );
		var required = $camptixInvoiceDetailsFormFields.eq(0).prop( 'required' );
		$camptixInvoiceDetailsFormFields.prop( 'required', ! required );
	}

});
