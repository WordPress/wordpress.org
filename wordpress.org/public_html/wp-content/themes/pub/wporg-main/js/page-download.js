( function( $ ) {
	$('#download-wordpress').click( function() {
		this.blur();
		$('#after-download').modal();
	} );
	// Move focus into modal
	$('#after-download').on($.modal.OPEN, function() {
		$( this ).focus();
	} );
	// Move focus back to download button
	$('#after-download').on($.modal.AFTER_CLOSE, function() {
		$('#download-wordpress').focus();
	} );
} )( window.jQuery );

