/* global wpCookies */
( function( window, $, wpCookies ) {

	'use strict';

	var WP15LocaleSwitcher = window.WP15LocaleSwitcher || {},
		app;

	app = $.extend( WP15LocaleSwitcher, {
		$switcher: $(),

		$notice: $(),

		init: function() {
			app.$switcher = $( '#wp15-locale-switcher' );
			app.$notice   = $( '.wp15-locale-notice' );

			app.$switcher.select2( {
				language: app.locale,
				dir: app.dir
			} );

			app.$switcher.on( 'change', function() {
				$(this).parents( 'form' ).submit();
			} );

			app.$notice.on( 'click', '.wp15-locale-notice-dismiss', function( event ) {
				event.preventDefault();
				app.dismissNotice();
			} );
		},

		dismissNotice: function() {
			app.$notice.fadeTo( 100, 0, function() {
				app.$notice.slideUp( 100, function() {
					app.$notice.remove();
				});
			});

			wpCookies.set(
				'wp15-locale-notice-dismissed',
				true,
				app.cookie.expires,
				app.cookie.cpath,
				app.cookie.domain,
				app.cookie.secure
			);
		}
	} );

	$( document ).ready( function() {
		app.init();
	} );

} )( window, jQuery, wpCookies );
