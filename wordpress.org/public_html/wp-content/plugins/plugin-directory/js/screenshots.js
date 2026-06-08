/**
 * Show-all reveal for the [wporg-plugins-screenshots] shortcode.
 *
 * Every figure is in the DOM from first paint — masonry has already
 * balanced columns and never needs to re-balance. Collapse / expand
 * happens entirely on the wrap via `max-height`:
 *
 *   - The collapsed cap is a fixed `max-height: 32rem` declared in
 *     CSS, applied while `.is-revealed` is absent. No JS measurement
 *     on init: lazy figures past the fold report `naturalWidth: 0`
 *     until they decode, which would have made any measured cap equal
 *     to the unclipped natural height.
 *   - On click, this script measures the wrap's full `scrollHeight`,
 *     writes it into `--full-height`, then flips the `is-revealed`
 *     class so the CSS transition animates from the fixed cap to the
 *     measured target.
 *   - After the transition, the cap is dropped so a viewport resize
 *     never re-clips the now-revealed gallery.
 *
 * Click does NOT add or remove any figures — they're already in
 * place. The user sees the wrap unfold, fade out, button fade out;
 * no reshuffle.
 */

( function () {
	var EXIT_MS = 220;

	/**
	 * Marks a figure as broken when its <img> failed to load or arrived
	 * with zero natural dimensions.
	 *
	 * Without this the figure still paints its 1px hairline border and
	 * reads as an empty rectangle in the grid — which has happened
	 * locally with loginpress when the plugin author uploaded a broken
	 * screenshot.
	 *
	 * Critically: only treat an img as broken when it has actually
	 * settled (load or error event already fired, i.e. `complete` is
	 * true) AND has zero natural dimensions. A lazy-loaded img past the
	 * fold reports `naturalWidth: 0` until it decodes — marking those
	 * as broken would hide every collapsed figure permanently and leave
	 * only the first nine visible after the Show-all click.
	 *
	 * @param {HTMLImageElement} img Screenshot image element to inspect.
	 */
	function markBroken( img ) {
		if ( img.complete && ( img.naturalWidth === 0 || img.naturalHeight === 0 ) ) {
			var fig = img.closest( 'figure.wp-block-image' );
			if ( fig ) {
				fig.classList.add( 'is-broken-tile' );
			}
		}
	}

	/**
	 * Wires up broken-image detection for every screenshot in the
	 * gallery and schedules a delayed re-sweep for stragglers that the
	 * load / error events miss.
	 *
	 * @param {Document|HTMLElement} root Document or section element to scan.
	 */
	function hideBrokenFigures( root ) {
		var imgs = root.querySelectorAll( '#screenshots .wp-block-image img' );
		imgs.forEach( function ( img ) {
			if ( img.complete ) {
				markBroken( img );
				return;
			}
			img.addEventListener( 'load', function () { markBroken( img ); }, { once: true } );
			img.addEventListener( 'error', function () {
				var fig = img.closest( 'figure.wp-block-image' );
				if ( fig ) {
					fig.classList.add( 'is-broken-tile' );
				}
			}, { once: true } );
		} );

		/*
		 * Some screenshots end up "loaded" by the browser but with zero
		 * natural dimensions — typically Photon returning an empty 200,
		 * or a partial / pending response that the network stack never
		 * settles. The load / error events don't always fire for those,
		 * so re-sweep after a short delay and hide anything that's still
		 * degenerate. The `markBroken` guard against `!complete` keeps
		 * lazy figures past the fold safe from this sweep.
		 */
		setTimeout( function () {
			imgs.forEach( markBroken );
		}, 3000 );
	}

	/**
	 * Keeps the full-size anchor as a no-JS fallback only.
	 *
	 * Core opens the lightbox from img/button handlers but does not cancel
	 * navigation on a wrapping anchor, so cancel the anchor default without
	 * stopping propagation.
	 *
	 * @param {Document|HTMLElement} root Document or section element to scan.
	 */
	function disableFallbackNavigation( root ) {
		var links = root.querySelectorAll(
			'#screenshots figure.wp-block-image > a.plugin-screenshots__fallback-link[href]'
		);

		links.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
			} );
		} );
	}

	/*
	 * `--collapse-height` is set in CSS as a constant (`32rem` ≈ three
	 * rows of typical landscape thumbs). We don't measure the natural
	 * bottom of the ninth figure on init, because lazy figures past the
	 * fold report `naturalWidth: 0` and contribute nothing to the
	 * measured height — that produced a collapse-height effectively
	 * equal to the unclipped natural height, so the collapse did
	 * nothing. A fixed `32rem` works across every screenshot count and
	 * every aspect-ratio mix because:
	 *   - small galleries (≤ 9) skip the reveal entirely (no button
	 *     rendered server-side);
	 *   - large galleries always have figures past the cap, so the fade
	 *     and the button always sit over real content.
	 */

	/**
	 * Expands the gallery wrap from its collapsed `max-height` cap to
	 * the full content height and removes the Show-all button.
	 *
	 * @param {HTMLElement} reveal_root  The wrapper carrying the `is-revealed` toggle.
	 * @param {HTMLElement} gallery_wrap The clipped container that holds every figure.
	 * @param {HTMLElement} button       The Show-all button to detach after the transition.
	 */
	function reveal( reveal_root, gallery_wrap, button ) {
		/*
		 * Bump lazy figures to eager so the browser starts decoding them
		 * immediately. We DON'T wait on the loads — that would stall the
		 * click for seconds on a 33-screenshot gallery and the user sees
		 * the button vanish with no visible reaction. The transition
		 * runs against a generously-sized pixel target; lazy figures
		 * slot in as they decode (overflow goes `visible` after the
		 * transition, so the wrap can grow past the target without
		 * re-clipping).
		 */
		gallery_wrap.querySelectorAll( 'figure.wp-block-image img[loading="lazy"]' ).forEach( function ( img ) {
			img.loading = 'eager';
		} );

		// Lock the transition start in pixels so the keyframe has a concrete `from`.
		var startH         = gallery_wrap.getBoundingClientRect().height;
		var prevTransition = gallery_wrap.style.transition;
		gallery_wrap.style.transition = 'none';
		gallery_wrap.style.maxHeight  = startH + 'px';
		void gallery_wrap.offsetHeight;
		gallery_wrap.style.transition = prevTransition;

		/*
		 * Use the larger of `scrollHeight` (already-decoded figures) and
		 * an estimate based on natural ratios. The estimate has to be
		 * generous because lazy figures past the fold report
		 * `naturalWidth: 0` until they decode and contribute zero to
		 * `scrollHeight`. 600 px / row covers tall portrait shots
		 * (full-page admin screenshots common in plugins like
		 * loginpress), which are the worst case for under-estimation;
		 * over-estimating just makes the transition target larger and
		 * cosmetically inconsequential because we drop the cap right
		 * after.
		 */
		var figureCount = gallery_wrap.querySelectorAll( 'figure.wp-block-image' ).length;
		var estimate    = Math.ceil( figureCount / 3 ) * 600 + 400;
		var fullH       = Math.max( gallery_wrap.scrollHeight, estimate );

		reveal_root.classList.add( 'is-revealed' );
		gallery_wrap.style.setProperty( '--full-height', fullH + 'px' );
		gallery_wrap.style.maxHeight = fullH + 'px';

		/*
		 * Release the cap once the transition lands. We wire up both the
		 * `transitionend` listener AND a fallback `setTimeout` because
		 * the event drops on certain edge cases (transition interrupted
		 * by a resize, browser tab in the background, `prefers-reduced-
		 * motion: reduce` collapsing the duration to zero). Without the
		 * fallback the wrap stays clipped at `fullH` and a tall gallery
		 * (loginpress with 33 portrait screenshots ≈ 7000 px tall) shows
		 * only the part that fits inside the estimate.
		 */
		var released = false;
		var release  = function () {
			if ( released ) {
				return;
			}
			released = true;
			gallery_wrap.removeEventListener( 'transitionend', onEnd );
			/*
			 * Release the cap entirely. From here the wrap can grow to
			 * whatever height the lazy figures end up needing —
			 * `overflow: visible` stops the clip too.
			 */
			gallery_wrap.style.maxHeight = 'none';
			gallery_wrap.style.removeProperty( '--full-height' );
			gallery_wrap.style.removeProperty( '--collapse-height' );
			gallery_wrap.style.overflow = 'visible';
		};
		var onEnd = function ( event ) {
			if ( 'max-height' !== event.propertyName ) {
				return;
			}
			release();
		};
		gallery_wrap.addEventListener( 'transitionend', onEnd );
		// Fallback: 1.2 s is comfortably longer than the CSS transition duration.
		setTimeout( release, 1200 );

		button.setAttribute( 'aria-expanded', 'true' );
		// Detach the button after its opacity transition for a clean a11y tree.
		setTimeout( function () {
			button.remove();
		}, EXIT_MS );
	}

	/**
	 * Decides whether the Show-all button is meaningful for the current
	 * masonry height and toggles `has-no-overflow` accordingly.
	 *
	 * PHP renders the button whenever screenshot count > 9, but the
	 * natural masonry height for short landscape thumbs can fit under
	 * the cap — in which case nothing is hidden and the button is
	 * meaningless. Hide the reveal affordance when there's no overflow.
	 *
	 * We compare against the CSS cap (32rem = 32 × root font-size)
	 * rather than `clientHeight`, because once we add `has-no-overflow`
	 * the cap goes to `none` and `clientHeight` equals `scrollHeight`
	 * forever — the state would lock and never re-enable when lazy
	 * figures decode.
	 *
	 * @param {HTMLElement} reveal_root  The wrapper carrying the toggle classes.
	 * @param {HTMLElement} gallery_wrap The clipped container holding the figures.
	 */
	function syncOverflowState( reveal_root, gallery_wrap ) {
		var rootFs = parseFloat( getComputedStyle( document.documentElement ).fontSize ) || 16;
		var capPx  = 32 * rootFs;
		/*
		 * Add a meaningful buffer: hide the button when only a sliver of
		 * content sits past the cap (≤ 200 px). Otherwise the user sees
		 * all the figures already, clicks the button, and almost nothing
		 * changes — which reads as a broken affordance.
		 */
		var meaningfulOverflow = gallery_wrap.scrollHeight > capPx + 200;
		reveal_root.classList.toggle( 'has-no-overflow', ! meaningfulOverflow );
	}

	/**
	 * Wires the reveal-state plumbing for a single reveal-root subtree.
	 *
	 * @param {HTMLElement} reveal_root Element with the `plugin-screenshots__reveal` class.
	 */
	function bind( reveal_root ) {
		var gallery_wrap = reveal_root.querySelector( '.plugin-screenshots__gallery-wrap' );
		var button       = reveal_root.querySelector( '.plugin-screenshots__show-all' );
		if ( ! gallery_wrap || ! button ) {
			return;
		}

		/*
		 * Initial sync, then re-check as figures finish (load OR error —
		 * error still settles the layout, and missing the re-sync on
		 * errored figures locks the wrap in the wrong state). Listen for
		 * both events.
		 */
		syncOverflowState( reveal_root, gallery_wrap );
		var imgs = gallery_wrap.querySelectorAll( 'figure.wp-block-image img' );
		imgs.forEach( function ( img ) {
			if ( img.complete ) {
				return;
			}
			var resync = function () {
				img.removeEventListener( 'load', resync );
				img.removeEventListener( 'error', resync );
				if ( ! reveal_root.classList.contains( 'is-revealed' ) ) {
					syncOverflowState( reveal_root, gallery_wrap );
				}
			};
			img.addEventListener( 'load', resync );
			img.addEventListener( 'error', resync );
		} );

		// Resize re-evaluates because column-count flips at 600px.
		var raf = 0;
		window.addEventListener( 'resize', function () {
			cancelAnimationFrame( raf );
			raf = requestAnimationFrame( function () {
				if ( ! reveal_root.classList.contains( 'is-revealed' ) ) {
					syncOverflowState( reveal_root, gallery_wrap );
				}
			} );
		} );

		button.addEventListener( 'click', function () {
			reveal( reveal_root, gallery_wrap, button );
		} );
	}

	/**
	 * Boots the reveal plumbing on every gallery wrap inside the given
	 * root element.
	 *
	 * @param {Document|HTMLElement} root Document or section element to scan.
	 */
	function init( root ) {
		hideBrokenFigures( root );
		disableFallbackNavigation( root );
		var roots = root.querySelectorAll( '.plugin-screenshots__reveal' );
		roots.forEach( bind );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { init( document ); } );
	} else {
		init( document );
	}
} )();
