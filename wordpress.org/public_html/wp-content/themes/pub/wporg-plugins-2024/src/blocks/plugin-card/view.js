/**
 * Binds click events to navigate on plugin card click.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	const cards = document.querySelectorAll( '.plugin-cards li' );

	if ( cards ) {
		cards.forEach( function ( card ) {
			card.addEventListener( 'click', function ( event ) {
				// Keep regular anchor tag function
				if ( 'a' === event.target.tagName.toLowerCase() ) {
					return;
				}

				// If they are selecting text, let's not navigate.
				if (
					'' !==
					card.ownerDocument.defaultView.getSelection().toString()
				) {
					return;
				}

				const anchorTag = card.querySelector( 'a' );
				if ( anchorTag ) {
					const link = anchorTag.getAttribute( 'href' );
					window.location.href = link;
				}
			} );
		} );
	}
} );
