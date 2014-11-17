/**
 * Dynamic functionality for comments as user submitted notes.
 *
 */

( function( $ ) {
	$( '#respond, #add-user-note' ).toggle();
	$( '#add-user-note' ).click( function( e ) {
		e.preventDefault();
		$( '#respond, #add-user-note' ).toggle();

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

	// Add php and js buttons to QuickTags.
	QTags.addButton( 'php', 'php', '[php]', '[/php]' );
	QTags.addButton( 'js', 'js', '[js]', '[/js]' );
	QTags.addButton( 'inline-code', 'inline code', '<code>', '</code>' );

} )( jQuery );
