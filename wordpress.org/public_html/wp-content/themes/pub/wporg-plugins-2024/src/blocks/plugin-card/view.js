/**
 * Binds click events to navigate on plugin card click.    
 */
document.addEventListener( 'DOMContentLoaded', function() {
	var cards = document.querySelectorAll( '.plugin-cards li' );

	if ( cards ) {
		cards.forEach( function( card ) {
			card.addEventListener( 'click', function() {
				var selectedText = window.getSelection().toString();

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
