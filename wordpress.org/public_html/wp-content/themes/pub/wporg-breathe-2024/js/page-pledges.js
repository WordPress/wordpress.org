/*
 * Page Pledges — Five for the Future redesign Page 1.
 * Client-side filter over the cards already rendered server-side.
 *
 * Filters: sponsorship (all/independent/sponsored) and time window (30/90/180).
 * Both groups are URL-driven (?sponsorship=, ?window=) so the view is
 * bookmarkable. The time-window chips reload the page so the aggregator runs
 * against the new window; the sponsorship chips are intercepted here for an
 * instant in-place re-filter and the URL is kept in sync via replaceState.
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
	var hiwBanner = document.getElementById( 'pledges-howitworks' );
	var hiwDismiss = hiwBanner && hiwBanner.querySelector( '.pledges-howitworks-dismiss' );
	var hiwKey = 'wporg-pledges-hiw-dismissed';

	var SPONSORSHIP_DEFAULT = 'independent';
	var SPONSORSHIP_ALLOWED = [ 'all', 'independent', 'sponsored' ];

	// Seed state from the server-rendered is-on chip so PHP stays the single
	// source of truth for the default. Allow-list the value so malformed markup
	// (missing data-value, injected chip) can't put state into an undefined
	// limbo that hides every card.
	var initialChip = document.querySelector(
		'.pledges-chip.is-on[data-filter="sponsorship"]'
	);
	var initialValue = initialChip ? initialChip.dataset.value : SPONSORSHIP_DEFAULT;
	if ( SPONSORSHIP_ALLOWED.indexOf( initialValue ) === -1 ) {
		initialValue = SPONSORSHIP_DEFAULT;
	}
	var state = {
		sponsorship: initialValue,
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
		if ( emptyState ) {
			emptyState.hidden = ( activeVisible + inactiveVisible ) > 0;
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
	 * Keep the URL in sync with the current sponsorship filter, and carry the
	 * value forward into sibling navigational links (time-window chips, the
	 * show_inactive toggle) so a subsequent full-page reload preserves it.
	 */
	function syncUrl() {
		try {
			var current = new URL( window.location.href );
			if ( state.sponsorship && state.sponsorship !== SPONSORSHIP_DEFAULT ) {
				current.searchParams.set( 'sponsorship', state.sponsorship );
			} else {
				current.searchParams.delete( 'sponsorship' );
			}
			window.history.replaceState( null, '', current.toString() );
		} catch ( e ) {
			/* ignore; URL hygiene is best-effort */
		}

		// Rewrite hrefs on full-page-reload links so the next navigation keeps
		// the sponsorship state. CSS-only chips (sponsorship) re-route through
		// our click handler and don't need rewriting here.
		var carryLinks = document.querySelectorAll(
			'a.pledges-chip[data-filter="window"], .pledges-inactive-toggle a[href], .pledges-empty-extra a[href]'
		);
		carryLinks.forEach( function ( link ) {
			try {
				var u = new URL( link.href, window.location.origin );
				if ( state.sponsorship && state.sponsorship !== SPONSORSHIP_DEFAULT ) {
					u.searchParams.set( 'sponsorship', state.sponsorship );
				} else {
					u.searchParams.delete( 'sponsorship' );
				}
				link.href = u.toString();
			} catch ( e ) {
				/* ignore malformed hrefs */
			}
		} );
	}

	/**
	 * Set the active sponsorship value, refresh chip state, sync the URL, and
	 * re-apply the visibility filter.
	 */
	function setSponsorship( value ) {
		if ( SPONSORSHIP_ALLOWED.indexOf( value ) === -1 ) {
			value = SPONSORSHIP_DEFAULT;
		}
		state.sponsorship = value;
		document
			.querySelectorAll( '.pledges-chip[data-filter="sponsorship"]' )
			.forEach( function ( c ) {
				var isActive = c.dataset.value === value;
				c.classList.toggle( 'is-on', isActive );
				if ( isActive ) {
					c.setAttribute( 'aria-current', 'true' );
				} else {
					c.removeAttribute( 'aria-current' );
				}
			} );
		syncUrl();
		apply();
	}

	/**
	 * Intercept sponsorship anchor chips so the filter applies instantly
	 * without a full page reload. The href stays correct so middle-click and
	 * "open in new tab" still produce a shareable URL.
	 */
	document
		.querySelectorAll( 'a.pledges-chip[data-filter="sponsorship"]' )
		.forEach( function ( chip ) {
			chip.addEventListener( 'click', function ( e ) {
				// Let modified clicks (cmd/ctrl/shift/middle) navigate normally
				// so users can open the filtered view in a new tab.
				if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
					return;
				}
				e.preventDefault();
				setSponsorship( chip.dataset.value );
			} );
		} );

	/**
	 * Empty-state reset. Rendered as an anchor pointing at the cleared-filter URL
	 * so JS-disabled visitors get a working escape hatch; intercept here for the
	 * instant in-place re-filter when JS is available. Modifier clicks fall
	 * through so "open in new tab" still produces a shareable URL.
	 */
	var resetLink = document.querySelector( '.pledges-empty-reset' );
	if ( resetLink ) {
		resetLink.addEventListener( 'click', function ( e ) {
			if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return;
			}
			e.preventDefault();
			setSponsorship( 'all' );
		} );
	}

	// Initial paint: sync the carry-forward hrefs once even when the user
	// hasn't toggled anything, so links rendered with no sponsorship query arg
	// inherit one if the page was loaded with ?sponsorship= in the URL.
	syncUrl();
	apply();
} )();
