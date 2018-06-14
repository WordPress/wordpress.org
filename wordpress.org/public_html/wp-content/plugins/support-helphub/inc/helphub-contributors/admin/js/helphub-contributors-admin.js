/*jslint node: true */
jQuery( document ).ready( function( $ ) {
	'use strict';

	// ON DOCUMENT READY
	$( document ).ready( function() {

		$( '#helphub-contributors' ).select2( {
			tags: true
		} );

	} );
} );
