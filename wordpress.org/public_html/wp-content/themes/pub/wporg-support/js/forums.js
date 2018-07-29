( function( $ ) {

	$( '#new-post' ).submit(function() {
		$( '[type="submit"]', $(this) ).prop( 'disabled', 'disabled' );
	});

} )( window.jQuery );
