( function( $ ){
	var currentHash = window.location.hash.replace( /[^a-z0-9-#]/gi, '' );

	$( function() {
		$( '.locale-filters' ).on( 'click', '.i18n-filter', function() {
			$( '.current-filter' ).removeClass( 'current-filter' );
			$( '.translators-info' )[0].className = 'translators-info show-' + $( this ).data( 'filter' );
			$( this ).addClass( 'current-filter' );
		});

		if ( currentHash ) {
			$( '.locale-filters' ).find( '[href="' + currentHash + '"]' ).trigger( 'click' );
		}
	});

})( jQuery );
