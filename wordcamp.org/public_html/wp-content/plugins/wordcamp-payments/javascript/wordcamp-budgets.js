jQuery( document ).ready( function( $ ) {
	'use strict';

	var app = window.WordCampBudgets = {
		/**
		 * Main entry point
		 */
		init : function () {
			try {
				app.registerEventHandlers();
			} catch ( exception ) {
				app.log( exception );
			}
		},

		/**
		 * Registers event handlers
		 */
		registerEventHandlers : function() {
			var needsIntermediaryBank = $( '#needs_intermediary_bank' );

			needsIntermediaryBank.change( app.toggleIntermediaryBankFields );
			needsIntermediaryBank.trigger( 'change' ); // Set the initial state

			$( '#wcb-save-draft' ).click( app.makeFieldsOptional      );
			$( '#wcb-update'     ).click( app.maybeMakeFieldsOptional );
		},

		/**
		 * Make all required fields optional under certain circumstances.
		 */
		maybeMakeFieldsOptional : function() {
			var status = $( '#wcb_status' ).val();

			if ( 'draft' === status || 'wcb-incomplete' === status ) {
				app.makeFieldsOptional();

				if ( 'wcb-incomplete' === status ) {
					$( '#wcp_mark_incomplete_notes' ).prop( 'required', true );
				}
			}
		},

		/**
		 * Make all required input field optional.
		 *
		 * This is used when saving drafts, setting posts to the Incomplete status, etc. Otherwise the user would
		 * have to potentially fill out dozens of fields when they're not ready to.
		 *
		 * @param {object} event
		 */
		makeFieldsOptional : function( event ) {
			$( '#poststuff' ).find( ':input[required]' ).each( function( fieldIndex, inputField ) {
				$( inputField ).prop( 'required', false );
			} );

			app.checkRadioButtons();
		},

		/**
		 * Toggle the payment method fields based on which method is selected
		 *
		 * @param {object} event
		 */
		togglePaymentMethodFields : function( event ) {
			var active_fields_container = '#' + $( this ).attr( 'id' ) + '_fields';
			var payment_method_fields   = '.payment_method_fields',
				optionalFields          = [ 'needs_intermediary_bank' ];

			$( payment_method_fields   ).removeClass( 'active' );
			$( payment_method_fields   ).addClass( 'hidden' );

			$( payment_method_fields ).each( function( containerIndex, fieldContainer ) {
				$( fieldContainer ).find( ':input' ).each( function( fieldIndex, inputField ) {
					$( inputField ).prop( 'required', false );
				} );
			} );

			$( active_fields_container ).removeClass( 'hidden' );
			$( active_fields_container ).addClass( 'active' );

			$( active_fields_container ).find( ':input' ).each( function( index, field ) {
				if ( $.inArray( $( field ).prop( 'id' ), optionalFields ) > -1 ) {
					return;
				}

				$( field ).prop( 'required', true );
			} );

			/*
			 * Also toggle intermediary bank fields, because otherwise switching to Wire from another method
			 * wouldn't remove `required` attributes.
			 */
			app.toggleIntermediaryBankFields( event );

			// todo make the transition smoother

			app.checkRadioButtons();
		},

		/*
		 * Make sure all radio buttons have values in Chrome
		 *
		 * This is only to work around bug #596138
		 *
		 * @see  https://bugs.chromium.org/p/chromium/issues/detail?id=596138
		 * @todo remove this after Chrome 51 is adopted by most users
		 */
		checkRadioButtons : function() {
			var checkedPaymentMethods = $( 'input[name="payment_method"]:checked' );

			if ( ! window.hasOwnProperty( 'chrome' ) ) {
				return;
			}

			if ( 'Direct Deposit' != checkedPaymentMethods.val() ) {
				if ( 0 === $( 'input[name="ach_account_type"]:checked' ).length ) {
					$( '#ach_account_type_company' ).prop( 'checked', true );
				}
			}

			if ( 0 === checkedPaymentMethods.length ) {
				$( '#payment_method_direct_deposit' ).prop( 'checked', true );
			}
		},

		/**
		 * Toggle the fields for a wire payment intermediary bank
		 *
		 * @param {object} event
		 */
		toggleIntermediaryBankFields : function( event ) {
			try {
				var isWirePayment             = $( '#payment_method_wire'      ).prop( 'checked' ),
					needsIntermediaryBank     = $( '#needs_intermediary_bank'  ).prop( 'checked' ),
					intermediaryBankFieldRows = $( '#intermediary_bank_fields' ).find( 'tr' );

				$( intermediaryBankFieldRows ).each( function( index, row ) {
					if ( isWirePayment && needsIntermediaryBank ) {
						$( row ).removeClass( 'hidden' );
						$( row ).find( ':input' ).prop( 'required', true );
					} else {
						$( row ).addClass( 'hidden' );
						$( row ).find( ':input' ).prop( 'required', false );
					}
				} );
			} catch( exception ) {
				app.log( exception );
			}
		},

		/**
		 * Set the default payment method based on the currency
		 *
		 * Don't override any existing payment method choices.
		 *
		 * @param {object} event
		 */
		setDefaultPaymentMethod : function ( event ) {
			var newCurrency           = $( this ).find( 'option:selected' ).val(),
			    selectedPaymentMethod = $( 'input[name=payment_method]:checked' ).val(),
				newPaymentMethod;

			if ( 'null' === newCurrency.slice( 0, 4 ) || undefined !== selectedPaymentMethod ) {
				return;
			}

			if ( 'USD' == newCurrency ) {
				newPaymentMethod = $( '#payment_method_direct_deposit' );
			} else {
				newPaymentMethod = $( '#payment_method_wire' );
			}

			newPaymentMethod.prop( 'checked', true );
			newPaymentMethod.trigger( 'change' );
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

	app.init();
} );
