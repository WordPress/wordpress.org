( function () {
	'use strict';

	var button = document.getElementById( 'bb-menu-icon' );
	var menu = document.getElementById( 'bb-nav' );

	if ( ! button || ! menu ) {
		return;
	}

	button.addEventListener( 'click', function () {
		button.setAttribute( 'aria-expanded', button.getAttribute( 'aria-expanded' ) === 'false' ? 'true' : 'false' );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && button.getAttribute( 'aria-expanded' ) === 'true' ) {
			button.setAttribute( 'aria-expanded', 'false' );
			button.focus();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( button.getAttribute( 'aria-expanded' ) === 'true' && ! button.parentNode.contains( event.target ) ) {
			button.setAttribute( 'aria-expanded', 'false' );
		}
	} );

	window.addEventListener( 'resize', function () {
		if ( window.innerWidth > 782 ) {
			button.setAttribute( 'aria-expanded', 'false' );
		}
	} );
}() );
