/**
 * Refreshes the FreeScout Import page while an import is running, so its progress shows.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	const seconds = parseInt(
		$( '[data-wporgfreescoutimport-refresh]' ).attr(
			'data-wporgfreescoutimport-refresh'
		),
		10
	);

	if ( seconds > 0 ) {
		window.setTimeout( function () {
			window.location.reload();
		}, seconds * 1000 );
	}
} )( jQuery );
