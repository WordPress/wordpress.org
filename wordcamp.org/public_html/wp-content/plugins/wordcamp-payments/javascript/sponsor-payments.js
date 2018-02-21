
( function( window, $ ) {

	'use strict';

	var preLoadedData = window.WordCampSponsorPayments || {},
		app;

	app = $.extend( preLoadedData, {
		/**
		 * Run on page load.
		 */
		init: function() {
			$.map( this.steps, parseInt );

			var $form = $('.payment-form'),
				currentStep;

			if ( $form.length ) {
				currentStep = parseInt( $form.data('step') );
			}

			switch ( currentStep ) {
				case this.steps['select-invoice'] :
					this.initSelectInvoice();
					break;
			}
		},

		/**
		 * Run if the page is on the Select Invoice step.
		 */
		initSelectInvoice: function() {
			var $form = $('.payment-form'),
				$controlType = $form.find('input[name=payment_type]'),
				$invoiceFields = $form.find('.invoice-fields'),
				$otherFields = $form.find('.other-fields');

			$controlType.change( function() {
				if ( $( this ).is(':checked') ){
					switch ( this.value ) {
						case 'invoice' :
							$invoiceFields.show();
							$otherFields.hide();
							break;

						case 'other' :
							$otherFields.show();
							$invoiceFields.hide();
							break;
					}
				}
			} );

			$controlType.trigger( 'change' );
		}
	} );

	app.init();

} )( window, jQuery );
