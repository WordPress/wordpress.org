/**
 * Comments as examples dynamic functionality.
 *
 */

( function( $ ) {
	$( '#respond, #add-example' ).toggle();
	$( '#add-example' ).click( function( e ) {
		e.preventDefault();
		$( '#respond, #add-example' ).toggle();

		if ( pos = $( '#submit' ).position() ) {
			if ( pos.top < $(window).scrollTop() ) {
				// Scroll up
				$( 'html,body' ).animate( {scrollTop:pos.top}, 1000 );
			}
			else if ( pos.top + jQuery("selector").height() > $(window).scrollTop() + (window.innerHeight || document.documentElement.clientHeight) ){
				// Scroll down
				$( 'html,body' ).animate( {scrollTop:pos.top - (window.innerHeight || document.documentElement.clientHeight) + $( '#submit' ).height() + 30}, 1000 );
			}
		}
	} );
} )( jQuery );
