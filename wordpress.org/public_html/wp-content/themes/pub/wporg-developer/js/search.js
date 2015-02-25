( function( $ ) {

	function toggle_search_bar() {
		// The search input field.
		var search_input = $( '#inner-search .search-section .search-field' );

		// Toggle display.
		$( '#inner-search .search-section' ).toggle();

		// Give input field focus if it is now visible, remove focus otherwise.
		if ( search_input.is(':visible') ) {
			search_input.focus();
		} else {
			search_input.blur();
		}
	}

	// Toggle search bar on page load (it's shown by default).
	toggle_search_bar();

	// Toggle search bar when icon is clicked.
	$( '#inner-search #inner-search-icon' ).on( 'click', function() {
		toggle_search_bar();
	});

} )( jQuery );
