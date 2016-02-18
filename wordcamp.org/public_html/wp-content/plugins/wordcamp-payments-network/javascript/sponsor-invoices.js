( function( $ ) {
	'use strict';

	window.WordCampBudgetsDashboard = window.WordCampBudgetsDashboard || {};

	var app = window.WordCampBudgetsDashboard.SponsorInvoices = {
		/**
		 * Initialization that runs as soon as this file has loaded
		 */
		start : function() {
			try {
				$( '.wcbdsi-approve-invoice' ).click( app.approveInvoice );
			} catch ( exception ) {
				app.log( exception );
			}
		},

		/**
		 * Send an AJAX request to approve the invoice
		 *
		 * @param {event} event
		 */
		approveInvoice : function( event ) {
			var approvalButton = $( this ),
			    statusMessage  = $( this ).parent().find( '.wcbd-inline-notice' ),
			    siteID         = approvalButton.data( 'site-id'    ),
				invoiceID      = approvalButton.data( 'invoice-id' ),
				nonce          = approvalButton.data( 'nonce'      );

			event.preventDefault();

			try {
				approvalButton.addClass( 'hidden' );
				statusMessage.html( 'Submitting to QuickBooks...' );   // todo show spinner instead
				statusMessage.removeClass( 'hidden' );

				$.post(
					ajaxurl,
					{
						action     : 'wcbdsi_approve_invoice',
						nonce      : nonce,
						site_id    : siteID,
						invoice_id : invoiceID
					},

					function( response ) {
						// todo modularize this

						try {
							if ( response.hasOwnProperty( 'success' ) && true === response.success ) {
								statusMessage.addClass( 'notice notice-success' );
								statusMessage.html( _.escape( response.data.success ) );
							} else {
								statusMessage.addClass( 'notice notice-error' );
								statusMessage.html( _.escape( 'ERROR: ' + response.data.error ) );

								// todo bring button back so they can try again?
							}
						} catch ( exception ) {
							app.log( exception );
						}
					}
				);
			} catch ( exception ) {
				app.log( exception );
			}
		},

		/**
		 * Log a message to the console
		 *
		 * todo centralize for other modules
		 *
		 * @param {*} error
		 */
		log : function( error ) {
			if ( ! window.console ) {
				return;
			}

			if ( 'string' === typeof error ) {
				console.log( 'WordCamp Budgets Dashboard: ' + error );
			} else {
				console.log( 'WordCamp Budgets Dashboard: ', error );
			}
		}
	};

} )( jQuery );

window.WordCampBudgetsDashboard.SponsorInvoices.start();
