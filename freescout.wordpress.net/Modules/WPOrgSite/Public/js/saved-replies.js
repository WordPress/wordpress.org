/**
 * Saved replies from the keyboard. In the reply and note editors, typing / at the start of a line opens the Saved
 * Replies menu instead, and the editor says so while it's empty. In the menu, however it opened, the search has the
 * focus, the arrow keys move between it and the replies, Enter inserts one, and Escape closes it, back to the editor.
 *
 * The menu is the Saved Replies module's, which adds its search once a mailbox has more than 20 replies. Without the
 * module, editors have no menu, and nothing changes.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * The Saved Replies menu, in an editor's toolbar.
	 *
	 * @type {string}
	 */
	const MENU = '.dropdown-saved-replies';

	/**
	 * The menu's search.
	 *
	 * @type {string}
	 */
	const SEARCH = '.sr-li-search input';

	/**
	 * Elements that start a line of their own.
	 *
	 * @type {Set<string>}
	 */
	const BLOCKS = new Set( [
		'ADDRESS',
		'ARTICLE',
		'ASIDE',
		'BLOCKQUOTE',
		'DD',
		'DIV',
		'DL',
		'DT',
		'FIGURE',
		'FOOTER',
		'H1',
		'H2',
		'H3',
		'H4',
		'H5',
		'H6',
		'HEADER',
		'HR',
		'LI',
		'OL',
		'P',
		'PRE',
		'SECTION',
		'TABLE',
		'TD',
		'TH',
		'TR',
		'UL',
	] );

	/**
	 * What the placeholder says, around the key.
	 *
	 * @type {string[]}
	 */
	const PLACEHOLDER = [ 'Type ', '/', ' for saved replies' ];

	/**
	 * Keys that type nothing on their own, like the Shift or AltGr that / takes on some keyboards.
	 *
	 * @type {Set<string>}
	 */
	const MODIFIERS = new Set( [
		'Alt',
		'AltGraph',
		'CapsLock',
		'Control',
		'Dead',
		'Fn',
		'Meta',
		'Shift',
	] );

	/**
	 * Whether the next / types a /: the agent closed the menu the last one opened, with Escape.
	 *
	 * @type {boolean}
	 */
	let literalSlash = false;

	/**
	 * Whether a / opened the menu that's open.
	 *
	 * @type {boolean}
	 */
	let openedBySlash = false;

	/**
	 * The editor an element is in, if it has the Saved Replies menu.
	 *
	 * @param {Element} element Element.
	 * @return {jQuery|null} Editor.
	 */
	function editorOf( element ) {
		const $editor = $( element ).closest( '.note-editor' );

		return $editor.find( MENU ).length ? $editor : null;
	}

	/**
	 * The element Summernote was started on, whose API remembers where the caret was.
	 *
	 * @param {jQuery} $editor Editor.
	 * @return {jQuery} Element, or none.
	 */
	function noteOf( $editor ) {
		const $note = $editor.prev();

		return 'function' === typeof $.fn.summernote &&
			$note.data( 'summernote' )
			? $note
			: $();
	}

	/**
	 * Whether text is only spaces, including the zero-width ones editors leave behind.
	 *
	 * @param {string} text Text.
	 * @return {boolean} Whether it is.
	 */
	function isBlank( text ) {
		return '' === text.replace( /[\s\u200b\ufeff]+/g, '' );
	}

	/**
	 * Whether there's nothing in an editor yet, as it starts: no text, and nothing like an image.
	 *
	 * @param {HTMLElement} editable The editor's text.
	 * @return {boolean} Whether it's empty.
	 */
	function isEmpty( editable ) {
		return (
			isBlank( editable.textContent ) &&
			! editable.querySelector( 'img, iframe, video, hr, table' )
		);
	}

	/**
	 * The text of the last line of what's before the caret.
	 *
	 * @param {Node} node What's before the caret.
	 * @return {string} Text.
	 */
	function lastLine( node ) {
		let line = '';
		const visit = ( parent ) => {
			for ( const child of parent.childNodes ) {
				if ( window.Node.TEXT_NODE === child.nodeType ) {
					line += child.nodeValue;
				} else if ( 'BR' === child.nodeName ) {
					line = '';
				} else {
					visit( child );
					// A block's end is a line's end.
					if ( BLOCKS.has( child.nodeName ) ) {
						line = '';
					}
				}
			}
		};

		visit( node );

		return line;
	}

	/**
	 * Whether the caret is at the start of a line, with nothing but spaces before it on it: at the start of the text, or
	 * of a new line. Not within a word, nor after one, so a / in "and/or" or "1 / 2" stays a /.
	 *
	 * @param {HTMLElement} editable The editor's text.
	 * @return {boolean} Whether it is.
	 */
	function isAtLineStart( editable ) {
		const selection = editable.ownerDocument.defaultView.getSelection();
		if ( ! selection || ! selection.rangeCount ) {
			return false;
		}

		const caret = selection.getRangeAt( 0 );
		if (
			! caret.collapsed ||
			! editable.contains( caret.startContainer )
		) {
			return false;
		}

		// From the start of the caret's block.
		let block = caret.startContainer;
		while ( block !== editable && ! BLOCKS.has( block.nodeName ) ) {
			block = block.parentNode;
		}

		const before = document.createRange();
		before.setStart( block, 0 );
		before.setEnd( caret.startContainer, caret.startOffset );

		return isBlank( lastLine( before.cloneContents() ) );
	}

	/**
	 * The menu's replies and categories that show, and Save This Reply, in order, without the search.
	 *
	 * @param {jQuery} $menu Menu.
	 * @return {jQuery} Their links.
	 */
	function itemsOf( $menu ) {
		return $menu
			.children( 'li' )
			.not( '.divider' )
			.children( 'a' )
			.not( '.sr-li-search' )
			.filter( ':visible' );
	}

	/**
	 * The menu's toggle, the button in the toolbar.
	 *
	 * @param {jQuery} $menu Menu.
	 * @return {jQuery} Toggle.
	 */
	function toggleOf( $menu ) {
		return $menu.siblings( '[data-toggle="dropdown"]' ).first();
	}

	/**
	 * Opens an editor's menu, after saving where the caret is, where the module inserts the reply. The search gets the
	 * focus, or, without one, the first reply.
	 *
	 * @param {jQuery} $editor Editor.
	 * @return {boolean} Whether it opened.
	 */
	function openMenu( $editor ) {
		const $menu = $editor.find( MENU ).first();
		const $toggle = toggleOf( $menu );
		if ( ! $toggle.length || 'function' !== typeof $.fn.dropdown ) {
			return false;
		}

		noteOf( $editor ).summernote( 'editor.saveRange' );

		if ( ! $menu.parent().hasClass( 'open' ) ) {
			$toggle.dropdown( 'toggle' );
		}

		const $search = $menu.find( SEARCH ).first();
		( $search.length ? $search : itemsOf( $menu ).first() ).trigger(
			'focus'
		);

		return true;
	}

	/**
	 * Closes a menu, and puts the caret back where it was in the editor.
	 *
	 * @param {jQuery} $menu Menu.
	 */
	function closeMenu( $menu ) {
		const $editor = $menu.closest( '.note-editor' );
		const $note = noteOf( $editor );

		if ( $menu.parent().hasClass( 'open' ) ) {
			toggleOf( $menu ).dropdown( 'toggle' );
		}

		if ( $note.length ) {
			$note.summernote( 'editor.restoreRange' );
		} else {
			$editor.find( '.note-editable' ).trigger( 'focus' );
		}
	}

	/**
	 * Moves the focus up or down the menu: from the search to the replies, and back up to it from the first one.
	 *
	 * @param {jQuery}  $menu  Menu.
	 * @param {Element} from   Where the focus is.
	 * @param {number}  offset 1 to go down, -1 to go up.
	 */
	function move( $menu, from, offset ) {
		const $search = $menu.find( SEARCH ).first();
		const stops = $search.add( itemsOf( $menu ) ).get();

		// The search's link counts as the search.
		let index = $( from ).closest( '.sr-li-search' ).length
			? 0
			: stops.indexOf( from );
		index = Math.min( Math.max( index + offset, 0 ), stops.length - 1 );

		if ( stops[ index ] ) {
			stops[ index ].focus();
		}
	}

	/**
	 * Handles the keys in a menu: the arrow keys, Enter in the search, and Escape. Typing on a reply goes to the search,
	 * and Backspace deletes from it. Nothing else on the page gets them, like the conversation's shortcuts.
	 *
	 * @param {KeyboardEvent} event Key down.
	 * @param {jQuery}        $menu Menu.
	 */
	function onMenuKeydown( event, $menu ) {
		const $search = $menu.find( SEARCH ).first();
		const inSearch =
			$search.length &&
			( event.target === $search.get( 0 ) ||
				$( event.target ).closest( '.sr-li-search' ).length > 0 );

		switch ( event.key ) {
			case 'ArrowDown':
			case 'ArrowUp':
				event.preventDefault();
				move( $menu, event.target, 'ArrowDown' === event.key ? 1 : -1 );
				break;

			case 'Escape':
				event.preventDefault();
				// After Escape, a / types a /, so a line can start with one, like a path.
				literalSlash = openedBySlash;
				closeMenu( $menu );
				break;

			case 'Enter':
				if ( ! inSearch ) {
					// A reply's link: the module inserts it.
					return;
				}

				// Enter in the search would send the reply form, which it's in; it inserts the first reply found.
				event.preventDefault();
				if ( $search.val().trim() ) {
					const reply = itemsOf( $menu )
						.not( '.sr-cat, [data-value=""]' )
						.get( 0 );
					if ( reply ) {
						reply.click();
					}
				}
				break;

			case 'Backspace':
				if ( inSearch || ! $search.length ) {
					return;
				}

				// On a reply, it deletes the search's last character, as typing adds to it.
				event.preventDefault();
				focusSearchEnd( $search );
				$search.val( $search.val().slice( 0, -1 ) ).trigger( 'keyup' );
				break;

			default:
				if ( ! inSearch && $search.length && 1 === event.key.length ) {
					// The key types into the search, which has the focus by the time it does.
					focusSearchEnd( $search );
				}
		}

		event.stopPropagation();
	}

	/**
	 * Focuses the search, with the caret at the end of what's in it, where typing adds to it.
	 *
	 * @param {jQuery} $search Search.
	 */
	function focusSearchEnd( $search ) {
		const search = $search.get( 0 );
		const end = search.value.length;

		search.focus();
		search.setSelectionRange( end, end );
	}

	/**
	 * Opens the menu for a / at the start of a line, which it takes the place of.
	 *
	 * @param {KeyboardEvent} event    Key down.
	 * @param {HTMLElement}   editable The editor's text.
	 */
	function onEditorKeydown( event, editable ) {
		const $editor = editorOf( editable );
		if ( ! $editor ) {
			return;
		}

		if ( '/' !== event.key ) {
			literalSlash = literalSlash && MODIFIERS.has( event.key );
			return;
		}

		if ( literalSlash ) {
			literalSlash = false;
			return;
		}

		if ( isAtLineStart( editable ) && openMenu( $editor ) ) {
			event.preventDefault();
			openedBySlash = true;
		}
	}

	/**
	 * Sends keys to the editor or menu they're for. In the capture phase, before Bootstrap's dropdowns, which leave the
	 * search alone, and the conversation's shortcuts.
	 *
	 * @param {KeyboardEvent} event Key down.
	 */
	function onKeydown( event ) {
		const target = event.target;
		if (
			event.defaultPrevented ||
			event.isComposing ||
			'string' !== typeof event.key ||
			! ( target instanceof window.Element )
		) {
			return;
		}

		// On some keyboards, / takes AltGr, which reads as Ctrl and Alt.
		const altGraph =
			event.getModifierState && event.getModifierState( 'AltGraph' );
		if (
			event.metaKey ||
			( ( event.ctrlKey || event.altKey ) && ! altGraph )
		) {
			return;
		}

		const menu = target.closest( MENU );
		if ( menu ) {
			onMenuKeydown( event, $( menu ) );
		} else if ( target.isContentEditable ) {
			onEditorKeydown( event, target );
		}
	}

	/**
	 * Adds the placeholder that tells about /, to an editor with the menu, shown while the editor is empty.
	 *
	 * @param {jQuery} $editor Editor.
	 */
	function addPlaceholder( $editor ) {
		const editable = $editor.find( '.note-editable' ).get( 0 );
		const $area = $editor.find( '.note-editing-area' ).first();
		if (
			! editable ||
			! $area.length ||
			$editor.data( 'wporgsite-placeholder' )
		) {
			return;
		}

		// Where the editor's own text starts, in its font.
		const style = window.getComputedStyle( editable );
		const $placeholder = $(
			'<div class="wporgsite-editor-placeholder" aria-hidden="true"></div>'
		)
			.append(
				document.createTextNode( PLACEHOLDER[ 0 ] ),
				$( '<kbd class="wporgsite-key"></kbd>' ).text(
					PLACEHOLDER[ 1 ]
				),
				document.createTextNode( PLACEHOLDER[ 2 ] )
			)
			.css( {
				padding: style.padding,
				font: style.font,
			} );

		const update = () =>
			$placeholder.toggle(
				isEmpty( editable ) && ! $editor.hasClass( 'codeview' )
			);

		new window.MutationObserver( update ).observe( editable, {
			childList: true,
			characterData: true,
			subtree: true,
		} );
		noteOf( $editor ).on( 'summernote.codeview.toggled', update );

		$area.prepend( $placeholder );
		$editor.data( 'wporgsite-placeholder', true );
		update();
	}

	document.addEventListener( 'keydown', onKeydown, true );

	// Clicking the editor where it was, after Escape, starts over.
	$( document ).on( 'mousedown', '.note-editable', () => {
		literalSlash = false;
	} );

	// However the menu opened, its search gets the focus and starts empty: the module keeps the last search.
	$( document ).on( 'shown.bs.dropdown', '.note-btn-group', function () {
		const $search = $( this ).children( MENU ).find( SEARCH ).first();
		if ( $search.val() ) {
			// The module filters the replies as keys go up.
			$search.val( '' ).trigger( 'keyup' );
		}

		$search.trigger( 'focus' );
	} );

	$( document ).on( 'hidden.bs.dropdown', '.note-btn-group', () => {
		openedBySlash = false;
	} );

	$( () => {
		// Core starts the editors after the page's scripts, as it's ready.
		window.setTimeout( () => {
			$( MENU )
				.closest( '.note-editor' )
				.each( function () {
					addPlaceholder( $( this ) );
				} );
		} );
	} );
} )( jQuery );
