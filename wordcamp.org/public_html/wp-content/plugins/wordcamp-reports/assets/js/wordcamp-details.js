
( function( window, $ ) {

	'use strict';

	var WordCampDetails = window.WordCampDetails || {};

	$.extend( WordCampDetails, {
		/**
		 * Initialize the script.
		 */
		init: function() {
			var self = this;

			this.cache = {
				$container: $( '.fields-container' ),
				$control: $()
			};

			self.cache.$control = self.createControl();

			$( document ).ready( function() {
				self.cache.$container.find( 'legend' ).append( self.cache.$control );
			} );
		},

		/**
		 * Create the elements for the checkbox control that will toggle all of the checkboxes in the form.
		 *
		 * @return {jQuery} The object for the control component.
		 */
		createControl: function() {
			var self = this,
				$input, $control;

			$input = $( '<input>' )
				.attr( 'type', 'checkbox' )
			;

			$input.on( 'change', function( event ) {
				var $target = $( event.target );

				self.toggleCheckboxes( $target );
			} );

			$control = $( '<label>' )
				.addClass( 'fields-checkall' )
				.text( 'Check all' )
				.prepend( $input )
			;

			return $control;
		},

		/**
		 * Perform the checking/unchecking of all the checkboxes.
		 *
		 * @param {jQuery} $target The control.
		 */
		toggleCheckboxes: function( $target ) {
			var $container = $target.parents( '.fields-container' ),
				$checkboxes = $container.find( 'input[type="checkbox"]' ).not( ':disabled' );

			if ( $target.is( ':checked' ) ) {
				$checkboxes.prop( 'checked', true );
			} else {
				$checkboxes.prop( 'checked', false );
			}
		}
	} );

	WordCampDetails.init();

} )( window, jQuery );
