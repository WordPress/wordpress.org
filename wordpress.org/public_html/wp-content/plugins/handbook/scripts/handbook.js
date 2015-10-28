/**
 * Enables toggling visibility of all the chapters in the Handbook Chapter widget.
 * 
 * @since ???
 */
var toggleChapters = function () {
	/**
	 * The Handbook Chapter widget title element.
	 *
	 * @type {NodeList}
	 */
	var chapterWidgetHeading = document.querySelectorAll( '.widget_wporg_handbook_pages .widgettitle' );

	/*
	 * Bind to the touch and click events to make it useful on both mobile and desktop.
	 * Since 'click' is the last event in the chain, we use preventDefault() to stop the
	 * touch event from continuing and triggering a "ghost click".
	 *
	 * @link http://www.html5rocks.com/en/mobile/touchandmouse/
	 */
	jQuery( chapterWidgetHeading ).bind( 'touchstart click', function ( e ) {
		e.preventDefault();
		jQuery( this ).next( '.menu-table-of-contents-container' ).toggle();
	} );
};

// Layout changes the widget at 700px, so we don't want to enable toggling above that.
// @todo Check on window resize, rather than just on page loading
jQuery( document ).ready( function() {
	if ( 700 >= parseInt( jQuery( window ).width(), 10 ) ) {
		toggleChapters();
	}
});

