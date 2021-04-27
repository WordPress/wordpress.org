/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createPortal, forwardRef, useCallback, useState } from '@wordpress/element';
import { useMergeRefs } from '@wordpress/compose';

/* Note: This was copied from https://github.com/WordPress/gutenberg/blob/aca801a11162e881fd79f8c975af61e9a4d7daae/packages/block-editor/src/components/iframe/index.js
 * The copy was made to remove `tabIndex` and to add the ability to set the iframe's `title`.
 */

const BODY_CLASS_NAME = 'editor-styles-wrapper';

/**
 * Bubbles some event types (keydown, keypress, and dragover) to parent document
 * document to ensure that the keyboard shortcuts and drag and drop work.
 *
 * Ideally, we should remove event bubbling in the future. Keyboard shortcuts
 * should be context dependent, e.g. actions on blocks like Cmd+A should not
 * work globally outside the block editor.
 *
 * @param {Document} doc Document to attach listeners to.
 */
function bubbleEvents( doc ) {
	const { defaultView } = doc;
	const { frameElement } = defaultView;

	function bubbleEvent( event ) {
		const prototype = Object.getPrototypeOf( event );
		const constructorName = prototype.constructor.name;
		const Constructor = window[ constructorName ];

		const init = {};

		for ( const key in event ) {
			init[ key ] = event[ key ];
		}

		if ( event instanceof defaultView.MouseEvent ) {
			const rect = frameElement.getBoundingClientRect();
			init.clientX += rect.left;
			init.clientY += rect.top;
		}

		const newEvent = new Constructor( event.type, init );
		const cancelled = ! frameElement.dispatchEvent( newEvent );

		if ( cancelled ) {
			event.preventDefault();
		}
	}

	const eventTypes = [ 'keydown', 'keypress', 'dragover' ];

	for ( const name of eventTypes ) {
		doc.addEventListener( name, bubbleEvent );
	}
}

/**
 * Sets the document direction.
 *
 * Sets the `editor-styles-wrapper` class name on the body.
 *
 * Copies the `admin-color-*` class name to the body so that the admin color
 * scheme applies to components in the iframe.
 *
 * @param {Document} doc Document to add class name to.
 */
function setBodyClassName( doc ) {
	doc.dir = document.dir;
	doc.body.className = BODY_CLASS_NAME;

	for ( const name of document.body.classList ) {
		if ( name.startsWith( 'admin-color-' ) ) {
			doc.body.classList.add( name );
		}
	}
}

/**
 * Sets the document head and default styles.
 *
 * @param {Document} doc  Document to set the head for.
 * @param {string}   head HTML to set as the head.
 */
function setHead( doc, head ) {
	doc.head.innerHTML =
		// Body margin must be overridable by themes.
		'<style>body{margin:0}</style>' + head;
}

function Iframe( { contentRef, children, head, headHTML, themeSlug, ...props }, ref ) {
	const [ iframeDocument, setIframeDocument ] = useState();

	headHTML +=
		'<style>body{pointer-events:none;display: flex;align-items: center;justify-content: center;min-height: 100vh;} body > div {width: 100%}</style>';

	if ( themeSlug ) {
		headHTML += `<link rel="stylesheet" href="https://wp-themes.com/wp-content/themes/${ themeSlug }/style.css" media="all" />`;
	} else {
		headHTML += '<link rel="stylesheet" href="https://wp-themes.com/wp-content/themes/twentytwentyone/style.css" media="all" />';
	}

	const setRef = useCallback( ( node ) => {
		if ( ! node ) {
			return;
		}

		function setDocumentIfReady() {
			const { contentDocument } = node;
			const { readyState, body } = contentDocument;

			if ( readyState !== 'interactive' && readyState !== 'complete' ) {
				return false;
			}

			if ( typeof contentRef === 'function' ) {
				contentRef( body );
			} else if ( contentRef ) {
				contentRef.current = body;
			}

			setHead( contentDocument, headHTML );
			setBodyClassName( contentDocument );
			bubbleEvents( contentDocument );
			setBodyClassName( contentDocument );
			setIframeDocument( contentDocument );

			return true;
		}

		if ( setDocumentIfReady() ) {
			return;
		}

		// Document is not immediately loaded in Firefox.
		node.addEventListener( 'load', () => {
			setDocumentIfReady();
		} );
	}, [] );

	return (
		<iframe
			title={ __( 'Pattern Preview', 'wporg-patterns' ) }
			tabIndex="-1"
			{ ...props }
			ref={ useMergeRefs( [ ref, setRef ] ) }
		>
			{ iframeDocument && createPortal( children, iframeDocument.body ) }
			{ iframeDocument && createPortal( head, iframeDocument.head ) }
		</iframe>
	);
}

export default forwardRef( Iframe );
