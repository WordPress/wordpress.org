/* global _gaq */
( function( $, wporg ) {
	wporg.plugins = {
		toggle: function( section ) {
			var sectionId = '#' + section,
				$section = $( sectionId ),
				$button = $( 'button.section-toggle[aria-controls="' + section + '"]' );

			$section.toggleClass( 'toggled' );

			$( '.read-more:not(' + sectionId + ',.short-content)' ).removeClass( 'toggled' )
				.next( '.section-toggle' ).attr( 'aria-expanded', false ).text( $button.data( 'read-more' ) );

			$button.text(
				$section.hasClass( 'toggled' ) ? $button.data( 'show-less' ) : $button.data( 'read-more' )
			).attr( 'aria-expanded', function( index, attribute ) {
				var notExpanded = 'false' === attribute;

				if ( notExpanded ) {
					_gaq.push([ '_trackPageview', window.location.pathname + sectionId + '/' ]);
				}

				return notExpanded;
			} );
		},
		initial_size: function( section ) {
			$( section ).each( function( index, element ) {
				var $section = $( element ),
					$button = $( '.section-toggle[aria-controls="' + element.id + '"]' );

				if ( $section.height() / element.scrollHeight > 0.8 ) {
					// Force the section to expand, and hide its button.
					$section.toggleClass( 'toggled' ).addClass( 'short-content' );
					$button.hide();
				} else {
					// If the description starts with an embed/video, set the min-height to include it.
					if ( 'description' === element.id && $section.children().next( 'p, div' ).first().find( 'video, iframe' ) ) {
						var height = $section.children().next( 'p,div' ).first().outerHeight( true ) /* embed */ + $section.children().first().outerHeight( true ) /* h2 */;

						if ( height > parseInt( $section.css( 'max-height' ) ) ) {
							$section.css( 'min-height', height + 'px' );
						}
					}

					// Contract the section and make sure its button is visible.
					$section.removeClass( 'short-content' );
					$button.show();
				}
			} );
		}
	};

	$( function() {
		// Must always run first, else expand/contract buttons will get hidden incorrectly.
		wporg.plugins.initial_size( '.read-more' );

		if ( document.location.hash ) {
			wporg.plugins.toggle( document.location.hash.substr(1) );
		}

		$( window ).on( 'hashchange', function() {
			wporg.plugins.toggle( document.location.hash.substr(1) );
		} );

		$( '#main' ).on( 'click', '.section-toggle', function( event ) {
			wporg.plugins.toggle( $( event.target ).attr( 'aria-controls' ) );
		} );
	} );
} )( window.jQuery, window.wporg || {} );
