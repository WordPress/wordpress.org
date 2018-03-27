
( function( window, $ ) {

	'use strict';

	var WP15LocaleSwitcher = window.WP15LocaleSwitcher || {},
		app;

	app = $.extend( WP15LocaleSwitcher, {
		$switcher: $(),

		init: function() {
			app.$switcher = $( '#wp15-locale-switcher' );

			app.$switcher.select2( {
				language: app.locale,
				dir: app.dir
			} );

			app.$switcher.on( 'change', function() {
				$(this).parents( 'form' ).submit();
			} );
		}
	} );

	$( document ).ready( function() {
		app.init();
	} );

} )( window, jQuery );
