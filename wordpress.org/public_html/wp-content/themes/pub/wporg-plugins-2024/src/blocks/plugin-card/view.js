/**
 * Binds click events to navigate on plugin card click.    
 */
document.addEventListener( 'DOMContentLoaded', function() {
	var cards = document.querySelectorAll( '.plugin-cards li' );

	if ( cards ) {
		cards.forEach( function( card ) {
			card.addEventListener( 'click', function( event ) {
				var selectedText = window.getSelection().toString();

				// Keep regular anchor tag function
				if ( 'a' === event.target.tagName.toLowerCase() ) {
					return;
				}

				// If they are selecting text, let's not navigate.
				if ( '' !== selectedText ) {
					return;
				}

				var anchorTag = card.querySelector( 'a' );
				if ( anchorTag ) {
					var link = anchorTag.getAttribute( 'href' );
					window.location.href = link;
				}
			} );
		} )
	}
} );
