( function( $ ) {
	// Toggle search
	$( '#inner-search .search-section' ).toggle();

	$( '#inner-search #inner-search-icon' ).on( 'click', function() {
		$( '#inner-search .search-section' ).toggle();
	});
} )( jQuery );
