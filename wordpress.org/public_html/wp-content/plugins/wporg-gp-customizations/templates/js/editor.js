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

	// Override copy function to adopt custom markup.
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
				.on( 'click', 'button.translation-actions__save', $gp.editor.hooks.ok )
				.on( 'click', 'button.translation-actions__help', openHelpModal )
				.on( 'click', 'button.translation-actions__ltr', switchTextDirection )
				.on( 'click', 'button.translation-actions__rtl', switchTextDirection )
				.on( 'focus', 'textarea', textareaAutosize )
				.on( 'click', 'summary', toggleDetails )
				.on( 'click', 'button.button-menu__toggle', toggleLinkMenu );
		}
	})( $gp.editor.install_hooks );

})( jQuery );
