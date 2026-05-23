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

		// Propagate the user's visible-relative rank to the standing card and
		// pill so all three "your rank" labels agree. Server initial render
		// uses the absolute rank (the user's position in the unfiltered active
		// list); the renumber loop above rewrites the in-list card to the
		// visible-relative rank under the current filter — mirror that here.
		syncFindMeRank();
	}

	function syncFindMeRank() {
		var card = document.getElementById( 'pledges-card-you' );
		if ( ! card || card.hidden ) {
			return;
		}
		var rankEl = card.querySelector( '.pledges-card-rank' );
		if ( ! rankEl ) {
			return;
		}
		var visibleRankText = rankEl.textContent;
		var visibleRankNum  = parseInt( visibleRankText, 10 );

		var standing = document.querySelector( '.pledges-standing-card-ranked' );
		if ( standing ) {
			var standingRankEl = standing.querySelector( '.pledges-card-rank' );
			if ( standingRankEl ) {
				standingRankEl.textContent = visibleRankText;
			}
		}

		var pill = document.querySelector( '.pledges-jump-pill' );
		if ( pill && ! isNaN( visibleRankNum ) ) {
			var pillRankEl = pill.querySelector( '.pledges-jump-rank' );
			if ( pillRankEl ) {
				pillRankEl.textContent = '#' + visibleRankNum;
			}
			// Server-provided localized template — JS rebuilds the aria-label
			// so screen readers also hear the updated rank.
			var ariaTpl = pill.dataset.ariaTemplate;
			if ( ariaTpl ) {
				pill.setAttribute( 'aria-label', ariaTpl.replace( '%d', String( visibleRankNum ) ) );
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

	/**
	 * Find Me — Option 1 (hide-on-overlap).
	 *
	 * The page renders, server-side, a "Your standing" card at the top and a
	 * floating "Jump to your card" pill at the bottom. Both indicate where
	 * the logged-in user sits in the ranking. When the user is *actually
	 * looking at* their in-list card (IntersectionObserver fires for
	 * #pledges-card-you), both indicators recede via the body class
	 * `is-on-me` so the page isn't showing the same identity three times.
	 *
	 * The 'ranked' server state renders two standing-card twins side by side:
	 * one ranked + one filtered-out, with the filtered-out twin hidden by
	 * default. When the sponsorship filter changes client-side to one that
	 * excludes the user, the in-list card's `hidden` attribute flips on, and
	 * this code swaps which standing-card twin is visible — so the user
	 * always has a "Clear filters" recovery affordance on the page instead
	 * of the whole block disappearing.
	 */
	var youCard       = document.getElementById( 'pledges-card-you' );
	var jumpPill      = document.querySelector( '.pledges-jump-pill' );
	var standingSect  = document.querySelector( '.pledges-standing' );
	var standingRank  = document.querySelector( '.pledges-standing-card-ranked' );
	var standingFiltd = document.querySelector( '.pledges-standing-card-filtered' );

	function refreshStandingVariant() {
		if ( ! youCard ) {
			return;
		}
		var hiddenByFilter = !! youCard.hidden;

		// Body class is the cross-cutting state flag (used by other rules too).
		document.body.classList.toggle( 'is-find-me-filtered', hiddenByFilter );

		// When the card flips to display:none, IntersectionObserver never
		// fires isIntersecting=false on it (there's no layout to observe), so
		// is-on-me can persist from before the filter change and continue
		// hiding the standing block. Clear it explicitly here.
		if ( hiddenByFilter ) {
			document.body.classList.remove( 'is-on-me' );
		}

		// Both twins live in the DOM. Toggle [hidden] on both so swaps work
		// in either direction (ranked → filtered AND filtered → ranked when
		// the user clears the filter through the toolbar).
		if ( standingRank ) {
			standingRank.hidden = hiddenByFilter;
		}
		if ( standingFiltd ) {
			standingFiltd.hidden = ! hiddenByFilter;
		}
		// CSS keys the eyebrow color + dot off the section's state modifier
		// class. Without this toggle the eyebrow would stay blueberry over
		// the gold filtered-out twin (or gold + missing-dot over the ranked
		// twin) when JS swaps which twin is visible.
		if ( standingSect ) {
			standingSect.classList.toggle( 'pledges-standing-ranked', ! hiddenByFilter );
			standingSect.classList.toggle( 'pledges-standing-filtered-out', hiddenByFilter );
		}
		// Pill mirrors the ranked-twin visibility — JS owns the [hidden] flag
		// so the server's initial render survives a client-side filter swap.
		if ( jumpPill ) {
			jumpPill.hidden = hiddenByFilter;
		}
	}

	// Re-evaluate after every filter change. The original apply() updates
	// card visibility; we layer the standing-variant swap on top.
	var originalApply = apply;
	apply = function () {
		originalApply.apply( this, arguments );
		refreshStandingVariant();
	};

	if ( youCard && 'IntersectionObserver' in window ) {
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				document.body.classList.toggle( 'is-on-me', entry.isIntersecting );
			} );
		}, {
			// Trigger before the card is fully on-screen so the standing card
			// fades out as the user approaches their row, not after they're
			// already staring at the duplicate.
			rootMargin: '-15% 0px -15% 0px',
			threshold: 0,
		} );
		io.observe( youCard );
	}

	if ( jumpPill && youCard ) {
		jumpPill.addEventListener( 'click', function ( e ) {
			// Let modified clicks (cmd/ctrl/shift/middle) navigate normally so
			// users can open the deep link in a new tab.
			if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return;
			}
			e.preventDefault();
			// Smooth-scroll the card to the middle of the viewport. block:'center'
			// matches the design — the card lands with neighbors visible above
			// and below for context, not flush against the top of the viewport.
			try {
				youCard.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			} catch ( err ) {
				youCard.scrollIntoView();
			}
			// Move focus into the card so screen readers announce it. Cards
			// aren't naturally focusable, so set tabindex on demand and clear
			// it on blur to keep the tab order untouched for keyboard users.
			youCard.setAttribute( 'tabindex', '-1' );
			try {
				youCard.focus( { preventScroll: true } );
			} catch ( err ) {
				youCard.focus();
			}
			youCard.addEventListener( 'blur', function onBlur() {
				youCard.removeAttribute( 'tabindex' );
				youCard.removeEventListener( 'blur', onBlur );
			} );
		} );
	}

	// Initial paint: sync the carry-forward hrefs once even when the user
	// hasn't toggled anything, so links rendered with no sponsorship query arg
	// inherit one if the page was loaded with ?sponsorship= in the URL.
	syncUrl();
	apply();
} )();
