/*
 * Page Pledges — Five for the Future redesign Page 1.
 * Client-side filter + sort over the cards already rendered server-side.
 *
 * Filters: sponsorship (all/independent/sponsored). Time window is currently a
 * UI-only placeholder until contribution-window aggregation ships.
 *
 * Sorts: weighted (default), raw, alpha. Non-destructive — all cards stay in DOM,
 * order is rewritten via Array.prototype.sort and re-appended.
 */

( function () {
	'use strict';

	var grid = document.getElementById( 'pledges-grid' );
	if ( ! grid ) {
		return;
	}

	var cards = Array.prototype.slice.call(
		grid.querySelectorAll( '.pledges-card' )
	);
	var resultCount = document.getElementById( 'pledges-result-count' );
	var emptyState = document.getElementById( 'pledges-empty' );
	var hiwBanner = document.getElementById( 'pledges-howitworks' );
	var hiwDismiss = hiwBanner && hiwBanner.querySelector( '.pledges-howitworks-dismiss' );
	var hiwKey = 'wporg-pledges-hiw-dismissed';

	var state = {
		window: '30',
		sponsorship: 'independent',
		sort: 'weighted',
	};

	/**
	 * Restore "How this page works" banner dismiss state from localStorage.
	 */
	if ( hiwBanner && hiwDismiss ) {
		try {
			if ( window.localStorage.getItem( hiwKey ) === '1' ) {
				hiwBanner.classList.add( 'is-dismissed' );
			}
		} catch ( e ) {
			/* ignore */
		}

		hiwDismiss.addEventListener( 'click', function () {
			hiwBanner.classList.add( 'is-dismissed' );
			try {
				window.localStorage.setItem( hiwKey, '1' );
			} catch ( e ) {
				/* ignore */
			}
		} );
	}

	/**
	 * Apply filter + sort + render counts.
	 */
	function apply() {
		// Sort.
		var sortKey = state.sort;
		var sorted = cards.slice().sort( function ( a, b ) {
			if ( sortKey === 'weighted' ) {
				return parseInt( b.dataset.weighted || '0', 10 ) - parseInt( a.dataset.weighted || '0', 10 );
			}
			if ( sortKey === 'raw' ) {
				return parseInt( b.dataset.raw || '0', 10 ) - parseInt( a.dataset.raw || '0', 10 );
			}
			if ( sortKey === 'alpha' ) {
				return ( a.dataset.name || '' ).localeCompare( b.dataset.name || '' );
			}
			return 0;
		} );

		// Re-append in sorted order.
		sorted.forEach( function ( card ) {
			grid.appendChild( card );
		} );

		// Filter (visibility) + re-rank visible cards in sorted order.
		var visibleCount = 0;
		sorted.forEach( function ( card ) {
			var matches = true;
			if ( state.sponsorship !== 'all' && card.dataset.sponsorship !== state.sponsorship ) {
				matches = false;
			}
			card.hidden = ! matches;
			if ( matches ) {
				visibleCount++;
				var rankEl = card.querySelector( '.pledges-card-rank' );
				if ( rankEl ) {
					rankEl.textContent = String( visibleCount ).padStart( 2, '0' );
				}
			}
		} );

		// Empty state.
		if ( emptyState ) {
			emptyState.hidden = visibleCount > 0;
		}

		// Result count.
		if ( resultCount ) {
			resultCount.textContent = visibleCount;
		}
	}

	/**
	 * Wire BUTTON chips for client-side filtering. <a> chips (time window)
	 * navigate via URL and reload — handled by the browser, not JS.
	 */
	document.querySelectorAll( 'button.pledges-chip' ).forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			var filter = chip.dataset.filter;
			var value = chip.dataset.value;
			if ( ! filter ) {
				return;
			}
			document
				.querySelectorAll( 'button.pledges-chip[data-filter="' + filter + '"]' )
				.forEach( function ( c ) {
					c.classList.remove( 'is-on' );
				} );
			chip.classList.add( 'is-on' );
			state[ filter ] = value;
			apply();
		} );
	} );

	/**
	 * Empty-state reset.
	 */
	var resetBtn = document.querySelector( '.pledges-empty-reset' );
	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', function () {
			document
				.querySelectorAll( '.pledges-chip[data-filter="sponsorship"]' )
				.forEach( function ( c ) {
					c.classList.toggle( 'is-on', c.dataset.value === 'all' );
				} );
			state.sponsorship = 'all';
			apply();
		} );
	}

	apply();
} )();
