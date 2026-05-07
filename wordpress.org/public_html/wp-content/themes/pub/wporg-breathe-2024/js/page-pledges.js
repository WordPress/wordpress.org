/*
 * Page Pledges — Five for the Future redesign Page 1.
 * Client-side filter over the cards already rendered server-side.
 *
 * Filters: sponsorship (all/independent/sponsored). The time window is server-side
 * (?window=30|90|180); chips are <a> links that reload the page so the aggregator
 * runs against the new window.
 *
 * Visibility is non-destructive — all cards stay in DOM, hidden via the `hidden`
 * attribute, and visible cards are re-ranked from 01 on every state change.
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
	var resultPhrase = document.getElementById( 'pledges-result-phrase' );
	var emptyState = document.getElementById( 'pledges-empty' );
	var divider = grid.querySelector( '.pledges-inactive-divider' );
	// When the empty state is showing, the empty-state-extra link already offers
	// "Show inactive contributors" — the standalone toggle below would just
	// duplicate the same CTA, so hide it whenever the empty state is on.
	var inactiveToggle = document.querySelector( '.pledges-inactive-toggle' );
	var hiwBanner = document.getElementById( 'pledges-howitworks' );
	var hiwDismiss = hiwBanner && hiwBanner.querySelector( '.pledges-howitworks-dismiss' );
	var hiwKey = 'wporg-pledges-hiw-dismissed';

	var state = {
		sponsorship: 'independent',
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
	 * Apply filter + render counts.
	 */
	function apply() {
		// Filter (visibility) + re-rank visible cards. Server-side order is preserved
		// (already sorted by weighted_volume desc in page-pledges.php).
		// `activeVisible` drives the result count beneath "active contributors";
		// `rankCounter` numbers every visible card sequentially across the divider.
		var activeVisible = 0;
		var inactiveVisible = 0;
		var rankCounter = 0;
		cards.forEach( function ( card ) {
			var matches = true;
			if ( state.sponsorship !== 'all' && card.dataset.sponsorship !== state.sponsorship ) {
				matches = false;
			}
			card.hidden = ! matches;
			if ( matches ) {
				rankCounter++;
				var rankEl = card.querySelector( '.pledges-card-rank' );
				if ( rankEl ) {
					rankEl.textContent = String( rankCounter ).padStart( 2, '0' );
				}
				if ( card.dataset.active === '1' ) {
					activeVisible++;
				} else {
					inactiveVisible++;
				}
			}
		} );

		// Hide the inactive divider when the current filter empties its section,
		// so users don't see an orphan "N contributors with no verified..." label.
		if ( divider ) {
			divider.hidden = inactiveVisible === 0;
		}

		// Empty state shows only when nothing is visible at all.
		var showEmpty = ( activeVisible + inactiveVisible ) === 0;
		if ( emptyState ) {
			emptyState.hidden = ! showEmpty;
		}

		// Hide the standalone "Show inactive" toggle when the empty state takes
		// over — the empty state already carries the same CTA inline.
		if ( inactiveToggle ) {
			inactiveToggle.hidden = showEmpty;
		}

		// Result count is "active contributors" — inactive cards don't belong here.
		if ( resultCount ) {
			resultCount.textContent = activeVisible;
		}

		// Rebuild the phrase so the singular/plural form follows the live visible
		// count instead of being frozen at the server-rendered $active_count.
		// (Two-form approximation; for fully plural-aware locales we'd swap this
		// for wp.i18n._n once the script depends on @wordpress/i18n.)
		if ( resultPhrase ) {
			var total = parseInt( resultPhrase.dataset.total || '0', 10 );
			var template = activeVisible === 1
				? resultPhrase.dataset.singular
				: resultPhrase.dataset.plural;
			if ( template ) {
				resultPhrase.textContent = template.replace( '%d', String( total ) );
			}
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
