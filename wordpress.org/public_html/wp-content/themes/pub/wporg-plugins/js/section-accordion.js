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

			$( '.read-more:not(' + sectionId + ',.short-content)' ).removeClass( 'toggled' ).attr( 'aria-expanded', false );
		},
		initial_size: function( selector ) {
			$( selector ).each( function( i, el) {
				if ( $(el).height() / el.scrollHeight > 0.8 || el.id == 'screenshots' ) {
					// Force the section to expand, and hide its button
					$(el).toggleClass( 'toggled' ).addClass('short-content').attr( 'aria-expanded', true );
					$( '.section-toggle[aria-controls="' + el.id + '"]' ).hide();
				} else {
					// Contract the section and make sure its button is visible
					$(el).removeClass( 'short-content' ).attr( 'aria-expanded', false );
					$( '.section-toggle[aria-controls="' + el.id + '"]' ).show();
				}
			} );
		}
	};

	$( function() {
		if ( document.location.hash ) {
			wporg.plugins.toggle( document.location.hash );
		}

		wporg.plugins.initial_size( '.read-more' );

		$( window ).on( 'hashchange', function() {
			wporg.plugins.toggle( document.location.hash );
		} );

		$( '#main' ).on( 'click', '.section-toggle', function( event ) {
			wporg.plugins.toggle( '#' + $( event.target ).attr( 'aria-controls' ) );
		} );
	} );
} )( window.jQuery, window.wporg || {} );
