( function( $ ) {
	$( document ).ready( function() {
		// Only do this once
		if ( $( 'body' ).hasClass( 'developer-js-loaded' ) ) {
			return;
		}
		$( 'body' ).addClass( 'developer-js-loaded' );



		$( '#secondary-toggle, #primary-modal' ).click( function () {

			$( 'body' ).toggleClass( 'responsive-show' );
			
		} );
		
		function moveNavIntoSidebar() {
			var $menu = $( 'nav#site-navigation' );
			if ( ( $menu.length ) && ( $menu.parents( 'header#masthead' ).length ) ) {
				$menu.prependTo( '#secondary-content' );
				$menu.wrap( '<aside class="widget" id="o2-responsive-nav"></aside>' );
			}
		}

		function moveNavOutOfSidebar() {
			var $menu = $( 'nav#site-navigation' );
			if ( ( $menu.length ) && ( 0 === $menu.parents( 'header#masthead' ).length ) ) {
				$( 'nav#site-navigation' ).appendTo( 'header#masthead' );
				$( '#o2-responsive-nav' ).remove();
			}
		}

		if ( 'undefined' != typeof enquire ) {
			// "Tablet" max-width also defined in inc/scss/partials/ui/_responsive.scss
			enquire.register("screen and (max-width:876px)", {
				match: function() {
					moveNavIntoSidebar();
				},

				unmatch: function() {
					moveNavOutOfSidebar();
				}
			} );

		}
	} );
} )( jQuery );

