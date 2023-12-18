/* global $gp */
( function( $ ){
	var $html = $( 'html' );
	var $document = $( document );

	function checkStorage() {
		var test = 'test',
			result = false;

		try {
			window.localStorage.setItem( 'test', test );
			result = window.localStorage.getItem( 'test' ) === test;
			window.localStorage.removeItem( 'test' );
		} catch(e) {}

		hasStorage = result;
		return result;
	}

	var hasStorage = checkStorage();

	// Handle tab view for plural forms.
	function switchPluralTabs() {
		var $tab = $( this );
		if ( $tab.hasClass( 'translation-form-list__tab--active' ) ) {
			return;
		}

		var $translationWrapper = $gp.editor.current.find( '.translation-wrapper' );
		var $formWrapper = $translationWrapper.find( '.translation-form-wrapper' );
		var $tabList = $formWrapper.find( '.translation-form-list' );
		var $tabs = $translationWrapper.find( '.textareas' );

		$tabList.find( '.translation-form-list__tab--active' ).removeClass( 'translation-form-list__tab--active' );
		$tab.addClass( 'translation-form-list__tab--active' );

		$tabs.removeClass( 'active' );

		var index = $tab.data( 'plural-index' );
		$tabs.filter( '[data-plural-index="' + index + '"]').addClass( 'active' );
	}

	// Open menu for contextual links.
	function toggleLinkMenu() {
		var $toggle = $( this );
		var $menu = $toggle.parent( '.button-menu' );

		$menu.toggleClass( 'active' );
		$document.off( 'click.menu-toggle' );

		if ( $menu.hasClass( 'active' ) ) {
			$document.on( 'click.menu-toggle', function( event ) {
				if ( ! $menu.is( event.target ) && 0 === $menu.has( event.target ).length ) {
					$menu.removeClass( 'active' );
					$document.off( 'click.menu-toggle' );
				}
			} );
		}
	}

	// Automatically adjust textarea height to fit text.
	function textareaAutosize() {
		var $textarea = $( this );
		if ( $textarea.hasClass( 'autosize' ) ) {
			return;
		}

		$textarea.addClass( 'autosize' );

		autosize( $textarea );
	}


	//Prefilter ajax requests to add translation_source to the request.
	$.ajaxPrefilter( function ( options ) {
		let data = Object.fromEntries( new URLSearchParams( options.data ) );

		if ( 'POST' === options.type && $gp_editor_options.url === options.url ) {
			options.data += '&translation_source=frontend';

		}
	});

	// Override functions to adopt custom markup.
	$gp.editor.copy = function() {
		var $activeTextarea = $gp.editor.current.find( '.textareas.active textarea' );
		if ( ! $activeTextarea.length ) {
			return;
		}

		var chunks = $activeTextarea.attr( 'id' ).split( '_' );
		var originalIndex = parseInt( chunks[ chunks.length - 1 ], 10 );

		var $original;
		if ( 0 === originalIndex ) {
			$original = $gp.editor.current.find( '.source-string__singular .original-raw' );
		} else {
			$original = $gp.editor.current.find( '.source-string__plural .original-raw' );
		}

		if ( ! $original.length ) {
			return;
		}

		var originalText = $original.text();
		if ( ! originalText ) {
			return;
		}

		$activeTextarea.val( originalText ).focus();

		// Trigger input event for autosize().
		var event = new Event( 'input' );
		$activeTextarea[0].dispatchEvent( event );
	};
	$gp.editor.tab = function() {
		var text_area = $gp.editor.current.find( '.textareas.active textarea' );
		if ( ! text_area.length ) {
			return;
		}

		var cursorPos = text_area.prop( 'selectionStart' );
		var v = text_area.val();
		var textBefore = v.substring( 0,  cursorPos );
		var textAfter  = v.substring( cursorPos, v.length );

		text_area.val( textBefore + '\t' + textAfter );

		text_area.focus();
		text_area[0].selectionEnd = cursorPos + 1;
	},
	$gp.editor.newline = function() {
		var text_area = $gp.editor.current.find( '.textareas.active textarea' );
		if ( ! text_area.length ) {
			return;
		}

		var cursorPos = text_area.prop( 'selectionStart' );
		var v = text_area.val();
		var textBefore = v.substring( 0,  cursorPos );
		var textAfter  = v.substring( cursorPos, v.length );

		text_area.val( textBefore + '\n' + textAfter );

		text_area.focus();
		text_area[0].selectionEnd = cursorPos + 1;
	};

	function switchTextDirection() {
		var direction = $( this ).is( '.translation-actions__ltr') ? 'ltr' : 'rtl';

		var $wrapper = $gp.editor.current.find( '.translation-wrapper' );
		if ( ! $wrapper.length ) {
			return;
		}

		$wrapper.removeClass( 'textarea-direction-rtl textarea-direction-ltr' );
		$wrapper.addClass( 'textarea-direction-' + direction );
	}

	// Open the modal for translation help.
	function openHelpModal() {
		var $modal = $( '#wporg-translation-help-modal' );
		var $closeButton = $modal.find( '.wporg-translate-modal__close' );

		$html.addClass( 'modal-open' );
		$modal.addClass( 'wporg-translate-modal--open' );
		$closeButton.focus();

		$document.on( 'keydown.modal', function( event ) {
			if ( 27 !== event.which ) { // ESC key.
				return;
			}

			$modal.removeClass( 'wporg-translate-modal--open' );
			$html.removeClass( 'modal-open' );
			$document.off( 'keydown.modal' );
		} );

		$closeButton.one( 'click', function() {
			$modal.removeClass( 'wporg-translate-modal--open' );
			$html.removeClass( 'modal-open' );
			$document.off( 'keydown.modal' );
		} );
	}

	$gp.editor.keydown  = ( function( original ) {
		return function( event ) {
			// Shift-Enter = Save.
			if ( 13 === event.keyCode && event.shiftKey ) {
				var $textarea = $( event.target );

				if ( ! $textarea.val().trim() ) {
					$gp.notices.error( 'Translation is empty.' );
					return false;
				}

				// Check plural forms.
				var $textareas = $gp.editor.current.find( '.textareas:not(.active) textarea' );
				var isValid = true;
				$textareas.each( function() {
					if ( ! this.value.trim() ) {
						isValid = false;
					}
				} );

				if ( ! isValid ) {
					$gp.notices.error( 'Translation is empty.' );
					return false;
				}

				$gp.editor.save( $gp.editor.current.find( 'button.translation-actions__save' ) );

			// Ctrl-Enter or Ctrl-Shift-B = Copy original.
			} else if (
				( 13 === event.keyCode && event.ctrlKey ) ||
				( 66 === event.keyCode && event.shiftKey && event.ctrlKey ) )
			{
				var $button = $gp.editor.current.find( 'button.translation-actions__copy' );

				$button.trigger( 'click' );
			} else {
				return original.apply( $gp.editor, arguments );
			}

			return false;
		}
	})( $gp.editor.keydown );

	// Store the open/close state of <details> element in locale storage and apply state when editor is shown.
	var DETAILS_STORE_KEY = 'translate-details-state';
	function updateDetailsState( type, state ) {
		if ( ! hasStorage ) {
			return;
		}

		var store  = window.localStorage.getItem( DETAILS_STORE_KEY );
		var states = store ? JSON.parse( store ) : {};

		states[ type ] = state;

		window.localStorage.setItem( DETAILS_STORE_KEY, JSON.stringify( states ) );
	}

	function toggleDetails( event ) {
		var $el = $( event.target ).closest( 'details' );
		var isClosed = $el.attr( 'open' ) === 'open'; // Gets closed when open attribute was previously set.
		var className = $el.attr( 'class' ).replace( /^(\S*).*/, '$1' );

		updateDetailsState( className, isClosed ? 'close' : 'open' );
	}

	function applyDetailsState() {
		if ( ! hasStorage || ! $gp.editor.current.length ) {
			return;
		}

		var store  = window.localStorage.getItem( DETAILS_STORE_KEY );
		var states = store ? JSON.parse( store ) : {};

		for ( var type in states ) {
			var state = states[ type ];

			if ( 'open' === state ) {
				$gp.editor.current.find( '.' + type ).attr( 'open', 'open' );
			} else {
				$gp.editor.current.find( '.' + type ).removeAttr( 'open' );
			}
		}
		// Click on the last tab opened in the previous row, to show the same tab in the current row.
		$gp.editor.current.find( '.' + states['last-tab-type-open'] ).first().click();
	}

	function changeRightTab( event ) {
		var tab = $( this );
		var tabType = tab.attr( 'class' ).split(' ')[0];
		var tabId = tab.attr( 'data-tab' );
		var divId = tabId.replace( 'tab', 'div' );
		var originalId = tabId.replace( /[^\d-]/g, '' ).replace( /^-+/g, '' );
		changeVisibleTab( tab );
		changeVisibleDiv( divId, originalId );
		updateDetailsState( 'last-tab-type-open', tabType );
		// Avoid to execute the code from the gp-translation-helpers plugin.
		event.stopImmediatePropagation();
	}

	/**
	 * Hides all tabs and show one of them, the last clicked.
	 *
	 * @param {Object} tab The selected tab.
	 */
	function changeVisibleTab( tab ) {
		var tabId = tab.attr( 'data-tab' );
		tab.siblings().removeClass( 'current' );
		tab.parents( '.sidebar-tabs ' ).find( '.helper' ).removeClass( 'current' );
		tab.addClass( 'current' );

		$( '#' + tabId ).addClass( 'current' );
	}


	/**
	 * Hides all divs and show one of them, the last clicked.
	 *
	 * @param {string} tabId      The select tab id.
	 * @param {number} originalId The id of the original string to translate.
	 */
	function changeVisibleDiv( tabId, originalId ) {
		$( '#sidebar-div-meta-' + originalId ).hide();
		$( '#sidebar-div-discussion-' + originalId ).hide();
		$( '#sidebar-div-others-' + originalId ).hide();
		$( '#' + tabId ).show();
	}

	$gp.editor.show = ( function( original ) {
		return function() {
			original.apply( $gp.editor, arguments );

			applyDetailsState();
		}
	})( $gp.editor.show );

	$gp.editor.install_hooks = ( function( original ) {
		return function() {
			original.apply( $gp.editor, arguments );

			$( $gp.editor.table )
				.on( 'click', 'button.translation-form-list__tab', switchPluralTabs )
				.on( 'click', 'button.panel-header-actions__previous', $gp.editor.prev )
				.on( 'click', 'button.panel-header-actions__next', $gp.editor.next )
				.on( 'click', 'button.panel-header-actions__cancel', $gp.editor.hooks.cancel )
				.on( 'click', 'button.translation-actions__copy', $gp.editor.hooks.copy )
				.on( 'click', 'button.translation-actions__insert-tab', $gp.editor.hooks.tab )
				.on( 'click', 'button.translation-actions__save', $gp.editor.hooks.ok )
				.on( 'click', 'button.translation-actions__help', openHelpModal )
				.on( 'click', 'button.translation-actions__ltr', switchTextDirection )
				.on( 'click', 'button.translation-actions__rtl', switchTextDirection )
				.on( 'focus', 'textarea', textareaAutosize )
				.on( 'click', 'summary', toggleDetails )
				.on( 'click', 'button.button-menu__toggle', toggleLinkMenu )
				.on( 'click', '.sidebar-tabs li', changeRightTab );
		}
	})( $gp.editor.install_hooks );

})( jQuery );
