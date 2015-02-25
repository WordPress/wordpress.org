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

	// Register keypress events for shortcuts.
	$( 'body' ).keypress( function(e) {

		var keypress = String.fromCharCode( e.which ).toLowerCase();

		switch ( keypress ) {
			case 's': // Toggle display of search bar (unless currently focused on editable form element)
				if ( e.target.nodeName == 'INPUT' || e.target.nodeName == 'TEXTAREA' || e.target.isContentEditable ) {
					return;
				}

				e.preventDefault();
				toggle_search_bar();
				break;
		}

	});

	// Register keydown event for search bar so 'escape' hides search bar.
	$( '#inner-search .search-section' ).keydown( function(e) {
		if ( 27 == e.which ) {
			toggle_search_bar();
		}
	});

} )( jQuery );
