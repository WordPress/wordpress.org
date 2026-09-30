/**
 * Loads the WordPress.org sidebar panels.
 *
 * Panels are fetched after page load, so a slow or failing api.wordpress.org never blocks the conversation view.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	$( function () {
		$( '.wporg-sidebar-panel[data-url]' ).each( function () {
			const $panel = $( this );

			$.getJSON( $panel.data( 'url' ) )
				.done( function ( response ) {
					if ( response && response.html ) {
						// Trusted: rendered by the signed api.wordpress.org endpoints.
						$panel.html( response.html );
					} else {
						$panel.closest( '.conv-sidebar-block' ).remove();
					}
				} )
				.fail( function () {
					$panel.text( 'Could not load this panel.' );
				} );
		} );
	} );
} )( jQuery );
