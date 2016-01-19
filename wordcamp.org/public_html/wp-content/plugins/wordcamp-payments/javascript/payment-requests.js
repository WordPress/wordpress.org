jQuery( document ).ready( function( $ ) {
	'use strict';

	var wcb = window.WordCampBudgets;
	var app = wcb.PaymentRequests = {
		
		/**
		 * Main entry point
		 */
		init: function () {
			try {
				app.registerEventHandlers();
				wcb.attachedFilesView = new wcb.AttachedFilesView();
				wcb.setupDatePicker( '#wcp_general_info' );
			} catch ( exception ) {
				wcb.log( exception );
			}
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			$( '#wcp_payment_details' ).find( 'input[name=payment_method]' ).change( wcb.togglePaymentMethodFields );
			$( '#payment_category' ).change( app.toggleOtherCategoryDescription );
				// todo this needs to fire onLoad too, otherwise the field is hidden
			$( '#wcp_files' ).find( 'a.wcp-insert-media' ).click( { title : wcpLocalizedStrings.uploadModalTitle }, wcb.showUploadModal );
			$( '#wcp_mark_incomplete_checkbox' ).click( app.requireNotes );
		},

		/**
		 * Toggle the extra input field when the user selects the Other category
		 *
		 * @param {object} event
		 */
		toggleOtherCategoryDescription : function( event ) {
			try {
				var otherCategoryDescription = $( '#row-other-category-explanation' );

				if ( 'other' == $( this ).find( 'option:selected' ).val() ) {
					$( otherCategoryDescription ).removeClass( 'hidden' );
				} else {
					$( otherCategoryDescription ).addClass( 'hidden' );
				}

				// todo make the transition smoother
			} catch ( exception ) {
				wcb.log( exception );
			}
		},

		/**
		 * Require notes when the request is being marked as incomplete
		 *
		 * @param {object} event
		 */
		requireNotes : function( event ) {
			try {
				var notes = $( '#wcp_mark_incomplete_notes' );

				if ( 'checked' === $( '#wcp_mark_incomplete_checkbox' ).attr( 'checked' ) ) {
					notes.attr( 'required', true );
				} else {
					notes.attr( 'required', false );
				}
			} catch ( exception ) {
				wcb.log( exception );
			}
		}
	};

	app.init();
} );
