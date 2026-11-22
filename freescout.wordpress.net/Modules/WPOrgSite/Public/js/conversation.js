/**
 * Keyboard shortcuts in conversations, status and assignee selectors that come into view while their menus are open,
 * and copying a conversation's number by clicking it.
 *
 * The shortcuts work while the agent isn't typing and no modal is open. They click core's own buttons and links, and
 * those that modules add, so core's checks and hooks run as they do for a click, and a shortcut whose button the agent
 * doesn't have, or whose module is off, does nothing.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * The buttons and links with a shortcut, which show its key.
	 *
	 * @type {Object<string, string>}
	 */
	const BUTTONS = {
		reply: '.conv-actions .conv-reply',
		note: '.conv-actions .conv-add-note',
		// The Tags module's.
		tag: '.conv-actions .conv-add-tags',
		// The Workflows module's, in the More Actions menu, for mailboxes with manual workflows.
		workflow: '.conv-actions a[data-modal-on-show="initRunWorkflow"]',
		merge: '.conv-actions a[data-modal-on-show="initMergeConv"]',
		status: '#conv-status',
		assignee: '#conv-assignee',
		newer: '.conv-next-prev .glyphicon-menu-left',
		older: '.conv-next-prev .glyphicon-menu-right',
	};

	/**
	 * The statuses' keys in the status menu, by core's Conversation::STATUS_* values: while it's open, a is Active,
	 * rather than the assignee menu. Spam's works with the menu closed too.
	 *
	 * @type {Map<string, string>}
	 */
	const STATUSES = new Map( [
		[ 'a', '1' ],
		[ 'p', '2' ],
		[ 'c', '3' ],
		[ '!', '4' ],
	] );

	/**
	 * The shortcuts, each with what it shows its key on, and what it does; true when it did something.
	 *
	 * @type {Map<string, Object>}
	 */
	const SHORTCUTS = new Map( [
		[
			'r',
			{ button: BUTTONS.reply, run: () => openForm( BUTTONS.reply ) },
		],
		[ 'n', { button: BUTTONS.note, run: () => openForm( BUTTONS.note ) } ],
		[ 't', { button: BUTTONS.tag, run: () => press( BUTTONS.tag ) } ],
		[
			'w',
			{
				button: BUTTONS.workflow,
				run: () => openModal( BUTTONS.workflow ),
			},
		],
		[
			'm',
			{ button: BUTTONS.merge, run: () => openModal( BUTTONS.merge ) },
		],
		[
			's',
			{
				button: BUTTONS.status,
				menu: BUTTONS.status,
				run: () => toggleMenu( BUTTONS.status ),
			},
		],
		[
			'a',
			{
				button: BUTTONS.assignee,
				menu: BUTTONS.assignee,
				run: () => toggleMenu( BUTTONS.assignee ),
			},
		],
		[ '!', { run: () => changeStatus( STATUSES.get( '!' ) ) } ],
		[ 'k', { button: BUTTONS.newer, run: () => follow( BUTTONS.newer ) } ],
		[ 'j', { button: BUTTONS.older, run: () => follow( BUTTONS.older ) } ],
	] );

	/**
	 * Inputs that take text, where keys are typing.
	 *
	 * @type {string}
	 */
	const TEXT_FIELDS =
		'textarea, select, input:not([type="checkbox"], [type="radio"], [type="button"], [type="submit"], [type="reset"])';

	/**
	 * What the conversation's number says it does.
	 *
	 * @type {Object<string, string>}
	 */
	const STRINGS = {
		copy: 'Copy number',
		copied: 'Copied',
		failed: 'Couldn’t copy',
	};

	/**
	 * How long "Copied" shows, in milliseconds.
	 *
	 * @type {number}
	 */
	const COPIED_FOR = 1200;

	/**
	 * How far from the top of the window the pinned status selector sits, in pixels.
	 *
	 * @type {number}
	 */
	const PIN_TOP = 8;

	/**
	 * How much space a form scrolled to leaves above it, for the pinned status selector, in pixels.
	 *
	 * @type {number}
	 */
	const SCROLL_MARGIN = 50;

	/**
	 * How much of a form must show for it to be in view, in pixels.
	 *
	 * @type {number}
	 */
	const IN_VIEW = 200;

	/**
	 * Hides "Copied".
	 *
	 * @type {number}
	 */
	let copiedTimer = 0;

	/**
	 * An element's tooltip, as Bootstrap keeps it once it set the tooltip up.
	 *
	 * @param {jQuery} $element Element.
	 * @return {string} Tooltip.
	 */
	function getTitle( $element ) {
		const original = $element.attr( 'data-original-title' );

		return String(
			undefined !== original ? original : $element.attr( 'title' ) || ''
		);
	}

	/**
	 * Changes an element's tooltip, whether Bootstrap set it up yet or not.
	 *
	 * @param {jQuery} $element Element.
	 * @param {string} title    Tooltip.
	 */
	function setTitle( $element, title ) {
		if ( undefined !== $element.attr( 'data-original-title' ) ) {
			$element.attr( 'data-original-title', title );
		} else {
			$element.attr( 'title', title );
		}
	}

	/**
	 * Whether the agent is typing in the element the key went to, or is in the editor, like its toolbar's menus.
	 *
	 * @param {EventTarget} target Element.
	 * @return {boolean} Whether it takes text.
	 */
	function isTyping( target ) {
		return (
			target instanceof window.Element &&
			( target.isContentEditable ||
				target.matches( TEXT_FIELDS ) ||
				null !== target.closest( '.note-editor' ) )
		);
	}

	/**
	 * Scrolls to an element that's out of view.
	 *
	 * @param {jQuery} $element Element.
	 */
	function bringIntoView( $element ) {
		const element = $element.get( 0 );

		// Core may already be scrolling to a form it just showed.
		if ( ! element || $( 'html, body' ).is( ':animated' ) ) {
			return;
		}

		const top = element.getBoundingClientRect().top;
		if ( top >= 0 && top <= window.innerHeight - IN_VIEW ) {
			return;
		}

		// Core's own global scrollTo() takes the window's place.
		( document.scrollingElement || document.documentElement ).scrollTo( {
			top: window.scrollY + top - SCROLL_MARGIN,
			behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' )
				.matches
				? 'auto'
				: 'smooth',
		} );
	}

	/**
	 * Clicks a button, as the agent would: through its handlers, without following a link's URL, which for the
	 * buttons that open a modal is the modal's content.
	 *
	 * @param {string} selector Button.
	 * @return {boolean} Whether there's the button.
	 */
	function press( selector ) {
		const $button = $( selector ).first();
		if ( ! $button.length ) {
			return false;
		}

		$button.trigger( 'click' );

		return true;
	}

	/**
	 * Focuses a modal's first text field, like Merge's conversation number, so the agent can go on typing. Core shows
	 * the modal at once, and loads what's in it a moment later. Buttons, like Run Workflow's, aren't focused, so Enter
	 * doesn't run one.
	 *
	 * @param {HTMLElement} modal Modal.
	 */
	function focusFirstField( modal ) {
		const focus = () => {
			const field = $( modal )
				.find( TEXT_FIELDS )
				.not( 'select' )
				.filter( ':visible' )
				.get( 0 );
			if ( field ) {
				field.focus();
			}

			return !! field;
		};

		if ( focus() ) {
			return;
		}

		const observer = new window.MutationObserver( () => {
			if ( focus() ) {
				observer.disconnect();
			}
		} );
		observer.observe( modal, { childList: true, subtree: true } );
		$( modal ).one( 'hidden.bs.modal', () => observer.disconnect() );
	}

	/**
	 * Clicks a button that opens a modal, and focuses the modal's first text field.
	 *
	 * @param {string} selector Button.
	 * @return {boolean} Whether there's the button.
	 */
	function openModal( selector ) {
		if ( ! $( selector ).length ) {
			return false;
		}

		const onShown = ( event ) => focusFirstField( event.target );
		$( document ).one( 'shown.bs.modal', onShown );
		// Core fades the modal in over 300ms; a button that opens none, like Workflows without any, leaves nothing armed.
		window.setTimeout(
			() => $( document ).off( 'shown.bs.modal', onShown ),
			1000
		);

		return press( selector );
	}

	/**
	 * Opens the reply or note form, or brings back the one that's open: core doesn't switch an open form to another
	 * kind, which would leave its draft behind.
	 *
	 * @param {string} button The button that opens it.
	 * @return {boolean} Whether there's a form.
	 */
	function openForm( button ) {
		const $form = $( '.conv-reply-block' ).first();
		if ( ! $( button ).length || ! $form.length ) {
			return false;
		}

		// Core focuses the editor of a form it shows.
		if ( $form.hasClass( 'hidden' ) ) {
			press( button );
		} else {
			const editor = $form.find( '.note-editable' ).get( 0 );
			if ( editor ) {
				editor.focus( { preventScroll: true } );
			}
		}

		bringIntoView( $form );

		return true;
	}

	/**
	 * Whether a selector's menu, the status's or the assignee's, is open.
	 *
	 * @param {string} selector Selector.
	 * @return {boolean} Whether it's open.
	 */
	function isMenuOpen( selector ) {
		return $( selector ).hasClass( 'open' );
	}

	/**
	 * Opens a selector's menu, or closes it. Bootstrap focuses its button, so the arrow keys and Enter work in it too.
	 *
	 * @param {string} selector Selector.
	 * @return {boolean} Whether there's the menu.
	 */
	function toggleMenu( selector ) {
		const $toggle = $( selector ).find( '.dropdown-toggle' ).first();
		if ( ! $toggle.length || 'function' !== typeof $.fn.dropdown ) {
			return false;
		}

		$toggle.dropdown( 'toggle' );

		return true;
	}

	/**
	 * Changes the conversation's status, as its link in the status menu does.
	 *
	 * @param {string} status Status, as core's Conversation::STATUS_* values.
	 * @return {boolean} Whether the conversation can have it.
	 */
	function changeStatus( status ) {
		const $link = $( BUTTONS.status )
			.find( '.conv-status a[data-status="' + status + '"]' )
			.first();
		if ( ! $link.length ) {
			return false;
		}

		if ( isMenuOpen( BUTTONS.status ) ) {
			toggleMenu( BUTTONS.status );
		}

		// Core would save the status the conversation already has, and reload the page.
		if ( ! $link.parent().hasClass( 'active' ) ) {
			$link.trigger( 'click' );
		}

		return true;
	}

	/**
	 * Follows a link.
	 *
	 * @param {string} selector Link.
	 * @return {boolean} Whether there's the link.
	 */
	function follow( selector ) {
		const link = $( selector ).get( 0 );
		if ( ! link || ! link.href ) {
			return false;
		}

		link.click();

		return true;
	}

	/**
	 * Runs the key's shortcut, unless the agent is typing.
	 *
	 * @param {KeyboardEvent} event Key down.
	 */
	function onKeydown( event ) {
		if (
			event.defaultPrevented ||
			event.repeat ||
			event.isComposing ||
			event.ctrlKey ||
			event.metaKey ||
			event.altKey ||
			'string' !== typeof event.key ||
			isTyping( event.target ) ||
			$( '.modal:visible' ).length
		) {
			return;
		}

		// Caps Lock doesn't change which letter it is; Shift does, and is left for other uses.
		const key = event.shiftKey ? event.key : event.key.toLowerCase();

		let done = false;
		if ( isMenuOpen( BUTTONS.status ) && STATUSES.has( key ) ) {
			done = changeStatus( STATUSES.get( key ) );
		} else if ( SHORTCUTS.has( key ) ) {
			const shortcut = SHORTCUTS.get( key );

			// A menu would stay open over the form, page, or other menu the shortcut brings up.
			[ BUTTONS.status, BUTTONS.assignee ]
				.filter(
					( menu ) => menu !== shortcut.menu && isMenuOpen( menu )
				)
				.forEach( toggleMenu );

			done = shortcut.run();
		}

		// Otherwise the key would type into the editor or field the shortcut focused.
		if ( done ) {
			event.preventDefault();
		}
	}

	/**
	 * Copies text the old way, through a selected field, which works where the browser doesn't let the page write to
	 * the clipboard, as long as it's in answer to a click.
	 *
	 * @param {string}      text   Text.
	 * @param {HTMLElement} button What was clicked, which gets the focus back.
	 * @return {boolean} Whether it was copied.
	 */
	function copyBySelecting( text, button ) {
		const field = document.createElement( 'textarea' );
		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.top = '0';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();

		let copied = false;
		try {
			copied = document.execCommand( 'copy' );
		} catch {
			copied = false;
		}

		field.remove();
		button.focus( { preventScroll: true } );

		return copied;
	}

	/**
	 * Copies text to the clipboard.
	 *
	 * @param {string}      text   Text.
	 * @param {HTMLElement} button What was clicked.
	 * @return {Promise} Settles once it's copied, or couldn't be.
	 */
	function copy( text, button ) {
		const clipboard = window.navigator.clipboard;
		const fallback = () =>
			copyBySelecting( text, button )
				? Promise.resolve()
				: Promise.reject();

		if ( ! clipboard || ! clipboard.writeText ) {
			return fallback();
		}

		return clipboard.writeText( text ).catch( fallback );
	}

	/**
	 * Copies the conversation's number, and says so in its tooltip.
	 *
	 * @param {jQuery} $number The number.
	 */
	function copyNumber( $number ) {
		const number = String(
			$number.attr( 'data-number' ) || $number.text()
		).trim();
		const say = ( text ) => {
			setTitle( $number, text );
			if ( 'function' === typeof $.fn.tooltip ) {
				$number.tooltip( 'show' );
			}

			window.clearTimeout( copiedTimer );
			copiedTimer = window.setTimeout( () => {
				if ( 'function' === typeof $.fn.tooltip ) {
					$number.tooltip( 'hide' );
				}
				setTitle( $number, STRINGS.copy );
			}, COPIED_FOR );
		};

		if ( ! number ) {
			say( STRINGS.failed );
			return;
		}

		copy( number, $number.get( 0 ) ).then(
			() => say( STRINGS.copied ),
			() => say( STRINGS.failed )
		);
	}

	/**
	 * Makes the conversation's number a button that copies it; in the alternate design, the sidebar's one too, which
	 * alt-conversation.js makes a button already.
	 */
	function initNumber() {
		$( '.conv-numnav strong, .wporgsite-alt-number' ).each( function () {
			const $number = $( this );
			const isButton = 'BUTTON' === this.tagName;

			$number.addClass( 'wporgsite-number' ).attr( {
				title: STRINGS.copy,
				'data-toggle': 'tooltip',
			} );
			if ( ! isButton ) {
				$number.attr( { role: 'button', tabindex: '0' } );
			}

			// Core sets up the tooltips that are on the page as it loads, which may have been before this.
			if ( 'function' === typeof $.fn.tooltip ) {
				$number.tooltip( { container: 'body' } );
			}

			$number.on( 'click', ( event ) => {
				event.preventDefault();
				copyNumber( $number );
			} );

			// A button's Enter and Space click it already.
			if ( ! isButton ) {
				$number.on( 'keydown', ( event ) => {
					if ( 'Enter' === event.key || ' ' === event.key ) {
						event.preventDefault();
						copyNumber( $number );
					}
				} );
			}
		} );
	}

	/**
	 * Shows a button's key: in its tooltip, or, for a menu item, which has none, next to its name, and in the title
	 * some have.
	 *
	 * @param {jQuery} $button Button.
	 * @param {string} key     Key.
	 */
	function showKey( $button, key ) {
		$button
			.find( '[data-toggle="dropdown"]' )
			.addBack()
			.attr( 'aria-keyshortcuts', key );

		const inMenu = $button.closest( '.dropdown-menu' ).length > 0;
		if ( inMenu ) {
			$button.attr( 'data-wporgsite-key', key );
		}

		// Some buttons, like the Tags module's, have their tooltip on what holds them.
		const $tooltip = inMenu
			? $button
			: $button.closest( '[data-toggle="tooltip"]' );
		const title = getTitle( $tooltip );
		if ( title ) {
			setTitle( $tooltip, title + ' (' + key + ')' );
		}
	}

	/**
	 * Shows the shortcuts' keys.
	 */
	function initHints() {
		SHORTCUTS.forEach( ( shortcut, key ) => {
			if ( shortcut.button ) {
				$( shortcut.button ).each( function () {
					showKey( $( this ), key );
				} );
			}
		} );

		STATUSES.forEach( ( status, key ) => {
			$( BUTTONS.status )
				.find( '.conv-status a[data-status="' + status + '"]' )
				.each( function () {
					showKey( $( this ), key );
				} );
		} );
	}

	/**
	 * Pins a selector, the status's or the assignee's, to the top of the window while its menu is open and the toolbar
	 * it's in is scrolled out of view, as it is further down long conversations, so the agent can choose there, like
	 * after pressing s or a.
	 *
	 * @param {string} selector Selector.
	 */
	function initPinning( selector ) {
		const element = $( selector ).get( 0 );
		const toolbar = document.getElementById( 'conv-toolbar' );

		// The alternate design has them at the top of the sidebar, always in view.
		if (
			! element ||
			! toolbar ||
			document.documentElement.classList.contains(
				'wporgsite-alt-layout'
			)
		) {
			return;
		}

		// Where the selector is in the toolbar, and across the window; it keeps the same place across when pinned.
		let offset = 0;
		let left = 0;
		let frame = 0;

		/**
		 * Pins or unpins the selector.
		 *
		 * @param {boolean} [opening] Whether the menu is about to open.
		 */
		const update = ( opening ) => {
			frame = 0;

			const pinned =
				( true === opening || element.classList.contains( 'open' ) ) &&
				toolbar.offsetHeight > 0 &&
				toolbar.getBoundingClientRect().top + offset < PIN_TOP;

			element.classList.toggle( 'wporgsite-pinned', pinned );
			element.style.left = pinned ? left + 'px' : '';
		};

		const measure = () => {
			element.classList.remove( 'wporgsite-pinned' );
			element.style.left = '';

			const rect = element.getBoundingClientRect();
			offset = rect.top - toolbar.getBoundingClientRect().top;
			left = rect.left;

			update();
		};

		window.addEventListener(
			'scroll',
			() => {
				if ( ! frame ) {
					frame = window.requestAnimationFrame( update );
				}
			},
			{ passive: true }
		);
		window.addEventListener( 'resize', measure );
		window.addEventListener( 'load', measure );

		// Pinned before Bootstrap focuses the menu's button as it opens it, which would scroll the page up to it.
		$( element ).on( 'show.bs.dropdown', () => update( true ) );
		$( element ).on( 'hidden.bs.dropdown', () => update() );

		measure();
	}

	$( () => {
		// Only conversations have a number.
		if ( ! $( '.conv-numnav' ).length ) {
			return;
		}

		initNumber();
		initHints();
		initPinning( BUTTONS.status );
		initPinning( BUTTONS.assignee );

		document.addEventListener( 'keydown', onKeydown );
	} );
} )( jQuery );
