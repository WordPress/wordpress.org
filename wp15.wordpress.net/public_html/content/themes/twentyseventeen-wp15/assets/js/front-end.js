
( function( $ ) {

	'use strict';

	var app = {
		$body: $( 'body' ),

		classValue: 'site-navigation-fixed',

		init: function() {
			var observer;

			app.$nav          = app.$body.find( '.navigation-top' );
			app.$navContainer = app.$body.find( '.navigation-top-container' );

			observer = new MutationObserver( app.observerCallback );

			if ( app.$navContainer.length ) {
				observer.observe( app.$nav.get(0), {
					attributes: true,
					attributeFilter: [ 'class' ]
				} );
			}
		},

		observerCallback: function( events ) {
			$.each( events, function ( i, event ) {
				var $target = $( event.target );

				if ( $target.hasClass( app.classValue ) ) {
					app.$navContainer.addClass( app.classValue );
				} else {
					app.$navContainer.removeClass( app.classValue );
				}
			} );
		}
	};

	$( document ).ready( function() {
		app.init();
	} );

} )( jQuery );
