/* global _gaq */
( function( $ ) {
	$( function() {
		$( '#main' ).on( 'click', '.section-toggle', function( event ) {
			var sectionId = $( event.target ).attr( 'aria-controls' );

			$( '#' + sectionId ).toggleClass( 'toggled' ).attr( 'aria-expanded', function( index, attribute ) {
				var notExpanded = 'false' === attribute;

				if ( notExpanded ) {
					_gaq.push(['_trackPageview', window.location.pathname + sectionId + '/' ]);
				}

				return notExpanded;
			} );

			$( '.read-more:not( #' + sectionId + ')' ).removeClass( 'toggled' ).attr( 'aria-expanded', false );
		} );
	} );
} )( window.jQuery );
