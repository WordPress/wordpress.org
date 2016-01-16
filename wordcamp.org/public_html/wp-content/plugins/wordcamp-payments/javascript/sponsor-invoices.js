jQuery( document ).ready( function( $ ) {
	'use strict';

	$.sponsorInvoices = {

		/**
		 * Main entry point
		 */
		init: function() {
			try {
				$.sponsorInvoices.registerEventHandlers();
				$( '#_wcbsi_sponsor_id' ).trigger( 'change' );  // Populate the initial sponsor information
				$.sponsorInvoices.setupDatePicker();
			} catch ( exception ) {
				$.sponsorInvoices.log( exception );
			}
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			$( '#_wcbsi_sponsor_id' ).change( $.sponsorInvoices.populateSponsorInformation );

			// todo detect when invoice fields incomplete, disable submit button (but not draft), and show notice
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
					return;
				}

				if ( info.requiredFieldsComplete ) {
					infoTemplate = wp.template( 'wcbsi-sponsor-information' );
					sendInvoiceButton.prop( 'disabled', false );
				} else {
					infoTemplate = wp.template( 'wcbsi-required-fields-incomplete' );
					sendInvoiceButton.prop( 'disabled', true );
				}

				infoContainer.html( infoTemplate( info ) );
			} catch ( exception ) {
				$.sponsorInvoices.log( exception );
			}
		},

		/**
		 * Fallback to the jQueryUI datepicker if the browser doesn't support <input type="date">
		 *
		 * todo this is mostly duplicate of same function in payment-requests.js. should make DRY
		 */
		setupDatePicker : function() {
			var browserTest = document.createElement( 'input' );
			browserTest.setAttribute( 'type', 'date' );

			if ( 'text' === browserTest.type ) {
				$( '#wcbsi_sponsor_invoice' ).find( 'input[type=date]' ).not( '[readonly="readonly"]' ).datepicker( {
					dateFormat : 'yy-mm-dd',
					changeMonth: true,
					changeYear : true
				} );
			}
		},

		/**
		 * Log a message to the console
		 *
		 * @todo centralize this for all modules to use
		 *
		 * @param {*} error
		 */
		log : function( error ) {
			if ( ! window.console ) {
				return;
			}

			if ( 'string' === typeof error ) {
				console.log( 'WordCamp Sponsor Invoices: ' + error );
			} else {
				console.log( 'WordCamp Sponsor Invoices: ', error );
			}
		}
	};

	$.sponsorInvoices.init();
} );
