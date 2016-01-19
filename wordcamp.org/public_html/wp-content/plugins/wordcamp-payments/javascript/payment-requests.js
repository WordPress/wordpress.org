jQuery( document ).ready( function( $ ) {

	// todo add try/catch and log

	var wcb = window.WordCampBudgets;
	var app = wcb.PaymentRequests = {
		
		/**
		 * Main entry point
		 */
		init: function () {
			app.registerEventHandlers();
			wcb.attachedFilesView = new wcb.AttachedFilesView();
			wcb.setupDatePicker( '#wcp_general_info' );
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
			var otherCategoryDescription = $( '#row-other-category-explanation' );

			if ( 'other' == $( this ).find( 'option:selected' ).val() ) {
				$( otherCategoryDescription ).removeClass( 'hidden' );
			} else {
				$( otherCategoryDescription ).addClass( 'hidden' );
			}

			// todo make the transition smoother
		},

		/**
		 * Require notes when the request is being marked as incomplete
		 */
		requireNotes : function() {
			var notes = $( '#wcp_mark_incomplete_notes' );

			if ( 'checked' === $( '#wcp_mark_incomplete_checkbox' ).attr( 'checked' ) ) {
				notes.attr( 'required', true );
			} else {
				notes.attr( 'required', false );
			}
		}
	};

	app.init();
} );
