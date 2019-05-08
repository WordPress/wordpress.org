( function( $ ){
	var $html = $( 'html' );
	var $document = $( document );

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
				.on( 'click', 'button.button-menu__toggle', toggleLinkMenu );
		}
	})( $gp.editor.install_hooks );

})( jQuery );
