/**
 * The sidebar's Collapse button, on the line between it and the page, as on desktops. Collapsed, the sidebar is as wide
 * as its icons, and opens over the page while the agent's pointer or keyboard is in it; the browser remembers which it
 * was. site.css does the rest, and the module's script in the page's head collapses it before it shows.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * Where the browser remembers it, as WPOrgSiteServiceProvider::SIDEBAR_KEY.
	 *
	 * @type {string}
	 */
	const KEY = 'wporgsite.sidebar';

	/**
	 * The page's class while it's collapsed.
	 *
	 * @type {string}
	 */
	const COLLAPSED = 'wporgsite-sidebar-collapsed';

	/**
	 * The page's class once changes may animate: not as it loads, which shows it as it was left.
	 *
	 * @type {string}
	 */
	const ANIMATE = 'wporgsite-sidebar-animate';

	/**
	 * How long the sidebar takes to collapse or expand, as in site.css, in milliseconds.
	 *
	 * @type {number}
	 */
	const DURATION = 250;

	/**
	 * What the button says.
	 *
	 * @type {Object<string, string>}
	 */
	const STRINGS = {
		collapse: 'Collapse sidebar',
		expand: 'Expand sidebar',
	};

	/**
	 * Backgrounds for the title's initial, each with white text that meets WCAG AA; a title gets the same one every time.
	 *
	 * @type {string[]}
	 */
	const COLORS = [
		'#3858e9',
		'#007017',
		'#b32d2e',
		'#8a5a00',
		'#6b4fbb',
		'#00708a',
		'#c2185b',
		'#50575e',
	];

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
	 * Whether the sidebar is collapsed.
	 *
	 * @return {boolean} Whether it is.
	 */
	function isCollapsed() {
		return document.documentElement.classList.contains( COLLAPSED );
	}

	/**
	 * Remembers whether the sidebar is collapsed; expanded, the default, isn't stored.
	 *
	 * @param {boolean} collapsed Whether it is.
	 */
	function remember( collapsed ) {
		const local = storage();
		if ( ! local ) {
			return;
		}

		try {
			if ( collapsed ) {
				local.setItem( KEY, 'collapsed' );
			} else {
				local.removeItem( KEY );
			}
		} catch {
			// Then it's only for this page.
		}
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
	 * Gives the sidebar's title its initial, which shows in its place while the sidebar is collapsed: the mailbox's,
	 * the user's, or the title's own, like Settings, each on its own color.
	 *
	 * @param {jQuery} $sidebar Sidebar.
	 */
	function addInitial( $sidebar ) {
		const $title = $sidebar.children( '.sidebar-title' ).first();
		if ( ! $title.length ) {
			return;
		}

		let name = $title
			.find( '.sidebar-title-real, .dropdown-toggle' )
			.first()
			.text()
			.trim();
		if ( ! name ) {
			name = $title.clone().children().remove().end().text().trim();
		}
		if ( ! name ) {
			return;
		}

		let hash = 0;
		for ( const character of name ) {
			hash = ( hash + character.codePointAt( 0 ) ) % COLORS.length;
		}

		$title
			.attr(
				'data-wporgsite-initial',
				Array.from( name )[ 0 ].toLocaleUpperCase()
			)
			.css( '--wporgsite-initial-color', COLORS[ hash ] );
	}

	/**
	 * Updates what the button says, and its arrow, for whether the sidebar is collapsed.
	 *
	 * @param {jQuery} $button Button.
	 */
	function updateButton( $button ) {
		const collapsed = isCollapsed();
		const label = collapsed ? STRINGS.expand : STRINGS.collapse;

		$button.attr( {
			'aria-label': label,
			'aria-expanded': String( ! collapsed ),
		} );
		setTitle( $button, label );
		$button
			.children( '.glyphicon' )
			.toggleClass( 'glyphicon-chevron-left', ! collapsed )
			.toggleClass( 'glyphicon-chevron-right', collapsed );
	}

	// In case the head's script couldn't.
	try {
		const local = storage();
		if ( local && 'collapsed' === local.getItem( KEY ) ) {
			document.documentElement.classList.add( COLLAPSED );
		}
	} catch {
		// Then it's expanded.
	}

	$( () => {
		const $sidebar = $( '.sidebar-2col' ).first();
		if ( ! $sidebar.length ) {
			return;
		}

		addInitial( $sidebar );

		const $button = $(
			'<button type="button" class="wporgsite-sidebar-toggle" data-toggle="tooltip" data-placement="right">' +
				'<i class="glyphicon" aria-hidden="true"></i>' +
				'</button>'
		);
		$sidebar.prepend( $button );
		updateButton( $button );

		// On hover only: a clicked button keeps the focus, which would keep it showing after the sidebar closes.
		const hasTooltips = 'function' === typeof $.fn.tooltip;
		if ( hasTooltips ) {
			$button.tooltip( { container: 'body', trigger: 'hover' } );
		}

		$button.on( 'click', () => {
			document.documentElement.classList.add( ANIMATE );
			document.documentElement.classList.toggle( COLLAPSED );
			remember( isCollapsed() );
			updateButton( $button );

			// Shows what it says now, if it's showing: from the keyboard, it would stay.
			if ( hasTooltips && $button.get( 0 ).matches( ':hover' ) ) {
				$button.tooltip( 'show' );
			}

			// The page is wider or narrower: what measures it, like the pinned status selector, measures it again.
			window.setTimeout(
				() => window.dispatchEvent( new window.Event( 'resize' ) ),
				DURATION + 50
			);
		} );

		// From now on, changes animate.
		window.requestAnimationFrame( () =>
			window.requestAnimationFrame( () =>
				document.documentElement.classList.add( ANIMATE )
			)
		);
	} );
} )( jQuery );
