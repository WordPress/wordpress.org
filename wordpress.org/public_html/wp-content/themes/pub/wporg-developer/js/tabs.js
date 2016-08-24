/**
 * Takes care of hiding and displaying sections with tabs
 * 
 * Allow users to switch focus between the aria-selected tab and the content.
 * Change focus between tabs using the left and right arrow keys.
 * Use the TAB key as normal to focus the first element inside the visible tab panel.
 *
 * Html markup needed for a tabbed list
 * 
 * <div class=".tab-container">
 *     <ul class="tablist">
 *         <li><a href="#section-1">Section 1</a></li>
 *         <li><a href="#section-2">Section 2</a></li>
 *     </ul>
 *     <div id="section-1" class="tab-section">Section 1 content</div>
 *     <div id="section-2" class="tab-section">Section 2 content</div>
 * </div> 
 */


( function( $ ) {

	container = $( '.tab-container' );

	if ( container.length ) {

		container.each( function() {

			var tablist = $( this ).find( '.tablist' );
			var tabSections = $( this ).find( '.tab-section' );

			if ( tablist.length || tabSections.length ) {
				var tabs = tablist.find( 'a' );

				if ( ( 1 < tabs.length ) && ( tabs.length === tabSections.length ) ) {
					setupTabs( tablist, tabs, tabSections );
					tabEvents( tablist, tabs, tabSections );
				}
			}
		} );
	}

	function setupTabs( tablist, tabs, tabSections ) {

		tablist.attr( 'role', 'tablist' );
		tablist.find( 'li' ).attr( 'role', 'presentation' );

		tabs.attr( {
			'role': 'tab',
			'tabindex': '-1'
		} );

		// Make each aria-controls correspond id of targeted section (re href)
		tabs.each( function() {
			$( this ).attr(
				'aria-controls', $( this ).attr( 'href' ).substring( 1 )
			);
		} );

		// Make the first tab selected by default and allow it focus
		tabs.first().attr( {
			'aria-selected': 'true',
			'tabindex': '0'
		} );

		// Add 'tab-section-selected' to first section
		tabSections.first().addClass( 'tab-section-selected' );
		
		// Make each section focusable and give it the tabpanel role
		tabSections.attr( {
			'role': 'tabpanel'
		} );

		// Make first child of each panel focusable programmatically
		tabSections.children().first().attr( {
			'tabindex': '0'
		} );

		// Make all but the first section hidden (ARIA state and display CSS)
		$( tabSections ).not( ":first" ).attr( {
			'aria-hidden': 'true'
		} );
	}

	function tabEvents( tablist, tabs, tabSections ) {

		// Change focus between tabs with arrow keys
		tabs.on( 'keydown', function( e ) {

			// define current, previous and next (possible) tabs
			var original = $( this );
			var prev = $( this ).parents( 'li' ).prev().children( '[role="tab"]' );
			var next = $( this ).parents( 'li' ).next().children( '[role="tab"]' );
			var target;

			// find the direction (prev or next)
			switch ( e.keyCode ) {
				case 37:
					target = prev;
					break;
				case 39:
					target = next;
					break;
				default:
					target = false
					break;
			}

			if ( target.length ) {
				original.attr( {
					'tabindex': '-1',
					'aria-selected': null
				} );
				target.attr( {
					'tabindex': '0',
					'aria-selected': true
				} ).focus();
			}

			// Hide panels
			tabSections.attr( 'aria-hidden', 'true' );

			// Show panel which corresponds to target
			$( '#' + $( document.activeElement ).attr( 'href' ).substring( 1 ) ).attr( 'aria-hidden', null );

			// Toggle 'tab-section-selected' class for tab sections
			tabSections.toggleClass( 'tab-section-selected' );
		} );

		// Handle click on tab to show + focus tabpanel
		tabs.on( 'click', function( e ) {
			e.preventDefault();

			tabs.attr( {
				'tabindex': '-1',
				'aria-selected': null
			} );

			// replace above on clicked tab
			$( this ).attr( {
				'aria-selected': true,
				'tabindex': '0'
			} );

			// Hide panels
			tabSections.attr( 'aria-hidden', 'true' );

			// show corresponding panel
			$( '#' + $( this ).attr( 'href' ).substring( 1 ) ).attr( 'aria-hidden', null );

			// Toggle 'tab-section-selected' class for tab sections
			tabSections.toggleClass( 'tab-section-selected' );
		} );
	}

} )( jQuery );