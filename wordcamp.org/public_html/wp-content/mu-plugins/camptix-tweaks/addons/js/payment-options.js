jQuery( document ).ready( function initPaymentOptions( $ ) {

	/**
	 * Implements tab functionality in payment option selection
	 */
	$( '.tix-payment-tab' ).click( function( event ) {
		$( '.tix-payment-tab' ).removeClass( 'tix-tab-selected' );
		$( event.target ).addClass( 'tix-tab-selected' );

		if( $( event.target ).is( 'label[for=tix-preferred-payment-option]' ) ) {
			$( '.tix-payment-method-container' ).addClass( 'tix-hidden' );
		} else {
			$( '.tix-payment-method-container' ).removeClass( 'tix-hidden' );
			$( 'input#tix-preferred-payment-option' ).prop( 'checked', false );
		}
	});

	// Need to overwrite exiting function because it assumes `select` instead of `radio`
	window.CampTixUtilities.getSelectedPaymentOption = function() {
		return jQuery( '#tix [name="tix_payment_method"]:checked' ).val();
	};

});
