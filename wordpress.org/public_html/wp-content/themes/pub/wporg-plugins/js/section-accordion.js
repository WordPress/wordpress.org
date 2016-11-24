/* global _gaq */
( function( $, wporg ) {
	wporg.plugins = {
		toggle: function( section ) {
			var sectionId = '#' + section,
				$section = $( sectionId ),
				$button = $('button.section-toggle[aria-controls="' + section + '"]');

			$section.toggleClass( 'toggled' ).attr( 'aria-expanded', function( index, attribute ) {
				var notExpanded = 'false' === attribute;

				if ( notExpanded ) {
					_gaq.push(['_trackPageview', window.location.pathname + sectionId + '/' ]);
				}

				return notExpanded;
			} );

			$( '.read-more:not(' + sectionId + ',.short-content)' ).removeClass( 'toggled' ).attr( 'aria-expanded', false );

			$button.text(
				$section.hasClass( 'toggled' ) ?
					$button.data('show-less') :
					$button.data('read-more')
			);
		},
		initial_size: function( selector ) {
			$( selector ).each( function( i, el) {
				var $el = $(el),
					$section_toggle = $( '.section-toggle[aria-controls="' + el.id + '"]' );

				if ( $el.height() / el.scrollHeight > 0.8 ) {
					// Force the section to expand, and hide its button
					$el.toggleClass( 'toggled' ).addClass('short-content').attr( 'aria-expanded', true );
					$section_toggle.hide();
				} else {
					// If the description starts with an embed/video, set the min-height to include it.
					if ( 'description' == el.id && $el.children().next('p,div').first().find('video,iframe') ) {
						var height = $el.children().next('p,div').first().outerHeight(true) /* embed */ + $el.children().first().outerHeight(true) /* h2 */;

						if ( height > parseInt($el.css( 'max-height' )) ) {
							$el.css( 'min-height', height + "px" );
						}
					}

					// Contract the section and make sure its button is visible
					$el.removeClass( 'short-content' ).attr( 'aria-expanded', false );
					$section_toggle.show();
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
