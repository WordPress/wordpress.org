jQuery( document ).ready( function( $ ) {

	$.paymentRequests = {

		/**
		 * Main entry point
		 */
		init: function () {
			$.paymentRequests.registerEventHandlers();
			$.paymentRequests.setupDatePicker();
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			$( '#wcp_payment_details' ).find( 'input[name=payment_method]' ).change( $.paymentRequests.togglePaymentMethodFields );
			$( '#payment_category' ).change( $.paymentRequests.toggleOtherCategoryDescription );
			$( '#wcp_files' ).find( 'a.wcp-insert-media' ).click( $.paymentRequests.showUploadModal );
			$( '#wcp_mark_incomplete_checkbox' ).click( $.paymentRequests.requireNotes );
		},

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
		 * Example event handler
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
		 * Initialize Core's Media Picker
		 *
		 * @param {object} event
		 */
		showUploadModal : function( event ) {
			if ( 'undefined' == typeof $.paymentRequests.fileUploadFrame ) {
				// Create the frame
				$.paymentRequests.fileUploadFrame = wp.media( {
					title: wcpLocalizedStrings.uploadModalTitle,
					multiple: true,
					button: {
						text: wcpLocalizedStrings.uploadModalButton
					}
				} );

				// Add models to the collection for each selected attachment
				$.paymentRequests.fileUploadFrame.on( 'select', $.paymentRequests.addSelectedFilesToCollection );
			}

			$.paymentRequests.fileUploadFrame.open();
			return false;
		},

		/**
		 * Add files selected from the Media Picker to the current collection of files
		 */
		addSelectedFilesToCollection : function() {
			var attachments = $.paymentRequests.fileUploadFrame.state().get( 'selection' ).toJSON();

			$.each( attachments, function( index, attachment ) {
				var newFile = new $.paymentRequests.AttachedFile( {
					'ID':          attachment.id,
					'post_parent': attachment.uploadedTo,
					'filename':    attachment.filename,
					'url':         attachment.url
				} );

				$.paymentRequests.attachedFilesView.collection.add( newFile );
			} );
		},

		/**
		 * Fallback to the jQueryUI datepicker if the browser doesn't support <input type="date">
		 */
		setupDatePicker : function() {
			var browserTest = document.createElement( 'input' );
			browserTest.setAttribute( 'type', 'date' );

			if ( 'text' === browserTest.type ) {
				$( '#wcp_general_info' ).find( 'input[type=date]' ).not( '[readonly="readonly"]' ).datepicker( {
					dateFormat : 'yy-mm-dd',
					changeMonth: true,
					changeYear : true
				} );
			}
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

	$.paymentRequests.init();
} );
