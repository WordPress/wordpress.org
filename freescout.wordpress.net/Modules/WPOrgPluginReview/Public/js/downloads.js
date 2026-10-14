/**
 * Collects the download URLs of the plugins in review that the agent has open, in the order they opened them, for the
 * header's button that copies them all, to review the plugins in bulk with a local tool.
 *
 * Each page keeps its plugin's URL in the browser's storage, under a key of its own, so that pages opening at once
 * don't write over each other's, and removes it as it closes. It also holds a lock of the same name for as long as it's
 * open, which the browser lets go of however the page ends, even when it crashes, or closes without saying so: URLs
 * whose page doesn't hold its lock anymore are left out, and removed. Pages the worklists' Open buttons opened go in
 * the order they were opened, which their URL tells; the others in the order they loaded.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * What the keys of the pages' URLs start with, in the browser's storage; their locks are named the same.
	 *
	 * @type {string}
	 */
	const PREFIX = 'wporgpluginreview.download.';

	/**
	 * What the keys of the conversations' places in the order start with, in the tab's storage, followed by their path,
	 * so a page keeps its place when it's reloaded.
	 *
	 * @type {string}
	 */
	const PLACES = 'wporgpluginreview.download-place.';

	/**
	 * The parameters the worklists' Open buttons add to the conversations they open, as review.js's OPEN_PARAMS.
	 *
	 * @type {Object<string, string>}
	 */
	const OPEN_PARAMS = {
		batch: 'wporg_open_batch',
		order: 'wporg_open_order',
	};

	/**
	 * How long "Copied" shows, in milliseconds.
	 *
	 * @type {number}
	 */
	const COPIED_FOR = 1200;

	/**
	 * How often a visible page checks for pages that closed without saying so, in milliseconds.
	 *
	 * @type {number}
	 */
	const CHECK_EVERY = 5000;

	/**
	 * This page's key, different for every page that opens, even of the same conversation.
	 *
	 * @type {string}
	 */
	const PAGE_KEY =
		PREFIX +
		Date.now().toString( 36 ) +
		Math.random().toString( 36 ).slice( 2 );

	/**
	 * This page's URL and place, once its plugin is known.
	 *
	 * @type {Object|null}
	 */
	let entry = null;

	/**
	 * Whether this page holds its lock.
	 *
	 * @type {boolean}
	 */
	let locked = false;

	/**
	 * The URLs the header shows, which are the ones it copies.
	 *
	 * @type {string[]}
	 */
	let shown = [];

	/**
	 * Until when the header says they were copied, as a time in milliseconds.
	 *
	 * @type {number}
	 */
	let copiedUntil = 0;

	/**
	 * One of the browser's storages, if the page may use it.
	 *
	 * @param {string} name localStorage or sessionStorage.
	 * @return {Storage|null} Storage.
	 */
	function storage( name ) {
		try {
			return window[ name ] || null;
		} catch {
			return null;
		}
	}

	/**
	 * The browser's locks, which only secure pages have.
	 *
	 * @return {LockManager|null} Locks.
	 */
	function locks() {
		const manager = window.navigator.locks;

		return manager && manager.request && manager.query ? manager : null;
	}

	/**
	 * Where this page goes in the order: when the Open button that opened it was clicked, and in which position, or
	 * else when it loaded; first time this conversation loaded in this tab, so a reload keeps its place.
	 *
	 * @return {Object} Batch, a time in milliseconds, and order in it; 0 for pages that weren't opened together.
	 */
	function place() {
		const params = new URLSearchParams( window.location.search );
		const batch = Number( params.get( OPEN_PARAMS.batch ) );
		const order = Number( params.get( OPEN_PARAMS.order ) );
		const session = storage( 'sessionStorage' );
		const key = PLACES + window.location.pathname;

		let found = batch > 0 && order > 0 ? { batch, order } : null;
		if ( ! found && session ) {
			try {
				found = JSON.parse( session.getItem( key ) || 'null' );
			} catch {
				found = null;
			}
		}
		if ( ! found || ! ( Number( found.batch ) > 0 ) ) {
			found = { batch: Date.now(), order: 0 };
		}

		try {
			session.setItem( key, JSON.stringify( found ) );
		} catch {
			// Then a reload puts it last.
		}

		return { batch: Number( found.batch ), order: Number( found.order ) };
	}

	/**
	 * The pages' URLs and places, as kept.
	 *
	 * @return {Object[]} Each with its key.
	 */
	function entries() {
		const local = storage( 'localStorage' );
		const found = [];
		if ( ! local ) {
			return found;
		}

		for ( let i = 0; i < local.length; i++ ) {
			const key = local.key( i );
			if ( ! key || ! key.startsWith( PREFIX ) ) {
				continue;
			}

			try {
				const item = JSON.parse( local.getItem( key ) || 'null' );
				if ( item && 'string' === typeof item.url && item.url ) {
					found.push( { ...item, key } );
				}
			} catch {
				// Not one of ours.
			}
		}

		return found;
	}

	/**
	 * The ones whose page is still open, removing the others.
	 *
	 * @param {Object[]} found Entries.
	 * @return {Promise<Object[]>} Entries.
	 */
	function open( found ) {
		const manager = locks();
		if ( ! manager ) {
			return Promise.resolve( found );
		}

		return manager
			.query()
			.then( ( state ) => {
				const held = new Set(
					( state.held || [] ).map( ( lock ) => lock.name )
				);
				const local = storage( 'localStorage' );

				return found.filter( ( item ) => {
					if ( held.has( item.key ) ) {
						return true;
					}
					if ( local ) {
						local.removeItem( item.key );
					}
					return false;
				} );
			} )
			.catch( () => found );
	}

	/**
	 * Updates the header's buttons: shown once there are URLs, with how many.
	 */
	function refresh() {
		const $items = $( '.wporg-downloads' );
		if ( ! $items.length ) {
			return;
		}

		open( entries() ).then( ( found ) => {
			found.sort(
				( a, b ) =>
					Number( a.batch ) - Number( b.batch ) ||
					Number( a.order ) - Number( b.order ) ||
					Number( a.at ) - Number( b.at )
			);
			shown = [ ...new Set( found.map( ( item ) => item.url ) ) ];

			const strings = $items.first().data( 'strings' ) || {};
			let label = String(
				( 1 === shown.length ? strings.one : strings.many ) || ''
			).replace( ':count', String( shown.length ) );
			if ( Date.now() < copiedUntil ) {
				label = String( strings.copied || label );
			}

			$items.toggleClass( 'hidden', ! shown.length );
			$items.find( '.wporg-downloads-label' ).text( label );
		} );
	}

	/**
	 * Keeps this page's URL, while it holds its lock.
	 */
	function keep() {
		const local = storage( 'localStorage' );
		const store = () => {
			if ( entry && local ) {
				try {
					local.setItem( PAGE_KEY, JSON.stringify( entry ) );
				} catch {
					// Then it isn't in the list.
				}
			}
			refresh();
		};
		const manager = locks();

		if ( ! manager || locked ) {
			store();
			return;
		}

		// Held until the page ends: the promise never settles.
		manager
			.request( PAGE_KEY, () => {
				locked = true;
				store();
				return new Promise( () => {} );
			} )
			.catch( store );
	}

	/**
	 * Removes this page's URL, as it closes.
	 */
	function forget() {
		const local = storage( 'localStorage' );
		if ( local ) {
			local.removeItem( PAGE_KEY );
		}
	}

	// The Plugin Review panel tells the plugin once WordPress.org did, which may take a while.
	$( document ).on( 'wporgpluginreview:plugin', ( event, plugin ) => {
		if ( ! plugin || ! plugin.download_url ) {
			return;
		}

		entry = {
			url: String( plugin.download_url ),
			...place(),
			at: Date.now(),
		};
		keep();
	} );

	// Closing the page, or leaving it, removes its URL; coming back to it, as with the Back button, puts it back.
	window.addEventListener( 'pagehide', forget );
	window.addEventListener( 'pageshow', ( event ) => {
		if ( event.persisted && entry ) {
			keep();
		}
	} );

	// Other tabs' changes.
	window.addEventListener( 'storage', ( event ) => {
		if ( null === event.key || String( event.key ).startsWith( PREFIX ) ) {
			refresh();
		}
	} );

	// Copies them in quotes, separated by pipes, as the local tool reads them.
	$( document ).on( 'click', '.wporg-downloads-copy', ( event ) => {
		event.preventDefault();

		const clipboard = window.navigator.clipboard;
		if ( ! clipboard || ! shown.length ) {
			return;
		}

		clipboard
			.writeText( '"' + shown.join( '|' ) + '"' )
			.then( () => {
				copiedUntil = Date.now() + COPIED_FOR;
				refresh();
				setTimeout( refresh, COPIED_FOR );
			} )
			.catch( () => {} );
	} );

	// Forgets them all, the open pages' too.
	$( document ).on( 'click', '.wporg-downloads-clear', ( event ) => {
		event.preventDefault();

		const local = storage( 'localStorage' );
		if ( local ) {
			Object.keys( local )
				.filter( ( key ) => key.startsWith( PREFIX ) )
				.forEach( ( key ) => local.removeItem( key ) );
		}
		refresh();
	} );

	$( () => {
		if ( ! $( '.wporg-downloads' ).length ) {
			return;
		}

		refresh();
		setInterval( () => {
			if ( 'visible' === document.visibilityState ) {
				refresh();
			}
		}, CHECK_EVERY );
		document.addEventListener( 'visibilitychange', refresh );
	} );
} )( jQuery );
