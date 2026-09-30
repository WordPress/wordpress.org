/**
 * Augments the core lightbox overlay with a figcaption mounted at the
 * overlay level, beneath the active image.
 *
 * Core still owns the zoom animation, navigation buttons, and the
 * active image container geometry. This layer only reads the active
 * image's caption text, renders a dedicated caption bar below the
 * image, and switches the text alignment when the rendered caption
 * grows beyond a short-label treatment.
 *
 * `data-wp-text` / `data-wp-bind` attributes added after the
 * Interactivity runtime parsed the page do not bind, so the caption is
 * driven imperatively via a MutationObserver on every overlay <img>.
 */

( function () {
	// Delay the first caption reveal until core's lightbox zoom sizing has settled.
	var CAPTION_REVEAL_DELAY = 430;

	// Synthetic Plugin Directory screenshot IDs start here; ordinary images stay unmanaged.
	// Keep in sync with SYNTHETIC_ID_OFFSET in plugin-directory/shortcodes/class-screenshots.php.
	var SCREENSHOT_ID_OFFSET = 9000000;

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
		// Iterate from the end: core's containers center-overlap and the
		// last one (the full-resolution image) paints on top, so the
		// caption must live there or it renders behind the enlarged image.
		for ( var i = containers.length - 1; i >= 0; i-- ) {
			var img = containers[ i ].querySelector( 'img' );
			if ( img && isInViewport( img ) ) {
				return containers[ i ];
			}
		}
		// Fallback to the last container because a misplaced caption is better than none.
		return containers[ containers.length - 1 ] || null;
	}

	/**
	 * Keeps the caption as a direct child of the overlay so it can sit in
	 * a dedicated bar beneath the active image without disturbing core's
	 * overlapping lightbox-image containers.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLElement} caption The figcaption element to move.
	 */
	function moveCaptionIntoOverlay( overlay, caption ) {
		var insertBefore = overlay.querySelector( '.screen-reader-text, .scrim' );
		if ( caption.parentNode !== overlay ) {
			overlay.insertBefore( caption, insertBefore || null );
		}
	}

	/**
	 * Extracts the attachment-style id from a lightbox image class.
	 *
	 * Screenshot gallery images use a high synthetic id range so they can be
	 * distinguished from ordinary site images that should keep core behavior.
	 *
	 * @param {HTMLImageElement|null} img Lightbox image element.
	 * @return {number}
	 */
	function getImageBlockId( img ) {
		var match;

		if ( ! img || ! img.className ) {
			return 0;
		}

		match = img.className.match( /\bwp-image-(\d+)\b/ );

		if ( ! match ) {
			return 0;
		}

		return parseInt( match[1], 10 ) || 0;
	}

	/**
	 * Whether the current lightbox image belongs to the Plugin Directory
	 * screenshots gallery rather than some other site lightbox.
	 *
	 * @param {HTMLImageElement|null} img Lightbox image element.
	 * @return {boolean}
	 */
	function isManagedImage( img ) {
		return getImageBlockId( img ) >= SCREENSHOT_ID_OFFSET;
	}

	/**
	 * Whether the active overlay currently shows a managed screenshot image.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {boolean}
	 */
	function isManagedOverlay( overlay ) {
		var target = getVisibleContainer( overlay );
		var img    = target ? target.querySelector( 'img' ) : null;

		return isManagedImage( img );
	}

	/**
	 * Returns a stable key for the active managed screenshot.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {string}
	 */
	function getActiveImageKey( overlay ) {
		var target = getVisibleContainer( overlay );
		var img    = target ? target.querySelector( 'img' ) : null;

		if ( ! isManagedImage( img ) ) {
			return '';
		}

		return [
			getImageBlockId( img ),
			img.currentSrc || img.src || img.getAttribute( 'src' ) || '',
		].join( '|' );
	}

	/**
	 * Whether the lightbox overlay is currently active.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {boolean}
	 */
	function isOverlayActive( overlay ) {
		return overlay.classList.contains( 'active' );
	}

	/**
	 * Whether the overlay has finished its current transition.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {boolean}
	 */
	function isOverlaySettled( overlay ) {
		return overlay.dataset.wporgCaptionSettled === '1';
	}

	/**
	 * Marks the overlay as still transitioning.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function markOverlayUnsettled( overlay ) {
		overlay.dataset.wporgCaptionSettled = '0';
	}

	/**
	 * Marks the overlay as stable enough for geometry-sensitive caption work.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function markOverlaySettled( overlay ) {
		overlay.dataset.wporgCaptionSettled = '1';
	}

	/**
	 * Returns whether the first caption reveal is still waiting for core zoom.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @return {boolean}
	 */
	function isCaptionRevealPending( overlay ) {
		return overlay.dataset.wporgCaptionRevealPending === '1';
	}

	/**
	 * Marks the caption as waiting for the first reveal.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function markCaptionRevealPending( overlay ) {
		overlay.dataset.wporgCaptionRevealPending = '1';
		overlay.dataset.wporgCaptionRevealKey     = getActiveImageKey( overlay );
	}

	/**
	 * Clears the first reveal waiting state.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function clearCaptionRevealPending( overlay ) {
		delete overlay.dataset.wporgCaptionRevealPending;
		delete overlay.dataset.wporgCaptionRevealKey;
	}

	/**
	 * Clears cached core lightbox dimensions so the next settled sync starts
	 * from the current image rather than a stale transition frame.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function clearOverlayMetricCache( overlay ) {
		delete overlay.dataset.wporgLightboxBaseKey;
		delete overlay.dataset.wporgLightboxContainerWidth;
		delete overlay.dataset.wporgLightboxContainerHeight;
		delete overlay.dataset.wporgLightboxImageWidth;
		delete overlay.dataset.wporgLightboxImageHeight;
		delete overlay.dataset.wporgLightboxScale;
		delete overlay.dataset.wporgLightboxGeometryApplied;
	}

	/**
	 * Clears any pending deferred caption sync.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function clearDeferredSync( overlay ) {
		if ( overlay.wporgCaptionSyncTimer ) {
			window.clearTimeout( overlay.wporgCaptionSyncTimer );
			overlay.wporgCaptionSyncTimer = null;
		}
	}

	/**
	 * Clears any pending caption reveal.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function clearCaptionReveal( overlay ) {
		if ( overlay.wporgCaptionRevealTimer ) {
			window.clearTimeout( overlay.wporgCaptionRevealTimer );
			overlay.wporgCaptionRevealTimer = null;
		}

		clearCaptionRevealPending( overlay );
	}

	/**
	 * Reveals the caption after layout-sensitive sizing has settled.
	 *
	 * Delaying the opacity flip avoids showing the caption while the
	 * image and caption widths are still being recomputed.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLElement} caption The figcaption element.
	 */
	function scheduleCaptionReveal( overlay, caption ) {
		clearCaptionReveal( overlay );

		if ( ! isOverlayActive( overlay ) || ! caption.classList.contains( 'has-caption' ) ) {
			return;
		}

		markCaptionRevealPending( overlay );

		overlay.wporgCaptionRevealTimer = window.setTimeout( function () {
			overlay.wporgCaptionRevealTimer = null;
			clearCaptionRevealPending( overlay );

			if ( ! isOverlayActive( overlay ) || ! caption.classList.contains( 'has-caption' ) ) {
				return;
			}

			caption.classList.add( 'is-ready' );
		}, 24 );
	}

	/**
	 * Hides the caption without touching the current lightbox geometry.
	 *
	 * We intentionally leave core sizing variables as-is during the close
	 * animation so the zoom-back transition does not snap.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLElement} caption The figcaption element.
	 */
	function hideCaption( overlay, caption ) {
		clearCaptionReveal( overlay );
		caption.classList.remove( 'has-caption', 'is-long-caption', 'is-ready' );
		caption.style.removeProperty( '--wporg-lightbox-caption-width' );
		caption.style.removeProperty( '--wporg-lightbox-caption-top' );
		caption.style.removeProperty( '--wporg-lightbox-caption-max-height' );
	}

	/**
	 * Returns the number of rendered text lines inside the caption.
	 *
	 * The CSS keeps a stable width for the caption bar, so the rendered
	 * line count reflects the real lightbox layout on both desktop and
	 * narrow mobile viewports.
	 *
	 * @param {HTMLElement} caption The figcaption element.
	 * @return {number}
	 */
	function getRenderedLineCount( caption ) {
		var styles = window.getComputedStyle( caption );
		var lineHeight = parseFloat( styles.lineHeight );
		var paddingTop = parseFloat( styles.paddingTop ) || 0;
		var paddingBottom = parseFloat( styles.paddingBottom ) || 0;

		if ( ! lineHeight ) {
			return 0;
		}

		return Math.max(
			0,
			( caption.getBoundingClientRect().height - paddingTop - paddingBottom ) / lineHeight
		);
	}

	/**
	 * Whether two rectangles overlap.
	 *
	 * @param {DOMRect} a First rectangle.
	 * @param {DOMRect|null} b Second rectangle.
	 * @return {boolean}
	 */
	function rectsOverlap( a, b ) {
		return !! b && ! (
			a.right <= b.left ||
			a.left >= b.right ||
			a.bottom <= b.top ||
			a.top >= b.bottom
		);
	}

	/**
	 * Reads a numeric lightbox size variable off the overlay.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {string} variableName CSS custom property name.
	 * @return {number}
	 */
	function getOverlayMetric( overlay, variableName ) {
		var inlineValue = overlay.style.getPropertyValue( variableName );
		var computedValue = inlineValue || window.getComputedStyle( overlay ).getPropertyValue( variableName );
		var parsedValue = parseFloat( computedValue );

		return Number.isFinite( parsedValue ) ? parsedValue : 0;
	}

	/**
	 * Stores the current lightbox image dimensions for the active image and viewport.
	 *
	 * Core recalculates these values when the user navigates between images or
	 * resizes the viewport. We cache the current set so the caption logic can
	 * temporarily shrink the visible image and still restore the original values
	 * before the next measurement pass.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLImageElement|null} img The active lightbox image.
	 */
	function cacheOverlayMetrics( overlay, img ) {
		var key = ( img ? img.getAttribute( 'src' ) || '' : '' ) +
			'@' + window.innerWidth + 'x' + window.innerHeight;

		if ( overlay.dataset.wporgLightboxBaseKey === key ) {
			return;
		}

		overlay.dataset.wporgLightboxBaseKey = key;
		overlay.dataset.wporgLightboxContainerWidth = String(
			getOverlayMetric( overlay, '--wp--lightbox-container-width' )
		);
		overlay.dataset.wporgLightboxContainerHeight = String(
			getOverlayMetric( overlay, '--wp--lightbox-container-height' )
		);
		overlay.dataset.wporgLightboxImageWidth = String(
			getOverlayMetric( overlay, '--wp--lightbox-image-width' )
		);
		overlay.dataset.wporgLightboxImageHeight = String(
			getOverlayMetric( overlay, '--wp--lightbox-image-height' )
		);
		overlay.dataset.wporgLightboxScale = String(
			getOverlayMetric( overlay, '--wp--lightbox-scale' )
		);
	}

	/**
	 * Restores the lightbox image dimensions captured from core.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function resetOverlayMetrics( overlay ) {
		var metrics = {
			'--wp--lightbox-container-width': overlay.dataset.wporgLightboxContainerWidth,
			'--wp--lightbox-container-height': overlay.dataset.wporgLightboxContainerHeight,
			'--wp--lightbox-image-width': overlay.dataset.wporgLightboxImageWidth,
			'--wp--lightbox-image-height': overlay.dataset.wporgLightboxImageHeight,
			'--wp--lightbox-scale': overlay.dataset.wporgLightboxScale,
		};

		Object.keys( metrics ).forEach( function ( variableName ) {
			if ( ! metrics[ variableName ] ) {
				return;
			}

			if ( variableName === '--wp--lightbox-scale' ) {
				overlay.style.setProperty( variableName, metrics[ variableName ] );
				return;
			}

			overlay.style.setProperty( variableName, metrics[ variableName ] + 'px' );
		} );
	}

	/**
	 * Reads the rendered image dimensions from the active lightbox image.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLImageElement|null} img The active lightbox image.
	 * @return {{width:number,height:number}}
	 */
	function getRenderedImageMetrics( overlay, img ) {
		var overlayWidth = getOverlayMetric( overlay, '--wp--lightbox-image-width' );
		var overlayHeight = getOverlayMetric( overlay, '--wp--lightbox-image-height' );
		var imageRect = img ? img.getBoundingClientRect() : null;

		if ( overlayWidth && overlayHeight ) {
			return {
				width: overlayWidth,
				height: overlayHeight,
			};
		}

		return {
			width: imageRect ? imageRect.width : getOverlayMetric( overlay, '--wp--lightbox-image-width' ),
			height: imageRect ? imageRect.height : getOverlayMetric( overlay, '--wp--lightbox-image-height' ),
		};
	}

	/**
	 * Whether the active screenshot is portrait-shaped or otherwise very narrow.
	 *
	 * Those screenshots already get a wider caption bar and need the fitting
	 * math to use the intended lightbox geometry, not the current animation
	 * frame, otherwise the open transition can visibly jump mid-flight.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLImageElement|null} img The active lightbox image.
	 * @return {boolean}
	 */
	function isPortraitOrNarrowImage( overlay, img ) {
		var imageMetrics = getRenderedImageMetrics( overlay, img );
		var aspectRatio = imageMetrics.width ? imageMetrics.height / imageMetrics.width : 0;

		return aspectRatio > 1.2 || ( imageMetrics.width > 0 && imageMetrics.width < 320 );
	}

	/**
	 * Shrinks the lightbox image proportionally when a below-image caption would
	 * otherwise fall out of the viewport or into the navigation controls.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLElement} caption The figcaption element.
	 * @param {HTMLImageElement|null} img The active lightbox image.
	 */
	function fitCaptionWithinViewport( overlay, caption, img ) {
		var bottomPadding = 16;
		var attempt = 0;
		var minContainerScale = 0.5;
		var nextButton = overlay.querySelector( '.wp-lightbox-navigation-button-next' );
		var prevButton = overlay.querySelector( '.wp-lightbox-navigation-button-prev' );
		var maxCaptionBottom = window.innerHeight - bottomPadding;
		var nextRect = nextButton ? nextButton.getBoundingClientRect() : null;
		var prevRect = prevButton ? prevButton.getBoundingClientRect() : null;
		var isPortraitOrNarrow = isPortraitOrNarrowImage( overlay, img );
		var overflow;

		if ( isPortraitOrNarrow ) {
			if ( window.innerWidth <= 480 ) {
				minContainerScale = 0.2;
			} else if ( window.innerWidth <= 960 || window.innerHeight <= 700 ) {
				minContainerScale = 0.24;
			} else {
				minContainerScale = 0.28;
			}
		} else if ( window.innerWidth <= 480 ) {
			minContainerScale = 0.28;
		} else if ( window.innerWidth <= 960 || window.innerHeight <= 700 ) {
			minContainerScale = 0.35;
		}

		overflow = Math.max(
			0,
			caption.getBoundingClientRect().bottom - maxCaptionBottom
		);

		if ( rectsOverlap( caption.getBoundingClientRect(), nextRect ) ) {
			overflow = Math.max(
				overflow,
				caption.getBoundingClientRect().bottom - ( nextRect.top - 12 )
			);
		}

		if ( rectsOverlap( caption.getBoundingClientRect(), prevRect ) ) {
			overflow = Math.max(
				overflow,
				caption.getBoundingClientRect().bottom - ( prevRect.top - 12 )
			);
		}

		while ( overflow > 0 && attempt < 8 ) {
			var currentContainerWidth = getOverlayMetric( overlay, '--wp--lightbox-container-width' );
			var currentContainerHeight = getOverlayMetric( overlay, '--wp--lightbox-container-height' );
			var currentImageWidth = getOverlayMetric( overlay, '--wp--lightbox-image-width' );
			var currentImageHeight = getOverlayMetric( overlay, '--wp--lightbox-image-height' );
			var currentLightboxScale = getOverlayMetric( overlay, '--wp--lightbox-scale' );
			var minContainerHeight = parseFloat( overlay.dataset.wporgLightboxContainerHeight ) * minContainerScale;
			var newContainerHeight = Math.max(
				minContainerHeight,
				currentContainerHeight - ( overflow * 2 )
			);
			var scale;

			if (
				! currentContainerWidth ||
				! currentContainerHeight ||
				! currentImageWidth ||
				! currentImageHeight ||
				! currentLightboxScale ||
				newContainerHeight === currentContainerHeight
			) {
				return;
			}

			scale = newContainerHeight / currentContainerHeight;

			// These are core Image block lightbox internals set by setOverlayStyles().
			// Core rewrites them whenever the lightbox opens, navigates, or resizes.
			// See https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/image/view.js#L585-L597.
			overlay.style.setProperty(
				'--wp--lightbox-container-width',
				( currentContainerWidth * scale ) + 'px'
			);
			overlay.style.setProperty(
				'--wp--lightbox-container-height',
				newContainerHeight + 'px'
			);
			overlay.style.setProperty(
				'--wp--lightbox-image-width',
				( currentImageWidth * scale ) + 'px'
			);
			overlay.style.setProperty(
				'--wp--lightbox-image-height',
				( currentImageHeight * scale ) + 'px'
			);
			overlay.style.setProperty(
				'--wp--lightbox-scale',
				currentLightboxScale / scale
			);

			overflow = Math.max(
				0,
				caption.getBoundingClientRect().bottom - maxCaptionBottom
			);

			if ( rectsOverlap( caption.getBoundingClientRect(), nextRect ) ) {
				overflow = Math.max(
					overflow,
					caption.getBoundingClientRect().bottom - ( nextRect.top - 12 )
				);
			}

			if ( rectsOverlap( caption.getBoundingClientRect(), prevRect ) ) {
				overflow = Math.max(
					overflow,
					caption.getBoundingClientRect().bottom - ( prevRect.top - 12 )
				);
			}

			attempt++;
		}
	}

	/**
	 * Keeps portrait and very narrow screenshots readable by letting a
	 * text-heavy caption grow wider than the rendered image.
	 *
	 * Short labels stay aligned to the image width. Longer copy can use a
	 * wider bar when the screenshot itself is too narrow to carry a
	 * paragraph-shaped caption comfortably.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLElement} caption The figcaption element.
	 * @param {HTMLImageElement|null} img The active lightbox image.
	 * @param {string} text The caption text.
	 * @param {boolean} promoteForLongCaption Whether a measured paragraph
	 *                                        caption should get extra width.
	 */
	function applyCaptionWidth( overlay, caption, img, text, promoteForLongCaption ) {
		var imageMetrics = getRenderedImageMetrics( overlay, img );
		var imageWidth = imageMetrics.width;
		var viewportWidth = Math.max( 0, window.innerWidth - 16 );
		var isPortraitOrNarrow = isPortraitOrNarrowImage( overlay, img );
		var preferredWidth = imageWidth;
		var relaxedWidth;

		if ( ! imageWidth || ! viewportWidth ) {
			caption.style.removeProperty( '--wporg-lightbox-caption-width' );
			return;
		}

		if ( isPortraitOrNarrow && ( text.length > 60 || promoteForLongCaption ) ) {
			relaxedWidth = Math.max(
				imageWidth,
				Math.min( 420, imageWidth * 2 ),
				300
			);

			if ( promoteForLongCaption ) {
				relaxedWidth = Math.max(
					relaxedWidth,
					Math.min( 460, imageWidth * 2.3 ),
					340
				);
			}

			preferredWidth = Math.min( viewportWidth, relaxedWidth );
		}

		caption.style.setProperty( '--wporg-lightbox-caption-width', preferredWidth + 'px' );
	}

	/**
	 * Syncs caption width and long-text detection against the current
	 * rendered image size.
	 *
	 * The image may shrink after viewport-fitting, so this pass can be
	 * repeated after geometry changes to keep the final caption width in
	 * step with the visible screenshot.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {HTMLElement} caption The figcaption element.
	 * @param {HTMLImageElement|null} img The active lightbox image.
	 * @param {string} text The caption text.
	 */
	function syncCaptionPresentation( overlay, caption, img, text ) {
		var isLongCaption;

		caption.classList.remove( 'is-long-caption' );
		applyCaptionWidth( overlay, caption, img, text, false );

		isLongCaption = isParagraphCaption( text, caption );
		if ( isLongCaption ) {
			applyCaptionWidth( overlay, caption, img, text, true );
			isLongCaption = isParagraphCaption( text, caption );
		}

		caption.classList.toggle( 'is-long-caption', isLongCaption );
	}

	/**
	 * Decides whether the caption should be treated as long-form copy.
	 *
	 * Short product-style captions keep the same centered treatment even if a
	 * narrow mobile viewport wraps them. Longer descriptions still keep that
	 * centered treatment, but we track them separately for width heuristics.
	 *
	 * @param {string} text The caption text.
	 * @param {HTMLElement} caption The figcaption element.
	 * @return {boolean}
	 */
	function isParagraphCaption( text, caption ) {
		var renderedLineCount = getRenderedLineCount( caption );

		return renderedLineCount > 2.5 || ( text.length > 220 && renderedLineCount > 2 );
	}

	/**
	 * Reveals the caption after core's opening zoom has finished.
	 *
	 * Geometry is applied before the first visible zoom frame so the image
	 * does not jump at the end of the animation. Text waits until the zoom
	 * completes, keeping the opening motion focused on the screenshot.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 */
	function scheduleDeferredSync( overlay ) {
		clearDeferredSync( overlay );

		if ( ! isOverlayActive( overlay ) ) {
			return;
		}

		markCaptionRevealPending( overlay );

		overlay.wporgCaptionSyncTimer = window.setTimeout( function () {
			overlay.wporgCaptionSyncTimer = null;
			markOverlaySettled( overlay );
			scheduleCaptionReveal( overlay, ensureCaption( overlay ) );
		}, CAPTION_REVEAL_DELAY );
	}

	/**
	 * Delays the caption reveal until core finishes the opening animation.
	 *
	 * @param {HTMLElement} overlay       The `.wp-lightbox-overlay` element.
	 * @param {boolean}     forceGeometry Whether to recalculate during a pending reveal.
	 */
	function requestSettledSync( overlay, forceGeometry ) {
		var shouldSyncImmediately = isOverlaySettled( overlay );

		if ( ! isOverlayActive( overlay ) ) {
			clearDeferredSync( overlay );
			markOverlayUnsettled( overlay );
			clearOverlayMetricCache( overlay );
			hideCaption( overlay, ensureCaption( overlay ) );
			return;
		}

		if ( ! isManagedOverlay( overlay ) ) {
			clearDeferredSync( overlay );
			markOverlayUnsettled( overlay );
			clearOverlayMetricCache( overlay );

			if ( overlay.querySelector( 'figcaption.wp-lightbox-caption' ) ) {
				hideCaption( overlay, ensureCaption( overlay ) );
			}

			return;
		}

		if ( isCaptionRevealPending( overlay ) ) {
			var pendingKey = overlay.dataset.wporgCaptionRevealKey || '';
			var currentKey = getActiveImageKey( overlay );

			if ( currentKey === pendingKey && ! forceGeometry ) {
				return;
			}

			clearCaptionReveal( overlay );
		}

		if ( shouldSyncImmediately ) {
			clearDeferredSync( overlay );
			clearCaptionReveal( overlay );
			clearOverlayMetricCache( overlay );
			syncCaption( overlay, true );
			return;
		}

		clearOverlayMetricCache( overlay );
		markOverlaySettled( overlay );
		syncCaption( overlay, false );
		markOverlayUnsettled( overlay );
		scheduleDeferredSync( overlay );
	}

	/**
	 * Returns the overlay's caption node, creating it on first call.
	 *
	 * The caption lives at the overlay level so it survives container
	 * shuffles between open / close and can be positioned outside the
	 * image frame.
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
	 * Reads the active image's alt text and writes it into the caption
	 * bar below the visible image container.
	 *
	 * @param {HTMLElement} overlay The `.wp-lightbox-overlay` element.
	 * @param {boolean} keepReady Whether an already-visible caption should stay visible.
	 */
	function syncCaption( overlay, keepReady ) {
		var target  = getVisibleContainer( overlay );
		// Read alt off the visible image (the off-screen sibling holds stale state).
		var img  = target ? target.querySelector( 'img' ) : null;
		var text = img ? img.getAttribute( 'alt' ) || '' : '';
		var caption;

		if ( ! isOverlayActive( overlay ) ) {
			clearDeferredSync( overlay );
			markOverlayUnsettled( overlay );
			clearOverlayMetricCache( overlay );
			caption = overlay.querySelector( 'figcaption.wp-lightbox-caption' );

			if ( caption ) {
				hideCaption( overlay, caption );
			}

			return;
		}

		if ( ! isManagedImage( img ) ) {
			clearDeferredSync( overlay );
			markOverlayUnsettled( overlay );
			clearOverlayMetricCache( overlay );
			caption = overlay.querySelector( 'figcaption.wp-lightbox-caption' );

			if ( caption ) {
				hideCaption( overlay, caption );
			}

			return;
		}

		caption = ensureCaption( overlay );

		if ( ! isOverlayActive( overlay ) ) {
			clearDeferredSync( overlay );
			markOverlayUnsettled( overlay );
			clearOverlayMetricCache( overlay );
			hideCaption( overlay, caption );
			return;
		}

		moveCaptionIntoOverlay( overlay, caption );
		caption.textContent = text;

		if ( text === '' ) {
			hideCaption( overlay, caption );
			return;
		}

		caption.classList.add( 'has-caption' );
		if ( ! keepReady ) {
			caption.classList.remove( 'is-ready' );
		}

		if ( ! isOverlaySettled( overlay ) ) {
			return;
		}

		if ( overlay.dataset.wporgLightboxGeometryApplied !== '1' ) {
			cacheOverlayMetrics( overlay, img );
			resetOverlayMetrics( overlay );
			syncCaptionPresentation( overlay, caption, img, text );
			fitCaptionWithinViewport( overlay, caption, img );
			syncCaptionPresentation( overlay, caption, img, text );
			overlay.dataset.wporgLightboxGeometryApplied = '1';
		}

		syncCaptionPresentation( overlay, caption, img, text );

		if ( keepReady ) {
			caption.classList.add( 'is-ready' );
		}
	}

	/**
	 * Boots the caption pipeline once the overlay is in the DOM.
	 */
	function init() {
		var overlay = document.querySelector( '.wp-lightbox-overlay' );
		if ( ! overlay ) {
			return;
		}

		markOverlayUnsettled( overlay );
		requestSettledSync( overlay );

		/*
		 * Watch every image inside the overlay; core flips alt/src on
		 * the active one when the user clicks triggers or arrow-navs.
		 */
		var imgs = overlay.querySelectorAll( '.lightbox-image-container img' );
		imgs.forEach( function ( img ) {
			var observer = new MutationObserver( function () {
				requestSettledSync( overlay );
			} );
			observer.observe( img, { attributes: true, attributeFilter: [ 'alt', 'src' ] } );
		} );

		/*
		 * Re-sync when the overlay's active class flips (open / close
		 * transitions can move which container is on screen).
		 */
		var classObserver = new MutationObserver( function () {
			requestSettledSync( overlay );
		} );
		classObserver.observe( overlay, { attributes: true, attributeFilter: [ 'class' ] } );

		window.addEventListener( 'resize', function () {
			requestSettledSync( overlay, true );
		} );

		if ( window.visualViewport ) {
			window.visualViewport.addEventListener( 'resize', function () {
				requestSettledSync( overlay, true );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
