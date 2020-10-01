( function ( $ ) {
	$( 'document' ).ready( function () {
		toggle_wpsc_mobile_menu = function () {
			if ( !$( '.leftsidebar' ).hasClass( 'wpscMobileMenuSlideIn' ) ) {
				$( '.leftsidebar' ).animate( {
					left: "+=209"
				} );
				$( '.leftsidebar' ).addClass( 'wpscMobileMenuSlideIn' );
			} else {
				$( '.leftsidebar' ).animate( {
					left: "-=209"
				} );
				$( '.leftsidebar' ).removeClass( 'wpscMobileMenuSlideIn' );
			}
		};
	} );
} )( jQuery );
