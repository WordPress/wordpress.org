wp.wcpt = wp.wcpt || {};

wp.wcpt.WcptWordCamp = ( function( $ ) {
	'use strict';

	var self = {};

	/**
	 * Initialize
	 */
	self.initialize = function() {
		$( '.date-field' ).datepicker( {
			dateFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear:  true
		} );
	};

	$( document ).ready( function( $ ) {
		self.initialize();
	} );

	return self;

} ( jQuery ) );
