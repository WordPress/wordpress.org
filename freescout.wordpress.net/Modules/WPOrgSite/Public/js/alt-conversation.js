/**
 * The alternate conversation design, for agents who choose it in their profile, whose page's body then has the
 * wporgsite-alt-conversation class:
 *
 * - A pre-header, always in view: the subject, the tags, and the actions but Reply and Note.
 * - The rest of the subject block as it was, scrolling away with the threads.
 * - At the top of the sidebar, which scrolls on its own: the number, which copies itself when clicked, the previous
 *   and next conversations, and the assignee and status selectors.
 * - The threads oldest first, and the page scrolled to the newest as it opens.
 * - Reply and Note buttons at the bottom, over the threads, which the forms take the place of as they open.
 *
 * The page doesn't scroll itself: the conversation does, with its scrollbar by its side, and each sidebar on its own.
 * The navigation slides away while the conversation is scrolled from its top, and its mark stays, at the top of the
 * left sidebar.
 *
 * It moves core's own elements, so their handlers go with them, and core's code still finds them; and it lays the
 * page out as it runs, at the end of the page, before core and the other scripts set it up as it's ready. Only once it
 * could, the page gets the class site.css styles it by; otherwise, it stays as core lays it out.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * The body's class for agents who chose the design, as AltConversation::BODY_CLASS.
	 *
	 * @type {string}
	 */
	const BODY = 'wporgsite-alt-conversation';

	/**
	 * The page's class once it's laid out, which site.css and the other scripts go by.
	 *
	 * @type {string}
	 */
	const LAYOUT = 'wporgsite-alt-layout';

	/**
	 * What the buttons say.
	 *
	 * @type {Object<string, string>}
	 */
	const STRINGS = {
		reply: 'Reply',
		note: 'Note',
		cc: 'Cc/Bcc',
	};

	/**
	 * Whether the agent scrolled, or did anything that may have, since the page opened.
	 *
	 * @type {boolean}
	 */
	let interacted = false;

	/**
	 * A new element.
	 *
	 * @param {string} tag       Tag.
	 * @param {string} className Classes.
	 * @return {HTMLElement} Element.
	 */
	function element( tag, className ) {
		const created = document.createElement( tag );
		created.className = className;

		return created;
	}

	/**
	 * A big button at the bottom, which clicks core's own, hidden in the pre-header.
	 *
	 * @param {HTMLElement} original  Core's button.
	 * @param {string}      className Classes.
	 * @param {string}      icon      Glyphicon's class.
	 * @param {string}      label     What it says.
	 * @param {string}      key       Its keyboard shortcut.
	 * @return {HTMLElement} Button.
	 */
	function bigButton( original, className, icon, label, key ) {
		const $button = $( '<button type="button"></button>' )
			.addClass( className )
			.attr( 'aria-keyshortcuts', key )
			.append(
				$( '<i class="glyphicon" aria-hidden="true"></i>' ).addClass(
					icon
				),
				document.createTextNode( ' ' + label + ' ' ),
				$( '<kbd class="wporgsite-key"></kbd>' ).text( key )
			);

		// Core's handlers show the form, and the other scripts' follow it, as for a click on core's own.
		$button.on( 'click', () => $( original ).trigger( 'click' ) );

		return $button.get( 0 );
	}

	/**
	 * Lays the conversation out, if everything it moves is there.
	 *
	 * @return {boolean} Whether it did.
	 */
	function layOut() {
		const layout = document.getElementById( 'conv-layout' );
		const header = document.getElementById( 'conv-layout-header' );
		const toolbar = document.getElementById( 'conv-toolbar' );
		const subject = document.getElementById( 'conv-subject' );
		const main = document.getElementById( 'conv-layout-main' );
		const customer = document.getElementById( 'conv-layout-customer' );
		const actions = toolbar && toolbar.querySelector( '.conv-actions' );
		const info = toolbar && toolbar.querySelector( '.conv-info' );
		const forms =
			subject && subject.querySelector( '.conv-action-wrapper' );
		const number = document.querySelector( '.conv-numnav strong' );
		const text =
			subject && subject.querySelector( '.conv-subjtext > span' );

		if (
			! layout ||
			! header ||
			! toolbar ||
			! main ||
			! customer ||
			! actions ||
			! info ||
			! forms ||
			! number ||
			! text
		) {
			return false;
		}

		// A column for all but the sidebar, so the pre-header and the buttons stay in view within it.
		const column = element( 'div', 'wporgsite-alt-column' );
		layout.insertBefore( column, layout.firstChild );
		column.append( toolbar, header, main );

		// The pre-header: the subject, which follows the subject block's as it's edited there, the tags, and the actions.
		const title = element( 'div', 'wporgsite-alt-subject' );
		const sync = () => {
			title.textContent = text.textContent.trim();
			title.title = title.textContent;
		};
		sync();
		new window.MutationObserver( sync ).observe( text, {
			childList: true,
			characterData: true,
			subtree: true,
		} );
		toolbar.insertBefore( title, actions );

		const tags = document.getElementById( 'conv_tags' );
		if ( tags ) {
			toolbar.insertBefore( tags, actions );
		}

		// The sidebar's top: the number, the previous and next conversations, and the selectors.
		const section = element( 'div', 'wporgsite-alt-info' );
		const top = element( 'div', 'wporgsite-alt-info-top' );
		const copy = element( 'button', 'wporgsite-alt-number' );
		copy.type = 'button';
		copy.dataset.number = number.textContent.trim();
		copy.append(
			document.createTextNode( '#' + copy.dataset.number + ' ' ),
			element( 'i', 'glyphicon glyphicon-copy' )
		);
		top.append( copy );

		const nav = info.querySelector( '.conv-next-prev' );
		if ( nav ) {
			top.append( nav );
		}

		section.append( top, info );
		customer.insertBefore( section, customer.firstChild );

		// At the bottom: Reply and Note, and the forms they open in their place.
		const dock = element( 'div', 'wporgsite-alt-dock' );
		const buttons = element( 'div', 'wporgsite-alt-buttons' );
		const reply = actions.querySelector( '.conv-reply' );
		const note = actions.querySelector( '.conv-add-note' );
		if ( reply ) {
			buttons.append(
				bigButton(
					reply,
					'btn btn-primary btn-lg wporgsite-alt-reply',
					'glyphicon-share-alt',
					STRINGS.reply,
					'r'
				)
			);
		}
		if ( note ) {
			buttons.append(
				bigButton(
					note,
					'btn btn-default btn-lg wporgsite-alt-note',
					'glyphicon-edit',
					STRINGS.note,
					'n'
				)
			);
		}

		dock.append( buttons, forms );
		column.append( dock );

		document.documentElement.classList.add( LAYOUT );

		return true;
	}

	/**
	 * The conversation, which scrolls.
	 *
	 * @return {HTMLElement|null} Column.
	 */
	function scrollingColumn() {
		return document.querySelector( '.wporgsite-alt-column' );
	}

	/**
	 * Scrolls the conversation to its end, its newest thread; not when the address points at a thread.
	 */
	function scrollToEnd() {
		const scroller = scrollingColumn();
		if ( interacted || window.location.hash || ! scroller ) {
			return;
		}

		scroller.scrollTop = scroller.scrollHeight;
	}

	/**
	 * Adds the navigation's mark to the top of the left sidebar, which site.css shows while the navigation is away.
	 *
	 * @param {HTMLElement} navbar Navigation.
	 */
	function addBrand( navbar ) {
		const mark = navbar.querySelector( '.navbar-brand' );
		const sidebar = document.querySelector( '.sidebar-2col' );
		if ( ! mark || ! sidebar ) {
			return;
		}

		const brand = element( 'div', 'wporgsite-sidebar-brand' );
		brand.append( mark.cloneNode( true ) );
		sidebar.insertBefore( brand, sidebar.firstChild );
	}

	/**
	 * Adds Cc/Bcc to the start of the editor's toolbar, inside the form's box, rather than as a link over it; it clicks
	 * core's link, hidden, which shows the fields and goes away, and site.css hides it when core's link is gone.
	 */
	function addCc() {
		const link = document.getElementById( 'toggle-cc' );
		const group = document.querySelector(
			'.wporgsite-alt-dock .note-toolbar .note-btn-group'
		);
		if ( ! link || ! group ) {
			return;
		}

		const $button = $(
			'<button type="button" class="note-btn btn btn-default btn-sm wporgsite-cc-toggle" tabindex="-1" data-toggle="tooltip">Cc</button>'
		).attr( { title: STRINGS.cc, 'aria-label': STRINGS.cc } );

		$button.on( 'click', ( event ) => {
			event.preventDefault();
			$( link ).trigger( 'click' );
			$button.tooltip( 'hide' );
		} );

		group.insertBefore( $button.get( 0 ), group.firstChild );
		if ( 'function' === typeof $.fn.tooltip ) {
			$button.tooltip( { container: 'body' } );
		}
	}

	/**
	 * Gives site.css the sizes and color it lays the page out with: the navigation's, and the pre-header's.
	 *
	 * @param {HTMLElement} navbar Navigation.
	 */
	function measure( navbar ) {
		const root = document.documentElement.style;
		const toolbar = document.getElementById( 'conv-toolbar' );

		root.setProperty(
			'--wporgsite-navbar-height',
			navbar.offsetHeight + 'px'
		);
		root.setProperty(
			'--wporgsite-navbar-background',
			window.getComputedStyle( navbar ).backgroundColor
		);
		if ( toolbar ) {
			root.setProperty(
				'--wporgsite-preheader-height',
				toolbar.offsetHeight + 'px'
			);
		}
	}

	if (
		! document.body ||
		! document.body.classList.contains( BODY ) ||
		! layOut()
	) {
		return;
	}

	// The browser would put the page back where it was, rather than at the newest thread.
	if ( 'scrollRestoration' in window.history ) {
		window.history.scrollRestoration = 'manual';
	}

	const navbar = document.querySelector( '.navbar' );
	if ( navbar ) {
		measure( navbar );
		addBrand( navbar );
		window.addEventListener( 'resize', () => measure( navbar ) );
	}

	// The keys that scroll, like Page Down, scroll the conversation, unless something else has the focus.
	const scroller = scrollingColumn();
	scroller.tabIndex = -1;
	$( () => {
		const focused = scroller.ownerDocument.activeElement;
		if ( ! focused || focused === scroller.ownerDocument.body ) {
			scroller.focus( { preventScroll: true } );
		}
	} );

	[ 'wheel', 'touchstart', 'keydown', 'mousedown' ].forEach( ( type ) =>
		window.addEventListener(
			type,
			() => {
				interacted = true;
			},
			{ passive: true, once: true }
		)
	);

	// At the end, the conversation stays there as the bottom grows, a form opening or the reply getting longer, so it
	// doesn't cover the newest threads; elsewhere, it stays where the agent is reading. Scrolled from its top, the
	// navigation slides away.
	let atEnd = true;
	const onScroll = () => {
		atEnd =
			scroller.scrollTop + scroller.clientHeight >=
			scroller.scrollHeight - 2;
		document.documentElement.classList.toggle(
			'wporgsite-alt-scrolled',
			scroller.scrollTop > 0
		);
	};
	scroller.addEventListener( 'scroll', onScroll, { passive: true } );

	// Again as images load, which push the end further down, unless the agent is reading by then.
	$( () => {
		scrollToEnd();
		onScroll();

		// Core starts the editor after the page's scripts, as it's ready.
		window.setTimeout( addCc );

		// From now on, the navigation slides.
		window.requestAnimationFrame( () =>
			window.requestAnimationFrame( () =>
				document.documentElement.classList.add( 'wporgsite-alt-ready' )
			)
		);
	} );
	window.addEventListener( 'load', scrollToEnd );

	const dock = document.querySelector( '.wporgsite-alt-dock' );
	if ( dock && 'function' === typeof window.ResizeObserver ) {
		new window.ResizeObserver( () => {
			if ( atEnd ) {
				scroller.scrollTop = scroller.scrollHeight;
			}
		} ).observe( dock );
	}
} )( jQuery );
