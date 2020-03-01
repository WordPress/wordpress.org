( function( $ ){
	$( function() {
		$( document.body ).on( 'click', '.i18n-filter', function( event ) {
			event.preventDefault();

			var $el = $( this );
			var filter = $el.data( 'filter' ).replace( /[^a-z0-9-#]/gi, '' );
			window.location.hash = '#' + filter;

			$( '.current-filter' ).removeClass( 'current-filter' );
			$( '.translators-info' )[0].className = 'translators-info show-' + filter;
			$el.addClass( 'current-filter' );
		});
	});

	jQuery( document ).one( 'ready.o2', function() {
		var currentHash = window.location.hash.replace( /[^a-z0-9-#]/gi, '' );
		if ( currentHash ) {
			$( '.locale-filters' ).find( '[href="' + currentHash + '"]' ).trigger( 'click' );
		}
	} );
})( jQuery );
