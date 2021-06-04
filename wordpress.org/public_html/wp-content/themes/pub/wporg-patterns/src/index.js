/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Pattern from './components/pattern';
import Patterns from './components/patterns';

// Load the grid into the awaiting preview container.
const gridContainer = document.getElementById( 'patterns__container' );
if ( gridContainer ) {
	render( <Patterns />, gridContainer );
}

// Load the preview into any awaiting preview container.
const previewContainers = document.querySelectorAll( '.pattern-preview__container' );
for ( let i = 0; i < previewContainers.length; i++ ) {
	const container = previewContainers[ i ];
	const blockContent = JSON.parse( decodeURIComponent( container.innerText ) );
	const props = container.dataset;

	render( <Pattern { ...props } content={ blockContent } />, container, () => {
		// This callback is called after the render to unhide the container.
		container.hidden = false;
	} );
}
