( function( $ ) {
	if ( $( '#new-post' ).length ) {
		var requireConfirmClose = false;

		$( '#new-post' ).submit( function() {
			$( '[type="submit"]', $( this ) ).prop( 'disabled', 'disabled' );

			requireConfirmClose = false;
		} );

		$( '#new-post' ).one( 'input', 'input[type=text], textarea', function() {
			requireConfirmClose = true;
		} );

		window.addEventListener( 'beforeunload', function( e ) {
			if ( requireConfirmClose ) {
				e.preventDefault();

				// Chrome requires returnValue to be set.
				e.returnValue = '';
			}
		} );
	}
}( window.jQuery ) );
