jQuery( document ).ready( function( $ ) {

	$.wordcampPayments = {

		/**
		 * Main entry point
		 */
		init: function () {
			$.wordcampPayments.registerEventHandlers();
			$.wordcampPayments.setupDatePicker();
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			$( '#wcp_payment_details' ).find( 'input[name=payment_method]' ).change( $.wordcampPayments.togglePaymentMethodFields );
			$( '#payment_category' ).change( $.wordcampPayments.toggleOtherCategoryDescription );
			$( '#wcp_files' ).find( 'a.wcp-insert-media' ).click( $.wordcampPayments.showUploadModal );
			$( '#wcp_mark_incomplete_checkbox' ).click( $.wordcampPayments.requireNotes );
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
			if ( 'undefined' == typeof $.wordcampPayments.fileUploadFrame ) {
				// Create the frame
				$.wordcampPayments.fileUploadFrame = wp.media( {
					title: wcpLocalizedStrings.uploadModalTitle,
					multiple: true,
					button: {
						text: wcpLocalizedStrings.uploadModalButton
					}
				} );

				// Add models to the collection for each selected attachment
				$.wordcampPayments.fileUploadFrame.on( 'select', $.wordcampPayments.addSelectedFilesToCollection );
			}

			$.wordcampPayments.fileUploadFrame.open();
			return false;
		},

		/**
		 * Add files selected from the Media Picker to the current collection of files
		 */
		addSelectedFilesToCollection : function() {
			var attachments = $.wordcampPayments.fileUploadFrame.state().get( 'selection' ).toJSON();

			$.each( attachments, function( index, attachment ) {												// todo if selected an existing file, it isn't attached, so after post is saved it wont be in the list
				var newFile = new $.wordcampPayments.AttachedFile( {
					'ID':       attachment.id,
					'filename': attachment.filename,
					'url':      attachment.url
				} );

				$.wordcampPayments.attachedFilesView.collection.add( newFile );
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

	$.wordcampPayments.init();
} );
