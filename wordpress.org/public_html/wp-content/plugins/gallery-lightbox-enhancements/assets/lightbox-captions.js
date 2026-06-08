/**
 * Augments the core lightbox overlay with a figcaption mounted inside
 * the *visible* enlarged-image container, so the caption sits flush
 * against the bottom edge of the picture frame — exactly the visual
 * contract of https://github.com/WordPress/gutenberg/pull/77477.
 *
 * Why "visible": core renders two `.lightbox-image-container` siblings.
 * One holds the small thumbnail used for the zoom animation hand-off,
 * the other holds the full-resolution image. Which one is on screen
 * depends on the lifecycle stage (opening, navigating, closing). We
 * pick the container whose <img> is actually inside the viewport at
 * sync time, mount the caption there, and let CSS pin it to its
 * parent's bottom — which is the bottom of the displayed picture.
 *
 * `data-wp-text` / `data-wp-bind` attributes added after the
 * Interactivity runtime parsed the page do not bind, so the caption is
 * driven imperatively via a MutationObserver on every overlay <img>.
 */

( function () {
	/**
	 * Whether the element overlaps the current viewport, even partially.
	 *
	 * Partial intersection counts; the off-screen sibling sits fully
	 * past the viewport and gets ruled out cleanly.
	 *
	 * @param {HTMLElement} el Element under test.
	 * @return {boolean}
	 */
	function isInViewport( el ) {
		var rect = el.getBoundingClientRect();
		var vh   = window.innerHeight || document.documentElement.clientHeight;
		var vw   = window.innerWidth || document.documentElement.clientWidth;
		return (
			rect.bottom > 0 &&
			rect.top < vh &&
			rect.right > 0 &&
			rect.left < vw
		);
	}

	/**
	 * Returns the lightbox image container whose <img> is currently on
	 * screen, falling back to the first container when nothing matches.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {HTMLElement|null}
	 */
	function getVisibleContainer( overlay ) {
		var containers = overlay.querySelectorAll( '.lightbox-image-container' );
		for ( var i = 0; i < containers.length; i++ ) {
			var img = containers[ i ].querySelector( 'img' );
			if ( img && isInViewport( img ) ) {
				return containers[ i ];
			}
		}
		// Fallback: first container — wrong-spot caption beats no caption at all.
		return containers[ 0 ] || null;
	}

	/**
	 * Moves the caption node into the target container if it isn't
	 * already attached there.
	 *
	 * @param {HTMLElement|null} target  Container that should host the caption.
	 * @param {HTMLElement}      caption The figcaption element to move.
	 */
	function moveCaptionInto( target, caption ) {
		if ( target && caption.parentNode !== target ) {
			target.appendChild( caption );
		}
	}

	/**
	 * Returns the overlay's caption node, creating it on first call.
	 *
	 * The caption lives at the overlay level so it survives container
	 * shuffles between open / close. It is moved into whichever
	 * container is visible at sync time.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {HTMLElement}
	 */
	function ensureCaption( overlay ) {
		var caption = overlay.querySelector( 'figcaption.wp-lightbox-caption' );
		if ( ! caption ) {
			caption = document.createElement( 'figcaption' );
			caption.className = 'wp-lightbox-caption';
			overlay.appendChild( caption );
		}
		return caption;
	}

	/**
	 * Reads the active image's alt text and writes it into the caption,
	 * mounted under the visible container.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function syncCaption( overlay ) {
		var caption = ensureCaption( overlay );
		var target  = getVisibleContainer( overlay );
		moveCaptionInto( target, caption );

		// Read alt off the visible image (the off-screen sibling holds stale state).
		var img  = target ? target.querySelector( 'img' ) : null;
		var text = img ? img.getAttribute( 'alt' ) || '' : '';
		caption.textContent = text;
		caption.classList.toggle( 'has-caption', text !== '' );
	}

	/**
	 * Boots the caption pipeline once the overlay is in the DOM.
	 */
	function init() {
		var overlay = document.querySelector( '.wp-lightbox-overlay' );
		if ( ! overlay ) {
			return;
		}

		syncCaption( overlay );

		/*
		 * Watch every image inside the overlay; core flips alt/src on
		 * the active one when the user clicks triggers or arrow-navs.
		 */
		var imgs = overlay.querySelectorAll( '.lightbox-image-container img' );
		imgs.forEach( function ( img ) {
			var observer = new MutationObserver( function () {
				syncCaption( overlay );
			} );
			observer.observe( img, { attributes: true, attributeFilter: [ 'alt', 'src' ] } );
		} );

		/*
		 * Re-sync when the overlay's active class flips (open / close
		 * transitions can move which container is on screen).
		 */
		var classObserver = new MutationObserver( function () {
			syncCaption( overlay );
		} );
		classObserver.observe( overlay, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
