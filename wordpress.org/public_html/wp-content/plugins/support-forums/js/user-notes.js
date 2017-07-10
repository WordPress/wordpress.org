jQuery( document ).ready( function( $ ) {

	$( '.bbp-topic-author, .bbp-reply-author' ).on( 'click', '.wporg-bbp-user-notes-toggle a', function( event ) {
		event.preventDefault();
		$( '#wporg-bbp-user-notes-' + $(this).data( 'post-id' ) ).toggle();
	});

});