// Mobile Subnav open/close
jQuery(document).ready(function() {

	// Add our expandable button
	jQuery( '.menu-table-of-contents-container > ul > .menu-item-has-children > a' )
		.wrap( '<div class="expandable"></div>' )
		.before( '<span class="dashicons dashicons-arrow-down-alt2"></span>' );

	// Invisibly open all of the submenus
	jQuery( '.menu-item-has-children > ul ul' ).addClass( 'default-open' );

	// Open the current menu
	jQuery( '.current-menu-item a' ).first()
		.addClass( 'active' )
		.parents( '.menu-item-has-children' )
			.toggleClass( 'open' )
			.children( '.children' )
				.slideToggle();

	// Or if wrapped in a div.expandable
	jQuery( '.menu-item-has-children > div > .dashicons' ).click( function() {
		jQuery( this ).parent().siblings( '.children' ).slideToggle();
		jQuery( this ).closest( '.menu-item-has-children' ).toggleClass( 'open' );
	} );
} );

