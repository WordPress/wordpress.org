/**
 * Confirmation for the commit-subscription link in the [wporg-plugins-developers] shortcode.
 *
 * The prompt is read from the parsed `data-confirm` attribute, so the plugin title
 * it contains is never handed to the JavaScript parser as source text.
 */

( function () {
	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target || ! event.target.closest ) {
			return;
		}

		var link = event.target.closest( 'a.plugin-commit-subscribe' );

		if ( ! link ) {
			return;
		}

		if ( ! window.confirm( link.dataset.confirm ) ) {
			event.preventDefault();
		}
	} );
} )();
