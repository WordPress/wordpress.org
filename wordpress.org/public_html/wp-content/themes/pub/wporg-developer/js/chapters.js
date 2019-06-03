// Mobile Subnav open/close
jQuery( document ).ready( function () {

	jQuery( document ).on( 'click', '.code-tab', function ( e ) {
		var $tab = jQuery( e.target );
		if ( $tab.hasClass( 'is-active' ) ) {
			return;
		}

		var lang = $tab.text();

		$tab.parent().find( '.is-active, .' + lang ).toggleClass( 'is-active' );
	} );

	var tocContainer = jQuery( 'div[class*="-table-of-contents-container"]' ).first();

	if ( 0 === tocContainer.length ) {
		return;
	}

	// Add our expandable button
	tocContainer.find( '> ul .menu-item-has-children > a' )
		.wrap( '<div class="expandable"></div>' )
		.after( '<button class="dashicons dashicons-arrow-down-alt2" aria-expanded="false"></button>' );

	// Invisibly hide all of the submenus
	jQuery( '.menu-item-has-children > ul ul' ).hide();

	// Open the current menu
	tocContainer.find( '.current-menu-item a' ).first()
		.addClass( 'active' )
		.parents( '.menu-item-has-children' )
		.toggleClass( 'open' )
		.find( '> div > .dashicons' )
		.attr( 'aria-expanded', true );

	// Open the current submenu
	$secondary_menu = tocContainer.find( '.current-menu-item > ul' );
	if ( $secondary_menu.length ) {
		$secondary_menu.show();
	} else {
		tocContainer.find( '.current-menu-item' ).parents( 'ul' ).show();
	}
	// Or if wrapped in a div.expandable
	jQuery( '.menu-item-has-children > div > .dashicons' ).click( function () {
		var menuToggle = jQuery( this ).closest( '.menu-item-has-children' );

		jQuery( this ).parent().siblings( '.sub-menu' ).length
			? jQuery( this ).parent().siblings( '.sub-menu' ).slideToggle()
			: jQuery( this ).parent().siblings( '.children' ).slideToggle()

		menuToggle.toggleClass( 'open' );
		jQuery( this ).attr( 'aria-expanded', menuToggle.hasClass( 'open' ) );
	} );
} );

