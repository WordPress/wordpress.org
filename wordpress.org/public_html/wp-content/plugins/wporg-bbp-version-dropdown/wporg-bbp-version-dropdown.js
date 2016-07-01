jQuery( function($) {
	versionChanger = $( '#wp-other-version' ).hide();
	$( '#wp-version' ).on( 'change', function() {
		if ( 'other' == $(this).val() ) {
			versionChanger.show().focus();
		} else {
			versionChanger.hide();
			this.focus();
		}
	} ).change();
} );
