/**
 * Dynamic functionality for voting on user submitted notes.
 *
 */

( function( $, wp ) {
	$( '#comments' ).on( 'click', 'a.user-note-voting-up, a.user-note-voting-down', function( event ) {
		event.preventDefault();

		var $item = $( this ),
			comment = $item.closest( '.comment' );

		$.post(
			ajaxurl,
			{
				action:   'note_vote',
				comment:  $item.attr( 'data-id' ),
				vote:     $item.attr( 'data-vote' ),
				_wpnonce: $item.parent().attr( 'data-nonce' )
			},
			function( data ) {
				if ( '0' !== data ) {
					$item.closest( '.user-note-voting' ).replaceWith( data );
					wp.a11y.speak( $( '.user-note-voting-count', comment ).text() );
				}
			},
			'text'
		);

		return false;
	} );
} )( window.jQuery, window.wp );
