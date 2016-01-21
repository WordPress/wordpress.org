jQuery( document ).ready( function( $ ) {
	'use strict';

	var wcb = window.WordCampBudgets;
	var app = wcb.SponsorInvoices = {

		/**
		 * Main entry point
		 */
		init: function() {
			try {
				app.registerEventHandlers();
				$( '#_wcbsi_sponsor_id' ).trigger( 'change' );  // Populate the initial sponsor information
				$( '#wcbsi-sponsor-information' ).removeClass( 'loading-content' );
				wcb.setupDatePicker( '#wcbsi_sponsor_invoice' );
			} catch ( exception ) {
				wcb.log( exception );
			}
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			$( '#_wcbsi_sponsor_id' ).change( app.populateSponsorInformation );
		},

		/**
		 * Toggle the payment method fields based on which method is selected
		 *
		 * @param {object} event
		 */
		populateSponsorInformation : function( event ) {
			try {
				var info              = $( 'option:selected', this ).data(),
				    sendInvoiceButton = $( '#send-invoice' ),
				    infoContainer     = $( '#wcbsi-sponsor-information' ),
				    infoTemplate;

				if ( $.isEmptyObject( info ) ) {
					infoContainer.html( '' );
					sendInvoiceButton.prop( 'disabled', true );
					return;

				} else if ( info.requiredFieldsComplete ) {
					// todo add info.hasOwnProperty() check
					infoTemplate = wp.template( 'wcbsi-sponsor-information' );
					sendInvoiceButton.prop( 'disabled', false );

				} else {
					infoTemplate = wp.template( 'wcbsi-required-fields-incomplete' );
					sendInvoiceButton.prop( 'disabled', true );
				}

				infoContainer.html( infoTemplate( info ) );
			} catch ( exception ) {
				wcb.log( exception );
			}
		}
	};

	app.init();
} );
