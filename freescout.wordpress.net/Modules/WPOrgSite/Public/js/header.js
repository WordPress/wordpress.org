/**
 * Pins a conversation's header, its toolbar, subject, and reply and note forms, to the top of the window, so it stays
 * in view while the agent reads the emails below; from a button in its toolbar, which the browser remembers. Off, the
 * default, the header scrolls away, as in core.
 *
 * Pinned, the header takes at most 55% of the window, so a good part of the emails still shows: the editor scrolls
 * within what's left of it, rather than growing. A button next to Save Draft gives the editor the whole window, for
 * long replies, and back.
 *
 * The status and assignee selectors, which conversation.js pins while their menu is open, are in the header, so they
 * stay in view with it.
 *
 * In the alternate conversation design, laid out by alt-conversation.js, the pre-header is always in view, and the
 * forms open at the bottom: there's no button to pin the header, and the editor gets 55% of the window below the
 * pre-header, or all of it, the same way.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * Where the browser remembers it.
	 *
	 * @type {string}
	 */
	const KEY = 'wporgsite.header';

	/**
	 * The page's class while it's pinned.
	 *
	 * @type {string}
	 */
	const PINNED = 'wporgsite-header-pinned';

	/**
	 * How much of the window the pinned header takes at most.
	 *
	 * @type {number}
	 */
	const SHARE = 0.55;

	/**
	 * How tall the editor is at least, in pixels: core's own minimum.
	 *
	 * @type {number}
	 */
	const MIN_EDITOR = 120;

	/**
	 * What the buttons say.
	 *
	 * @type {Object<string, string>}
	 */
	const STRINGS = {
		pin: 'Pin the header',
		unpin: 'Unpin the header',
		expand: 'Use the whole window for the editor',
		shrink: 'Give the editor less room',
	};

	/**
	 * Whether the editor has the whole window, for this page.
	 *
	 * @type {boolean}
	 */
	let expanded = false;

	/**
	 * The browser's storage, if the page may use it.
	 *
	 * @return {Storage|null} Storage.
	 */
	function storage() {
		try {
			return window.localStorage || null;
		} catch {
			return null;
		}
	}

	/**
	 * Whether the header is pinned.
	 *
	 * @return {boolean} Whether it is.
	 */
	function isPinned() {
		return document.documentElement.classList.contains( PINNED );
	}

	/**
	 * Whether the conversation has the alternate design, where the forms open at the bottom.
	 *
	 * @return {boolean} Whether it has.
	 */
	function isAlt() {
		return document.documentElement.classList.contains(
			'wporgsite-alt-layout'
		);
	}

	/**
	 * Remembers whether the header is pinned; unpinned, the default, isn't stored.
	 *
	 * @param {boolean} pinned Whether it is.
	 */
	function remember( pinned ) {
		const local = storage();
		if ( ! local ) {
			return;
		}

		try {
			if ( pinned ) {
				local.setItem( KEY, 'pinned' );
			} else {
				local.removeItem( KEY );
			}
		} catch {
			// Then it's only for this page.
		}
	}

	/**
	 * Changes an element's tooltip, whether Bootstrap set it up yet or not, and its label.
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
		$element.attr( 'aria-label', title );
	}

	/**
	 * Sizes the editor, while the header is pinned, or in the alternate design, and a form is open: up to what's left of
	 * 55% of the window, or, if the agent asked for it, all that's left of it. It scrolls within that, as site.css says.
	 * In the alternate design, what's under the pre-header, of the forms at the bottom.
	 */
	function fit() {
		const editable = $( '.conv-reply-block .note-editable' ).get( 0 );
		const box = isAlt()
			? document.querySelector( '.wporgsite-alt-dock' )
			: document.getElementById( 'conv-layout-header' );
		if ( ! editable || ! box ) {
			return;
		}

		if (
			( ! isPinned() && ! isAlt() ) ||
			$( '.conv-reply-block' ).hasClass( 'hidden' )
		) {
			editable.style.maxHeight = '';
			editable.style.height = '';
			return;
		}

		// In the alternate design, the conversation scrolls, under the pre-header.
		const toolbar = document.getElementById( 'conv-toolbar' );
		const scroller = document.querySelector( '.wporgsite-alt-column' );
		const available =
			isAlt() && scroller
				? scroller.clientHeight - ( toolbar ? toolbar.offsetHeight : 0 )
				: window.innerHeight;
		const others = box.offsetHeight - editable.offsetHeight;
		const room = Math.max(
			MIN_EDITOR,
			Math.floor( available * ( expanded ? 1 : SHARE ) - others )
		);

		editable.style.maxHeight = room + 'px';
		editable.style.height = expanded ? room + 'px' : '';
	}

	/**
	 * Updates the pin button for whether the header is pinned.
	 *
	 * @param {jQuery} $button Button.
	 */
	function updatePin( $button ) {
		const pinned = isPinned();

		setTitle( $button, pinned ? STRINGS.unpin : STRINGS.pin );
		$button.attr( 'aria-pressed', String( pinned ) );
	}

	/**
	 * Updates the editor's button for whether the editor has the whole window.
	 *
	 * @param {jQuery} $button Button.
	 */
	function updateExpand( $button ) {
		setTitle( $button, expanded ? STRINGS.shrink : STRINGS.expand );
		$button.attr( 'aria-pressed', String( expanded ) );
		$button
			.children( '.glyphicon' )
			.toggleClass( 'glyphicon-resize-full', ! expanded )
			.toggleClass( 'glyphicon-resize-small', expanded );
	}

	/**
	 * Sets up a button's tooltip, as core does its own; it may have set them up before the button was there.
	 *
	 * @param {jQuery} $button Button.
	 */
	function tooltip( $button ) {
		if ( 'function' === typeof $.fn.tooltip ) {
			$button.tooltip( { container: 'body' } );
		}
	}

	/**
	 * Shows a button's new tooltip, if it's showing.
	 *
	 * @param {jQuery} $button Button.
	 */
	function refreshTooltip( $button ) {
		if (
			'function' === typeof $.fn.tooltip &&
			$button.get( 0 ).matches( ':hover' )
		) {
			$button.tooltip( 'show' );
		}
	}

	/**
	 * Adds the button that pins the header, at the end of its toolbar.
	 */
	function addPin() {
		const $button = $(
			'<button type="button" class="wporgsite-header-pin" data-toggle="tooltip" data-placement="bottom">' +
				'<i class="glyphicon glyphicon-pushpin" aria-hidden="true"></i>' +
				'</button>'
		);

		$( '#conv-toolbar .conv-actions' ).first().append( $button );
		updatePin( $button );
		tooltip( $button );

		$button.on( 'click', () => {
			document.documentElement.classList.toggle( PINNED );
			remember( isPinned() );
			updatePin( $button );
			refreshTooltip( $button );
			fit();
		} );
	}

	/**
	 * Adds the button that gives the editor the whole window, before Save Draft; site.css shows it while the header is
	 * pinned, or in the alternate design.
	 */
	function addExpand() {
		const $save = $(
			'.conv-reply-block .note-toolbar .note-btn-save-draft'
		).first();
		if ( ! $save.length ) {
			return;
		}

		const $button = $(
			'<button type="button" class="note-btn btn btn-default btn-sm wporgsite-editor-expand" tabindex="-1" data-toggle="tooltip">' +
				'<i class="glyphicon" aria-hidden="true"></i>' +
				'</button>'
		);

		$save.before( $button );
		updateExpand( $button );
		tooltip( $button );

		$button.on( 'click', ( event ) => {
			event.preventDefault();
			expanded = ! expanded;
			updateExpand( $button );
			refreshTooltip( $button );
			fit();
		} );
	}

	// As early as possible, so it's pinned from the start; the alternate design has its own.
	try {
		const local = storage();
		if ( ! isAlt() && local && 'pinned' === local.getItem( KEY ) ) {
			document.documentElement.classList.add( PINNED );
		}
	} catch {
		// Then it isn't.
	}

	// Core scrolls the page to a form it opens, which, pinned, is in view already: it would only move the emails.
	$( document ).on(
		'click',
		'.conv-actions .conv-reply, .conv-actions .conv-add-note',
		() => {
			if ( isPinned() || isAlt() ) {
				$( 'html, body' ).stop( true );
			}
		}
	);

	$( () => {
		// Only conversations have a number.
		if (
			! $( '.conv-numnav' ).length ||
			! $( '#conv-layout-header' ).length
		) {
			return;
		}

		if ( ! isAlt() ) {
			addPin();
		}

		// Core starts the editor after the page's scripts, as it's ready.
		window.setTimeout( addExpand );

		// The header, or the bottom, changes height as forms open and close, and fields show; the editor gets what's left.
		if ( 'function' === typeof window.ResizeObserver ) {
			const observer = new window.ResizeObserver( fit );
			// The conversation too, as the navigation slides away and back.
			$(
				'#conv-layout-header, .wporgsite-alt-dock, .wporgsite-alt-column'
			).each( function () {
				observer.observe( this );
			} );
		}
		window.addEventListener( 'resize', fit );
	} );
} )( jQuery );
