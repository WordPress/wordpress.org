/**
 * Theme functions file.
 *
 * Contains handlers for navigation and widget area.
 *
 * This file was copied from TwentySeventeen 1.2 and extended to support two nav menus.
 *
 * @package CampSite_2017
 */

/* global campsiteScreenReaderText */

(function( $ ) {
	var masthead = $( '#masthead' ),
	    mainMenuToggle = masthead.find( '.main-navigation .menu-toggle' ),
	    mainNavContain = masthead.find( '.main-navigation' ),
	    mainNavigation = masthead.find( '.main-navigation > div > ul' ),
	    secondaryMenuToggle = masthead.find( '.secondary-navigation .menu-toggle' ),
	    secondaryNavContain = masthead.find( '.secondary-navigation' ),
	    secondaryNavigation = masthead.find( '.secondary-navigation > div > ul' );

	initNavigation( $( '.main-navigation' ) );
	initNavigation( $( '.secondary-navigation' ) );
	enableMenuToggle( mainNavContain, mainMenuToggle );
	enableMenuToggle( secondaryNavContain, secondaryMenuToggle );
	fixSubmenuOnTouch( mainNavigation, mainMenuToggle );
	fixSubmenuOnTouch( secondaryNavigation, secondaryMenuToggle );

	// Initialize the navigation toggle buttons.
	function initNavigation( container ) {
		// Add dropdown toggle that displays child menu items.
		var dropdownToggle = $( '<button />', { 'class': 'dropdown-toggle', 'aria-expanded': false } )
			.append( campsiteScreenReaderText.icon )
			.append( $( '<span />', { 'class': 'screen-reader-text', text: campsiteScreenReaderText.expand } ) );

		container.find( '.menu-item-has-children > a, .page_item_has_children > a' ).after( dropdownToggle );

		// Set the active submenu dropdown toggle button initial state.
		container.find( '.current-menu-ancestor > button' )
			.addClass( 'toggled-on' )
			.attr( 'aria-expanded', 'true' )
			.find( '.screen-reader-text' )
			.text( campsiteScreenReaderText.collapse );

		// Set the active submenu initial state.
		container.find( '.current-menu-ancestor > .sub-menu' ).addClass( 'toggled-on' );

		container.find( '.dropdown-toggle' ).click( function( e ) {
			var _this = $( this ),
				screenReaderSpan = _this.find( '.screen-reader-text' );

			e.preventDefault();
			_this.toggleClass( 'toggled-on' );
			_this.next( '.children, .sub-menu' ).toggleClass( 'toggled-on' );

			_this.attr( 'aria-expanded', _this.attr( 'aria-expanded' ) === 'false' ? 'true' : 'false' );

			screenReaderSpan.text( screenReaderSpan.text() === campsiteScreenReaderText.expand ? campsiteScreenReaderText.collapse : campsiteScreenReaderText.expand );
		});
	}

	// Enable menuToggle.
	function enableMenuToggle( navContain, menuToggle ) {
		if ( ! menuToggle.length ) {
			return;
		}

		menuToggle.attr( 'aria-expanded', 'false' );

		menuToggle.on( 'click.campsite', function() {
			navContain.toggleClass( 'toggled-on' );

			$( this ).attr( 'aria-expanded', navContain.hasClass( 'toggled-on' ) );
		});
	}

	// Fix sub-menus for touch devices and better focus for hidden submenu items for accessibility.
	function fixSubmenuOnTouch( navigation, menuToggle ) {
		if ( ! navigation.length || ! navigation.children().length ) {
			return;
		}

		// Toggle `focus` class to allow submenu access on tablets.
		function toggleFocusClassTouchScreen( menuToggle ) {
			if ( 'none' === menuToggle.css( 'display' ) ) {
				$( document.body ).on( 'touchstart.campsite', function( e ) {
					if ( ! $( e.target ).closest( '.main-navigation li' ).length ) {
						$( '.main-navigation li' ).removeClass( 'focus' );
					}
				});

				navigation.find( '.menu-item-has-children > a, .page_item_has_children > a' )
					.on( 'touchstart.campsite', function( e ) {
						var el = $( this ).parent( 'li' );

						if ( ! el.hasClass( 'focus' ) ) {
							e.preventDefault();
							el.toggleClass( 'focus' );
							el.siblings( '.focus' ).removeClass( 'focus' );
						}
					});
			} else {
				navigation.find( '.menu-item-has-children > a, .page_item_has_children > a' ).unbind( 'touchstart.campsite' );
			}
		}

		if ( 'ontouchstart' in window ) {
			$( window ).on( 'resize.campsite', toggleFocusClassTouchScreen( menuToggle ) );
			toggleFocusClassTouchScreen( menuToggle );
		}

		navigation.find( 'a' ).on( 'focus.campsite blur.campsite', function() {
			$( this ).parents( '.menu-item, .page_item' ).toggleClass( 'focus' );
		});
	}
})( jQuery );
