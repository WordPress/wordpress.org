/* global _gaq */
( function( $, wporg ) {
	wporg.plugins = {
		toggle: function( sectionId ) {
			$( sectionId ).toggleClass( 'toggled' ).attr( 'aria-expanded', function( index, attribute ) {
				var notExpanded = 'false' === attribute;

				if ( notExpanded ) {
					_gaq.push(['_trackPageview', window.location.pathname + sectionId + '/' ]);
				}

				return notExpanded;
			} );

			$( '.read-more:not(' + sectionId + ')' ).removeClass( 'toggled' ).attr( 'aria-expanded', false );
		}
	};

	$( function() {
		if ( document.location.hash ) {
			wporg.plugins.toggle( document.location.hash );
		}

		$( window ).on( 'hashchange', function() {
			wporg.plugins.toggle( document.location.hash );
		} );

		$( '#main' ).on( 'click', '.section-toggle', function( event ) {
			wporg.plugins.toggle( '#' + $( event.target ).attr( 'aria-controls' ) );
		} );
	} );
} )( window.jQuery, window.wporg || {} );
