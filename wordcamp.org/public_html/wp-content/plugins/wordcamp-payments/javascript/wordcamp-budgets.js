jQuery( document ).ready( function( $ ) {
	'use strict';

	var app = window.WordCampBudgets = {

		/**
		 * Toggle the payment method fields based on which method is selected
		 *
		 * @param {object} event
		 */
		togglePaymentMethodFields : function( event ) {
			var active_fields_container = '#' + $( this ).attr( 'id' ) + '_fields';
			var payment_method_fields   = '.payment_method_fields';

			$( payment_method_fields   ).removeClass( 'active' );
			$( payment_method_fields   ).addClass( 'hidden' );
			$( active_fields_container ).removeClass( 'hidden' );
			$( active_fields_container ).addClass( 'active' );

			// todo make the transition smoother
		},

		/**
		 * Initialize Core's Media Picker
		 *
		 * @param {object} event
		 */
		showUploadModal : function( event ) {
			if ( 'undefined' == typeof app.fileUploadFrame ) {
				// Create the frame
				app.fileUploadFrame = wp.media( {
					title: wcbLocalizedStrings.uploadModalTitle,
					multiple: true,
					button: {
						text: wcbLocalizedStrings.uploadModalButton
					}
				} );

				// Add models to the collection for each selected attachment
				app.fileUploadFrame.on( 'select', app.addSelectedFilesToCollection );
			}

			app.fileUploadFrame.open();
			return false;
		},

		/**
		 * Add files selected from the Media Picker to the current collection of files
		 */
		addSelectedFilesToCollection : function() {
			// app var needs to point to caller?

			var attachments = app.fileUploadFrame.state().get( 'selection' ).toJSON();

			$.each( attachments, function( index, attachment ) {
				var newFile = new app.AttachedFile( {
					'ID'          : attachment.id,
					'post_parent' : attachment.uploadedTo,
					'filename'    : attachment.filename,
					'url'         : attachment.url
				} );

				app.attachedFilesView.collection.add( newFile );
			} );
		},

		/**
		 * Fallback to the jQueryUI datepicker if the browser doesn't support <input type="date">
		 *
		 * @param {string} selector
		 */
		setupDatePicker : function( selector ) {
			var browserTest = document.createElement( 'input' );
			browserTest.setAttribute( 'type', 'date' );

			if ( 'text' === browserTest.type ) {
				$( selector ).find( 'input[type=date]' ).not( '[readonly="readonly"]' ).datepicker( {
					dateFormat : 'yy-mm-dd',
					changeMonth: true,
					changeYear : true
				} );
			}
		},

		/**
		 * Log a message to the console
		 *
		 * @param {*} error
		 */
		log : function( error ) {
			if ( ! window.console ) {
				return;
			}

			if ( 'string' === typeof error ) {
				console.log( 'WordCamp Budgets: ' + error );
			} else {
				console.log( 'WordCamp Budgets: ', error );
			}
		}
	};
} );
